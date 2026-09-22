<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLandlordPayout;
use App\Models\PmLandlordPayoutItem;
use App\Models\PmLease;
use App\Models\PmPayment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Support\Property\PropertyUnitOccupancyStats;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class LandlordSettlementService
{
    public const LINE_REMITTANCE = 'remittance';

    public const LINE_DEPOSIT_REFUND = 'deposit_refund';

    public const LINE_TAX = 'tax';

    public const LINE_OTHER = 'other';

    public const LINE_ADVANCE = 'advance';

    public function __construct(
        private readonly AgentCommissionService $commission,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildSettlement(int $propertyId, int $landlordId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $link = DB::table('property_landlord')
            ->join('users as u', 'u.id', '=', 'property_landlord.user_id')
            ->join('properties as p', 'p.id', '=', 'property_landlord.property_id')
            ->where('property_landlord.property_id', $propertyId)
            ->where('property_landlord.user_id', $landlordId)
            ->select([
                'property_landlord.ownership_percent',
                'property_landlord.agreed_pay_day',
                'property_landlord.agreed_pay_notes',
                'u.name as landlord_name',
                'p.name as property_name',
            ])
            ->first();

        if (! $link) {
            throw new InvalidArgumentException('Landlord is not linked to this property.');
        }

        $ownershipPct = (float) $link->ownership_percent;
        $ownershipFactor = $ownershipPct / 100;
        $commissionPct = $this->commission->commissionPercentForProperty($propertyId);

        $collected = $this->collectedByTypeForProperty($propertyId, $periodStart, $periodEnd);
        $ownerCollected = [
            'rent' => round($collected['rent'] * $ownershipFactor, 2),
            'garbage' => round($collected['garbage'] * $ownershipFactor, 2),
            'water' => round($collected['water'] * $ownershipFactor, 2),
            'other' => round($collected['other'] * $ownershipFactor, 2),
            'total' => round($collected['total'] * $ownershipFactor, 2),
        ];

        $grossOwnerShare = $ownerCollected['total'];
        $managementFee = round($grossOwnerShare * ($commissionPct / 100), 2);
        $netCollected = round($grossOwnerShare - $managementFee, 2);

        $balanceBf = $this->ledgerNetBalance($landlordId, $propertyId, $periodStart);
        $periodCredits = $this->ledgerSum($landlordId, $propertyId, PmLandlordLedgerEntry::DIRECTION_CREDIT, $periodStart, $periodEnd);
        $periodDebits = $this->ledgerSum($landlordId, $propertyId, PmLandlordLedgerEntry::DIRECTION_DEBIT, $periodStart, $periodEnd);
        $closingBalance = round($balanceBf + $periodCredits - $periodDebits, 2);
        $netAmountDue = max(0.0, $closingBalance);

        $deductions = $this->periodDeductions($landlordId, $propertyId, $periodStart, $periodEnd);
        $additions = $this->periodAdditions($propertyId, $periodStart, $periodEnd);
        $openAdvances = $this->openAdvances($landlordId, $propertyId);
        $agreedPayDay = $link->agreed_pay_day !== null ? (int) $link->agreed_pay_day : null;
        $nextAgreedPayDate = app(LandlordAdvanceService::class)->nextAgreedPayDate($agreedPayDay, $periodEnd);
        $unitStats = PropertyUnitOccupancyStats::forProperty($propertyId);
        $unitLines = $this->unitSettlementLines($propertyId, $periodStart, $periodEnd);
        $unitTotals = $this->sumUnitLines($unitLines);
        $additionsTotal = round(collect($additions)->sum('amount'), 2);
        $deductionsTotal = round(collect($deductions)->sum('amount'), 2);
        $utilityReceived = round($collected['garbage'] + $collected['water'], 2);
        $otherExpenses = round(max(0.0, $collected['other']), 2);

        return [
            'property_id' => $propertyId,
            'landlord_id' => $landlordId,
            'property_name' => (string) $link->property_name,
            'landlord_name' => (string) $link->landlord_name,
            'ownership_percent' => $ownershipPct,
            'commission_percent' => $commissionPct,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_label' => $periodStart->format('F').' - '.$periodStart->format('Y'),
            'period_range_label' => $periodStart->format('d/m/Y').' - '.$periodEnd->format('d/m/Y'),
            'period_month' => $periodStart->format('Y-m'),
            'unit_stats' => $unitStats,
            'collected' => $collected,
            'owner_collected' => $ownerCollected,
            'rent_received' => round($collected['rent'], 2),
            'utility_received' => $utilityReceived,
            'other_expenses' => $otherExpenses,
            'management_fee' => $managementFee,
            'net_collected' => $netCollected,
            'balance_brought_forward' => $balanceBf,
            'period_credits' => $periodCredits,
            'period_debits' => $periodDebits,
            'additions' => $additions,
            'additions_total' => $additionsTotal,
            'deductions' => $deductions,
            'deductions_total' => $deductionsTotal,
            'open_advances' => $openAdvances,
            'open_advances_total' => round(collect($openAdvances)->sum('amount'), 2),
            'agreed_pay_day' => $agreedPayDay,
            'agreed_pay_notes' => (string) ($link->agreed_pay_notes ?? ''),
            'next_agreed_pay_date' => $nextAgreedPayDate?->format('Y-m-d'),
            'closing_balance' => $closingBalance,
            'net_amount_due' => $netAmountDue,
            'unit_lines' => $unitLines,
            'unit_totals' => $unitTotals,
        ];
    }

    public function createPayoutFromSettlement(
        array $settlement,
        User $actor,
        ?float $amountOverride = null,
    ): PmLandlordPayout {
        $amount = $amountOverride ?? (float) ($settlement['net_amount_due'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Settlement net amount due must be greater than zero.');
        }

        $propertyId = (int) ($settlement['property_id'] ?? 0);
        $landlordId = (int) ($settlement['landlord_id'] ?? 0);
        $periodMonth = (string) ($settlement['period_month'] ?? '');
        $propertyName = (string) ($settlement['property_name'] ?? 'Property');
        $periodLabel = (string) ($settlement['period_label'] ?? $periodMonth);

        return DB::transaction(function () use ($amount, $actor, $propertyId, $landlordId, $periodMonth, $propertyName, $periodLabel, $settlement) {
            $payout = PmLandlordPayout::query()->create([
                'agent_user_id' => (int) $actor->id,
                'total_amount' => $amount,
                'status' => 'draft',
                'created_by' => (int) $actor->id,
            ]);

            PmLandlordPayoutItem::query()->create([
                'payout_id' => (int) $payout->id,
                'landlord_id' => $landlordId,
                'property_id' => $propertyId > 0 ? $propertyId : null,
                'amount' => $amount,
                'line_type' => self::LINE_REMITTANCE,
                'description' => 'Rent remittance — '.$propertyName.' ('.$periodLabel.')',
                'period_month' => $periodMonth !== '' ? $periodMonth : null,
            ]);

            return $payout->fresh(['items']);
        });
    }

    public function approvePayout(PmLandlordPayout $payout, User $actor, bool $enforceMakerChecker = true): void
    {
        if ($payout->status !== 'draft') {
            throw new RuntimeException('Only draft payouts can be approved.');
        }

        if ($enforceMakerChecker
            && (int) ($payout->created_by ?? 0) > 0
            && (int) $payout->created_by === (int) $actor->id
            && ! ($actor->is_super_admin ?? false)
        ) {
            throw new RuntimeException('Maker-checker required: a different administrator must approve this payout.');
        }

        $payout->update([
            'status' => 'approved',
            'approved_by' => (int) $actor->id,
        ]);
    }

    /**
     * Cancel a draft payout that has not been paid (manual or B2C).
     */
    public function voidDraftPayout(PmLandlordPayout $payout, User $actor): void
    {
        if ($payout->status !== 'draft') {
            throw new RuntimeException('Only draft payouts can be voided.');
        }
        if ($payout->paid_at !== null || in_array((string) ($payout->payout_status ?? ''), ['pending', 'completed', 'success'], true)) {
            throw new RuntimeException('This payout already has a payment in progress or completed.');
        }

        DB::transaction(function () use ($payout): void {
            $payout->items()->delete();
            $payout->delete();
        });
    }

    public function markPayoutPaid(PmLandlordPayout $payout, User $actor, bool $enforceMakerChecker = true): void
    {
        if (! in_array($payout->status, ['draft', 'approved'], true)) {
            throw new RuntimeException('Payout cannot be marked paid from current status.');
        }

        if ($payout->status === 'draft') {
            throw new RuntimeException('Approve the payout before marking it paid (maker-checker).');
        }

        if ($enforceMakerChecker
            && (int) ($payout->created_by ?? 0) > 0
            && (int) $payout->created_by === (int) $actor->id
            && ! ($actor->is_super_admin ?? false)
        ) {
            throw new RuntimeException('Maker-checker required: the payout creator cannot mark it paid.');
        }

        DB::transaction(function () use ($payout, $actor) {
            $payout->loadMissing('items');
            $paidAt = now();

            foreach ($payout->items as $item) {
                $lineType = (string) $item->line_type;
                if (! in_array($lineType, [self::LINE_REMITTANCE, self::LINE_ADVANCE], true)) {
                    continue;
                }

                $landlord = User::query()->find((int) $item->landlord_id);
                if (! $landlord) {
                    continue;
                }

                $property = $item->property_id
                    ? Property::query()->find((int) $item->property_id)
                    : null;

                $defaultLabel = $lineType === self::LINE_ADVANCE ? 'Advance payment' : 'Remittance';

                LandlordLedger::post(
                    $landlord,
                    PmLandlordLedgerEntry::DIRECTION_DEBIT,
                    (float) $item->amount,
                    'Landlord payout #'.$payout->id.' — '.((string) ($item->description ?? $defaultLabel)),
                    $property,
                    'pm_landlord_payout',
                    (int) $payout->id,
                    $paidAt,
                );
            }

            $payout->update([
                'status' => 'paid',
                'approved_by' => $payout->approved_by ?? (int) $actor->id,
                'paid_at' => $paidAt,
            ]);

            app(PropertyTrustAccountingService::class)->postLandlordPayout($payout->fresh(), (int) $actor->id);
        });

        $fresh = $payout->fresh(['items']);
        $isRemittance = $fresh?->items->contains(
            fn ($item) => (string) ($item->line_type ?? '') === self::LINE_REMITTANCE
        );
        if ($isRemittance) {
            \App\Jobs\SendLandlordRemittanceAdviceJob::dispatch((int) $payout->id);
        }
    }

    /**
     * @return array{rent: float, garbage: float, water: float, other: float, total: float}
     */
    public function collectedByTypeForProperty(int $propertyId, Carbon $start, Carbon $end): array
    {
        $row = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pu.property_id', $propertyId)
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$start, $end])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_RENT."' THEN a.amount ELSE 0 END), 0) as rent,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_GARBAGE."' THEN a.amount ELSE 0 END), 0) as garbage,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_WATER."' THEN a.amount ELSE 0 END), 0) as water,
                COALESCE(SUM(CASE WHEN i.invoice_type NOT IN ('".PmInvoice::TYPE_RENT."', '".PmInvoice::TYPE_GARBAGE."', '".PmInvoice::TYPE_WATER."') THEN a.amount ELSE 0 END), 0) as other,
                COALESCE(SUM(a.amount), 0) as total
            ")
            ->first();

        return [
            'rent' => round((float) ($row->rent ?? 0), 2),
            'garbage' => round((float) ($row->garbage ?? 0), 2),
            'water' => round((float) ($row->water ?? 0), 2),
            'other' => round((float) ($row->other ?? 0), 2),
            'total' => round((float) ($row->total ?? 0), 2),
        ];
    }

    /**
     * Passion-style unit matrix: every unit with rent/month, Bal B/F, monthly charges, and paid (Rent/Garbage/Water).
     *
     * @return list<array<string, mixed>>
     */
    public function unitSettlementLines(int $propertyId, Carbon $start, Carbon $end): array
    {
        $units = PropertyUnit::query()
            ->where('property_id', $propertyId)
            ->orderBy('label')
            ->get(['id', 'label', 'status', 'rent_amount']);

        if ($units->isEmpty()) {
            return [];
        }

        $unitIds = $units->pluck('id')->all();

        $tenantsByUnit = DB::table('pm_lease_unit as lu')
            ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
            ->join('pm_tenants as t', 't.id', '=', 'l.pm_tenant_id')
            ->whereIn('lu.property_unit_id', $unitIds)
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->orderByDesc('l.id')
            ->get(['lu.property_unit_id', 't.name', 'l.monthly_rent'])
            ->groupBy('property_unit_id')
            ->map(fn ($rows) => $rows->first());

        $openingByUnit = DB::table('pm_invoices as i')
            ->whereIn('i.property_unit_id', $unitIds)
            ->tap(fn ($q) => PmInvoice::applyBillableArConstraints($q, 'i'))
            ->whereDate('i.issue_date', '<', $start->toDateString())
            ->where('i.balance_due', '>', 0)
            ->groupBy('i.property_unit_id')
            ->selectRaw('i.property_unit_id as unit_id')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_RENT."' THEN i.balance_due ELSE 0 END), 0) as rent_bf,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_GARBAGE."' THEN i.balance_due ELSE 0 END), 0) as garbage_bf,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_WATER."' THEN i.balance_due ELSE 0 END), 0) as water_bf
            ")
            ->get()
            ->keyBy('unit_id');

        $billedByUnit = DB::table('pm_invoices as i')
            ->whereIn('i.property_unit_id', $unitIds)
            ->tap(fn ($q) => PmInvoice::applyBillableArConstraints($q, 'i'))
            ->whereBetween('i.issue_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('i.property_unit_id')
            ->selectRaw('i.property_unit_id as unit_id')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_RENT."' THEN i.amount ELSE 0 END), 0) as rent_billed,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_GARBAGE."' THEN i.amount ELSE 0 END), 0) as garbage_billed,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_WATER."' THEN i.amount ELSE 0 END), 0) as water_billed
            ")
            ->get()
            ->keyBy('unit_id');

        $paidByUnit = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->whereIn('i.property_unit_id', $unitIds)
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$start, $end])
            ->groupBy('i.property_unit_id')
            ->selectRaw('i.property_unit_id as unit_id')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_RENT."' THEN a.amount ELSE 0 END), 0) as rent_received,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_GARBAGE."' THEN a.amount ELSE 0 END), 0) as garbage_received,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_WATER."' THEN a.amount ELSE 0 END), 0) as water_received,
                COALESCE(SUM(a.amount), 0) as total_received
            ")
            ->get()
            ->keyBy('unit_id');

        return $units->map(function (PropertyUnit $unit) use ($tenantsByUnit, $openingByUnit, $billedByUnit, $paidByUnit) {
            $tenantRow = $tenantsByUnit->get($unit->id);
            $opening = $openingByUnit->get($unit->id);
            $billed = $billedByUnit->get($unit->id);
            $paid = $paidByUnit->get($unit->id);

            $rentBf = round((float) ($opening->rent_bf ?? 0), 2);
            $garbageBf = round((float) ($opening->garbage_bf ?? 0), 2);
            $waterBf = round((float) ($opening->water_bf ?? 0), 2);
            $rentBilled = round((float) ($billed->rent_billed ?? 0), 2);
            $garbageBilled = round((float) ($billed->garbage_billed ?? 0), 2);
            $waterBilled = round((float) ($billed->water_billed ?? 0), 2);
            $rentReceived = round((float) ($paid->rent_received ?? 0), 2);
            $garbageReceived = round((float) ($paid->garbage_received ?? 0), 2);
            $waterReceived = round((float) ($paid->water_received ?? 0), 2);
            $totalReceived = round((float) ($paid->total_received ?? ($rentReceived + $garbageReceived + $waterReceived)), 2);

            $status = (string) $unit->status;
            $tenantName = trim((string) ($tenantRow->name ?? ''));
            if ($tenantName === '') {
                $tenantName = $status === PropertyUnit::STATUS_OWNER_OCCUPIED
                    ? 'Owner (LLD)'
                    : ($status === PropertyUnit::STATUS_VACANT ? 'VACANT' : '—');
            }

            $rentPerMonth = (float) ($tenantRow->monthly_rent ?? 0);
            if ($rentPerMonth <= 0) {
                $rentPerMonth = (float) ($unit->rent_amount ?? 0);
            }

            return [
                'unit_id' => (int) $unit->id,
                'unit_label' => (string) $unit->label,
                'unit_status' => $status,
                'tenant_name' => $tenantName,
                'rent_per_month' => round($rentPerMonth, 2),
                'rent_bf' => $rentBf,
                'garbage_bf' => $garbageBf,
                'water_bf' => $waterBf,
                'rent_billed' => $rentBilled,
                'garbage_billed' => $garbageBilled,
                'water_billed' => $waterBilled,
                'rent_received' => $rentReceived,
                'garbage_received' => $garbageReceived,
                'water_received' => $waterReceived,
                'total_received' => $totalReceived,
                'rent_closing' => round(max(0.0, $rentBf + $rentBilled - $rentReceived), 2),
                'garbage_closing' => round(max(0.0, $garbageBf + $garbageBilled - $garbageReceived), 2),
                'water_closing' => round(max(0.0, $waterBf + $waterBilled - $waterReceived), 2),
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $unitLines
     * @return array<string, float>
     */
    private function sumUnitLines(array $unitLines): array
    {
        $keys = [
            'rent_per_month', 'rent_bf', 'garbage_bf', 'water_bf',
            'rent_billed', 'garbage_billed', 'water_billed',
            'rent_received', 'garbage_received', 'water_received', 'total_received',
            'rent_closing', 'garbage_closing', 'water_closing',
        ];
        $totals = array_fill_keys($keys, 0.0);
        foreach ($unitLines as $line) {
            foreach ($keys as $key) {
                $totals[$key] = round($totals[$key] + (float) ($line[$key] ?? 0), 2);
            }
        }

        return $totals;
    }

    /**
     * Security deposits / period credits treated as statement additions.
     *
     * @return list<array{description: string, amount: float, occurred_at: string|null}>
     */
    private function periodAdditions(int $propertyId, Carbon $start, Carbon $end): array
    {
        if (! Schema::hasTable('pm_tenant_deposits')) {
            return [];
        }

        $tenantIds = DB::table('pm_lease_unit as lu')
            ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
            ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
            ->where('pu.property_id', $propertyId)
            ->pluck('l.pm_tenant_id')
            ->merge(
                DB::table('pm_invoices as i')
                    ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
                    ->where('u.property_id', $propertyId)
                    ->pluck('i.pm_tenant_id')
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tenantIds === []) {
            return [];
        }

        $unitByTenant = DB::table('pm_lease_unit as lu')
            ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
            ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
            ->where('pu.property_id', $propertyId)
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->whereIn('l.pm_tenant_id', $tenantIds)
            ->orderByDesc('l.id')
            ->get(['l.pm_tenant_id', 'pu.label'])
            ->groupBy('pm_tenant_id')
            ->map(fn ($rows) => (string) ($rows->first()->label ?? ''));

        return DB::table('pm_tenant_deposits as d')
            ->join('pm_tenants as t', 't.id', '=', 'd.tenant_id')
            ->whereIn('d.tenant_id', $tenantIds)
            ->whereBetween('d.created_at', [$start, $end])
            ->where('d.amount', '>', 0)
            ->orderBy('d.created_at')
            ->get(['d.amount', 'd.created_at', 'd.tenant_id', 't.name as tenant_name'])
            ->map(function ($row) use ($unitByTenant) {
                $tenant = trim((string) ($row->tenant_name ?? ''));
                $unit = trim((string) ($unitByTenant->get((int) $row->tenant_id) ?? ''));
                $label = 'SECURITY DEPOSIT';
                if ($tenant !== '') {
                    $label .= ' — '.$tenant;
                }
                if ($unit !== '') {
                    $label .= ' ('.$unit.')';
                }

                return [
                    'description' => $label,
                    'amount' => round((float) $row->amount, 2),
                    'occurred_at' => $row->created_at
                        ? Carbon::parse($row->created_at)->format('Y-m-d')
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, amount: float, description: string, agreed_pay_date: string|null, paid_at: string|null}>
     */
    private function openAdvances(int $landlordId, int $propertyId): array
    {
        return PmLandlordPayoutItem::query()
            ->with('payout')
            ->where('landlord_id', $landlordId)
            ->where('property_id', $propertyId)
            ->where('line_type', self::LINE_ADVANCE)
            ->where('advance_status', LandlordAdvanceService::STATUS_OPEN)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PmLandlordPayoutItem $item) => [
                'id' => (int) $item->id,
                'amount' => round((float) $item->amount, 2),
                'description' => (string) ($item->description ?? 'Advance payment'),
                'agreed_pay_date' => $item->agreed_pay_date?->format('Y-m-d'),
                'paid_at' => $item->payout?->paid_at?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{line_type: string, description: string, amount: float, occurred_at: string|null}>
     */
    private function periodDeductions(int $landlordId, int $propertyId, Carbon $start, Carbon $end): array
    {
        return PmLandlordLedgerEntry::query()
            ->where('user_id', $landlordId)
            ->where('property_id', $propertyId)
            ->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('occurred_at')
            ->get(['amount', 'description', 'reference_type', 'occurred_at'])
            ->map(function (PmLandlordLedgerEntry $entry) {
                $refType = (string) ($entry->reference_type ?? '');
                $lineType = match (true) {
                    $refType === 'pm_tenant_deposit' => self::LINE_DEPOSIT_REFUND,
                    str_contains(strtolower((string) $entry->description), 'deposit refund') => self::LINE_DEPOSIT_REFUND,
                    str_contains(strtolower((string) $entry->description), 'kra'),
                    str_contains(strtolower((string) $entry->description), 'tax') => self::LINE_TAX,
                    default => self::LINE_OTHER,
                };

                return [
                    'line_type' => $lineType,
                    'description' => (string) ($entry->description ?? 'Deduction'),
                    'amount' => round((float) $entry->amount, 2),
                    'occurred_at' => optional($entry->occurred_at)->format('Y-m-d'),
                ];
            })
            ->values()
            ->all();
    }

    private function ledgerNetBalance(int $landlordId, int $propertyId, ?Carbon $before = null): float
    {
        $query = PmLandlordLedgerEntry::query()
            ->where('user_id', $landlordId)
            ->where('property_id', $propertyId);

        if ($before !== null) {
            $query->where('occurred_at', '<', $before);
        }

        return round((float) $query
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = '".PmLandlordLedgerEntry::DIRECTION_CREDIT."' THEN amount ELSE -amount END), 0) as bal")
            ->value('bal'), 2);
    }

    private function ledgerSum(
        int $landlordId,
        int $propertyId,
        string $direction,
        Carbon $start,
        Carbon $end,
    ): float {
        return round((float) PmLandlordLedgerEntry::query()
            ->where('user_id', $landlordId)
            ->where('property_id', $propertyId)
            ->where('direction', $direction)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount'), 2);
    }
}
