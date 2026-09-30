<?php

namespace App\Services\Property;

use App\Models\PmEzenPaymentVoucher;
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

    /** @var array<string, array{properties: array<int, int>}>|null */
    private ?array $landlordNameIndex = null;

    /** @var array<string, array<int, float>> */
    private array $collectionWeightCache = [];

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

        $periodRemitted = $this->remittedTotal([$propertyId], $landlordId, $periodStart, $periodEnd);
        $balanceBf = $this->ledgerNetBalance($landlordId, $propertyId, $periodStart);
        $periodCredits = $this->ledgerSum($landlordId, $propertyId, PmLandlordLedgerEntry::DIRECTION_CREDIT, $periodStart, $periodEnd);
        $periodDebits = round($this->ledgerSum($landlordId, $propertyId, PmLandlordLedgerEntry::DIRECTION_DEBIT, $periodStart, $periodEnd) + $periodRemitted, 2);
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
                'total_billed' => round($rentBilled + $garbageBilled + $waterBilled, 2),
                'total_received' => $totalReceived,
                'rent_closing' => round(max(0.0, $rentBf + $rentBilled - $rentReceived), 2),
                'garbage_closing' => round(max(0.0, $garbageBf + $garbageBilled - $garbageReceived), 2),
                'water_closing' => round(max(0.0, $waterBf + $waterBilled - $waterReceived), 2),
            ];
        })->values()->all();
    }

    /**
     * Property account statement for one period (this property only).
     * Uses a linked landlord when available so remittance/net due match the owner statement.
     *
     * @return array<string, mixed>
     */
    public function buildPropertyPeriodStatement(int $propertyId, Carbon $periodStart, Carbon $periodEnd, ?int $landlordId = null): array
    {
        if ($landlordId !== null && $landlordId > 0) {
            try {
                return $this->buildSettlement($propertyId, $landlordId, $periodStart, $periodEnd);
            } catch (InvalidArgumentException) {
                // Fall through to unit-only statement.
            }
        }

        $property = Property::query()->find($propertyId);
        $unitLines = $this->unitSettlementLines($propertyId, $periodStart, $periodEnd);
        $unitTotals = $this->sumUnitLines($unitLines);
        $collected = $this->collectedByTypeForProperty($propertyId, $periodStart, $periodEnd);
        $additions = $this->periodAdditions($propertyId, $periodStart, $periodEnd);
        $additionsTotal = round(collect($additions)->sum('amount'), 2);
        $sameMonth = $periodStart->format('Y-m') === $periodEnd->format('Y-m');

        return [
            'property_id' => $propertyId,
            'landlord_id' => 0,
            'property_name' => (string) ($property?->name ?? ''),
            'landlord_name' => '—',
            'ownership_percent' => 0.0,
            'commission_percent' => $this->commission->commissionPercentForProperty($propertyId),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_label' => $sameMonth
                ? $periodStart->format('F').' - '.$periodStart->format('Y')
                : $periodStart->format('M Y').' – '.$periodEnd->format('M Y'),
            'period_range_label' => $periodStart->format('d/m/Y').' - '.$periodEnd->format('d/m/Y'),
            'period_month' => $periodStart->format('Y-m'),
            'unit_stats' => PropertyUnitOccupancyStats::forProperty($propertyId),
            'collected' => $collected,
            'owner_collected' => $collected,
            'rent_received' => round($collected['rent'], 2),
            'utility_received' => round($collected['garbage'] + $collected['water'], 2),
            'other_expenses' => round(max(0.0, $collected['other']), 2),
            'management_fee' => 0.0,
            'net_collected' => round($collected['total'], 2),
            'balance_brought_forward' => 0.0,
            'period_credits' => $additionsTotal,
            'period_debits' => 0.0,
            'additions' => $additions,
            'additions_total' => $additionsTotal,
            'deductions' => [],
            'deductions_total' => 0.0,
            'open_advances' => [],
            'open_advances_total' => 0.0,
            'agreed_pay_day' => null,
            'agreed_pay_notes' => '',
            'next_agreed_pay_date' => null,
            'closing_balance' => round((float) ($unitTotals['rent_closing'] ?? 0) + (float) ($unitTotals['garbage_closing'] ?? 0) + (float) ($unitTotals['water_closing'] ?? 0), 2),
            'net_amount_due' => 0.0,
            'unit_lines' => $unitLines,
            'unit_totals' => $unitTotals,
        ];
    }

    /**
     * Yearly property statement plus month totals and per-unit monthly billed/received.
     *
     * @return array{
     *     fy: int,
     *     open_month: string,
     *     year_settlement: array<string, mixed>,
     *     month_settlement: array<string, mixed>|null,
     *     monthly: list<array<string, mixed>>,
     *     unit_months: list<array<string, mixed>>,
     *     month_keys: list<string>
     * }
     */
    public function buildPropertyStatementHub(int $propertyId, int $fy, ?string $openMonthYm, ?int $landlordId = null): array
    {
        $yearStart = Carbon::create($fy, 1, 1)->startOfDay();
        $yearEnd = $yearStart->copy()->endOfYear();
        $openMonth = is_string($openMonthYm) && preg_match('/^\d{4}-\d{2}$/', $openMonthYm) === 1
            ? $openMonthYm
            : '';
        if ($openMonth !== '' && (int) substr($openMonth, 0, 4) !== $fy) {
            $openMonth = '';
        }

        $yearSettlement = $this->buildPropertyPeriodStatement($propertyId, $yearStart, $yearEnd, $landlordId);
        $yearSettlement['period_label'] = 'FY '.$fy;
        $yearSettlement['period_range_label'] = $yearStart->format('d/m/Y').' - '.$yearEnd->format('d/m/Y');
        $monthly = $this->monthlyActivityForProperty($propertyId, $fy);
        $unitMonths = $this->unitMonthlyActivity($propertyId, $fy, $yearSettlement['unit_lines'] ?? []);

        $monthSettlement = null;
        if ($openMonth !== '') {
            $monthStart = Carbon::createFromFormat('Y-m', $openMonth)->startOfMonth();
            $monthSettlement = $this->buildPropertyPeriodStatement(
                $propertyId,
                $monthStart,
                $monthStart->copy()->endOfMonth(),
                $landlordId,
            );
        }

        $monthKeys = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthKeys[] = Carbon::create($fy, $month, 1)->format('Y-m');
        }

        return [
            'fy' => $fy,
            'open_month' => $openMonth,
            'year_settlement' => $yearSettlement,
            'month_settlement' => $monthSettlement,
            'monthly' => $monthly,
            'unit_months' => $unitMonths,
            'month_keys' => $monthKeys,
        ];
    }

    /**
     * @return list<array{month: string, month_label: string, billed: float, received: float}>
     */
    public function monthlyActivityForProperty(int $propertyId, int $fy): array
    {
        $yearStart = Carbon::create($fy, 1, 1)->startOfDay();
        $yearEnd = $yearStart->copy()->endOfYear();

        $billed = DB::table('pm_invoices as i')
            ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
            ->where('u.property_id', $propertyId)
            ->tap(fn ($q) => PmInvoice::applyBillableArConstraints($q, 'i'))
            ->whereBetween('i.issue_date', [$yearStart->toDateString(), $yearEnd->toDateString()])
            ->groupByRaw("DATE_FORMAT(i.issue_date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(i.issue_date, '%Y-%m') as ym, COALESCE(SUM(i.amount), 0) as billed")
            ->pluck('billed', 'ym');

        $received = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
            ->where('u.property_id', $propertyId)
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$yearStart, $yearEnd])
            ->groupByRaw("DATE_FORMAT(pay.paid_at, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(pay.paid_at, '%Y-%m') as ym, COALESCE(SUM(a.amount), 0) as received")
            ->pluck('received', 'ym');

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($fy, $month, 1)->startOfMonth();
            $ym = $start->format('Y-m');
            $months[] = [
                'month' => $ym,
                'month_label' => $start->format('M Y'),
                'billed' => round((float) ($billed[$ym] ?? 0), 2),
                'received' => round((float) ($received[$ym] ?? 0), 2),
            ];
        }

        return $months;
    }

    /**
     * @param  list<array<string, mixed>>  $unitLines
     * @return list<array<string, mixed>>
     */
    public function unitMonthlyActivity(int $propertyId, int $fy, array $unitLines): array
    {
        $yearStart = Carbon::create($fy, 1, 1)->startOfDay();
        $yearEnd = $yearStart->copy()->endOfYear();

        $billedRows = DB::table('pm_invoices as i')
            ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
            ->where('u.property_id', $propertyId)
            ->tap(fn ($q) => PmInvoice::applyBillableArConstraints($q, 'i'))
            ->whereBetween('i.issue_date', [$yearStart->toDateString(), $yearEnd->toDateString()])
            ->groupBy('i.property_unit_id')
            ->groupByRaw("DATE_FORMAT(i.issue_date, '%Y-%m')")
            ->selectRaw("i.property_unit_id as unit_id, DATE_FORMAT(i.issue_date, '%Y-%m') as ym, COALESCE(SUM(i.amount), 0) as billed")
            ->get();

        $receivedRows = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
            ->where('u.property_id', $propertyId)
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$yearStart, $yearEnd])
            ->groupBy('i.property_unit_id')
            ->groupByRaw("DATE_FORMAT(pay.paid_at, '%Y-%m')")
            ->selectRaw("i.property_unit_id as unit_id, DATE_FORMAT(pay.paid_at, '%Y-%m') as ym, COALESCE(SUM(a.amount), 0) as received")
            ->get();

        $billedMap = [];
        foreach ($billedRows as $row) {
            $billedMap[(int) $row->unit_id][(string) $row->ym] = round((float) $row->billed, 2);
        }
        $receivedMap = [];
        foreach ($receivedRows as $row) {
            $receivedMap[(int) $row->unit_id][(string) $row->ym] = round((float) $row->received, 2);
        }

        return collect($unitLines)->map(function (array $line) use ($billedMap, $receivedMap, $fy) {
            $unitId = (int) ($line['unit_id'] ?? 0);
            $months = [];
            $yearBilled = 0.0;
            $yearReceived = 0.0;
            for ($month = 1; $month <= 12; $month++) {
                $ym = Carbon::create($fy, $month, 1)->format('Y-m');
                $billed = (float) ($billedMap[$unitId][$ym] ?? 0);
                $received = (float) ($receivedMap[$unitId][$ym] ?? 0);
                $yearBilled += $billed;
                $yearReceived += $received;
                $months[$ym] = [
                    'billed' => $billed,
                    'received' => $received,
                ];
            }

            return [
                'unit_id' => $unitId,
                'unit_label' => (string) ($line['unit_label'] ?? '—'),
                'tenant_name' => (string) ($line['tenant_name'] ?? '—'),
                'rent_per_month' => (float) ($line['rent_per_month'] ?? 0),
                'months' => $months,
                'year_billed' => round($yearBilled, 2),
                'year_received' => round($yearReceived, 2),
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $unitLines
     * @return array<string, float>
     */
    public function sumUnitLines(array $unitLines): array
    {
        $keys = [
            'rent_per_month', 'rent_bf', 'garbage_bf', 'water_bf',
            'rent_billed', 'garbage_billed', 'water_billed', 'total_billed',
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
     * Imported landlord payouts from the voucher listing, excluding any remittance already posted as a ledger debit.
     *
     * @param  list<int>  $propertyIds
     * @return list<array{landlord_id: int, property_id: int, amount: float, description: string, occurred_at: string|null, period_month: string, marker: string, source_id: string}>
     */
    public function landlordRemittances(array $propertyIds, int $landlordId, ?Carbon $from, ?Carbon $until): array
    {
        $propertyIds = array_values(array_unique(array_filter(array_map('intval', $propertyIds), fn (int $id) => $id > 0)));
        if ($landlordId <= 0 && $propertyIds === []) {
            return [];
        }
        $rows = [];
        $coveredMarkers = [];

        if (Schema::hasTable('pm_landlord_payout_items') && Schema::hasTable('pm_landlord_payouts')) {
            $payoutRows = DB::table('pm_landlord_payout_items as item')
                ->join('pm_landlord_payouts as po', 'po.id', '=', 'item.payout_id')
                ->when($landlordId > 0, fn ($query) => $query->where('item.landlord_id', $landlordId))
                ->when($propertyIds !== [], fn ($query) => $query->whereIn('item.property_id', $propertyIds))
                ->where('item.line_type', self::LINE_REMITTANCE)
                ->where('item.amount', '>', 0)
                ->get([
                    'item.landlord_id',
                    'item.property_id',
                    'item.amount',
                    'item.description',
                    'item.period_month',
                    'item.payout_id',
                    'po.paid_at',
                    'po.status',
                ]);

            foreach ($payoutRows as $row) {
                $status = strtolower(trim((string) ($row->status ?? '')));
                if ($status !== '' && ! in_array($status, ['paid', 'completed', 'approved'], true) && empty($row->paid_at)) {
                    continue;
                }
                if (! $this->remittanceFallsInRange($row->period_month ?? null, $row->paid_at ?? null, $from, $until)) {
                    continue;
                }

                $marker = $this->ezenVoucherMarker((string) ($row->description ?? ''));
                if ($marker !== '') {
                    $coveredMarkers[$marker] = true;
                }

                $rows[] = $this->remittanceRow(
                    (int) $row->landlord_id,
                    (int) $row->property_id,
                    (float) $row->amount,
                    (string) ($row->description ?: 'Landlord remittance'),
                    $row->paid_at ?? null,
                    (string) ($row->period_month ?? ''),
                    $marker,
                    'PAY-'.(int) $row->payout_id,
                );
            }
        }

        if (Schema::hasTable('pm_ezen_payment_vouchers')) {
            $voucherRows = DB::table('pm_ezen_payment_vouchers')
                ->when($landlordId > 0, fn ($query) => $query->where('landlord_id', $landlordId))
                ->when($propertyIds !== [], fn ($query) => $query->whereIn('property_id', $propertyIds))
                ->where('category', PmEzenPaymentVoucher::CATEGORY_REMITTANCE)
                ->where('amount', '>', 0)
                ->get([
                    'id',
                    'landlord_id',
                    'property_id',
                    'amount',
                    'particulars',
                    'ezen_voucher_no',
                    'period_month',
                    'txn_date',
                ]);

            foreach ($voucherRows as $row) {
                if ((int) ($row->property_id ?? 0) <= 0) {
                    continue;
                }
                $marker = $this->ezenVoucherMarker((string) ($row->ezen_voucher_no ?? ''));
                if ($marker !== '' && isset($coveredMarkers[$marker])) {
                    continue;
                }
                if (! $this->remittanceFallsInRange($row->period_month ?? null, $row->txn_date ?? null, $from, $until)) {
                    continue;
                }
                if ($marker !== '') {
                    $coveredMarkers[$marker] = true;
                }

                $particulars = trim((string) ($row->particulars ?? ''));
                $rows[] = $this->remittanceRow(
                    (int) $row->landlord_id,
                    (int) $row->property_id,
                    (float) $row->amount,
                    $particulars !== '' ? $particulars : 'Landlord remittance '.$row->ezen_voucher_no,
                    $row->txn_date ?? null,
                    (string) ($row->period_month ?? ''),
                    $marker,
                    (string) ($row->ezen_voucher_no ?: 'VCH-'.$row->id),
                );
            }

            $this->appendUnmatchedRemittances($rows, $coveredMarkers, $propertyIds, $landlordId, $from, $until);
        }

        if ($rows === []) {
            return [];
        }

        $debitDescriptions = DB::table('pm_landlord_ledger_entries')
            ->when($landlordId > 0, fn ($query) => $query->where('user_id', $landlordId))
            ->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)
            ->whereNull('reversed_at')
            ->when($propertyIds !== [], fn ($query) => $query->whereIn('property_id', $propertyIds))
            ->pluck('description')
            ->map(fn ($description) => (string) $description)
            ->all();

        if ($debitDescriptions === []) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($debitDescriptions): bool {
            $marker = (string) ($row['marker'] ?? '');
            if ($marker === '') {
                return true;
            }
            foreach ($debitDescriptions as $description) {
                if (str_contains($description, $marker)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Remittances whose payee was not linked to a property still belong on the snapshot
     * when one landlord name wins the match. Cheques for the same person are split
     * across that person's properties by what each building collected that month.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, true>  $coveredMarkers
     * @param  list<int>  $propertyIds
     */
    private function appendUnmatchedRemittances(array &$rows, array &$coveredMarkers, array $propertyIds, int $landlordId, ?Carbon $from, ?Carbon $until): void
    {
        $links = DB::table('property_landlord as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->when($landlordId > 0, fn ($query) => $query->where('u.id', $landlordId))
            ->when($propertyIds !== [], fn ($query) => $query->whereIn('pl.property_id', $propertyIds))
            ->get(['u.id as user_id', 'u.name', 'pl.property_id']);

        $landlords = [];
        foreach ($links as $link) {
            $id = (int) $link->user_id;
            $landlords[$id]['name'] = (string) $link->name;
            $landlords[$id]['properties'][(int) $link->property_id] = true;
        }
        if ($landlords === []) {
            return;
        }

        $voucherRows = DB::table('pm_ezen_payment_vouchers')
            ->where('category', PmEzenPaymentVoucher::CATEGORY_REMITTANCE)
            ->where('amount', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('landlord_id')->orWhere('landlord_id', 0)->orWhereNull('property_id');
            })
            ->get([
                'id',
                'landlord_id',
                'property_id',
                'amount',
                'particulars',
                'paid_to',
                'payee_name',
                'property_code',
                'ezen_voucher_no',
                'period_month',
                'txn_date',
            ]);

        if ($voucherRows->isEmpty()) {
            return;
        }

        $codes = app(PassionPropertyCodeResolver::class);
        $propertyMeta = DB::table('properties')
            ->whereIn('id', collect($landlords)->flatMap(fn (array $info) => array_keys($info['properties']))->unique()->all())
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        foreach ($voucherRows as $row) {
            $marker = $this->ezenVoucherMarker((string) ($row->ezen_voucher_no ?? ''));
            if ($marker !== '' && isset($coveredMarkers[$marker])) {
                continue;
            }
            if (! $this->remittanceFallsInRange($row->period_month ?? null, $row->txn_date ?? null, $from, $until)) {
                continue;
            }

            $explicitLandlord = (int) ($row->landlord_id ?? 0);
            $payee = trim((string) ($row->paid_to ?: $row->payee_name));
            $matchedLandlord = $explicitLandlord > 0 && isset($landlords[$explicitLandlord]) ? $explicitLandlord : 0;
            $strippedKey = $matchedLandlord > 0
                ? $this->strippedLandlordKey((string) ($landlords[$matchedLandlord]['name'] ?? ''))
                : $this->uniquePayeeLandlordName($payee, $codes);
            if ($matchedLandlord === 0) {
                if ($strippedKey === '') {
                    continue;
                }
                foreach ($landlords as $id => $info) {
                    if ($this->strippedLandlordKey((string) $info['name']) === $strippedKey) {
                        $matchedLandlord = (int) $id;
                        break;
                    }
                }
                if ($matchedLandlord === 0) {
                    continue;
                }
            }

            $owned = array_keys($landlords[$matchedLandlord]['properties'] ?? []);
            $siblingMap = $this->propertiesOwnedByName($strippedKey);
            if ($siblingMap === []) {
                $siblingMap = [];
                foreach ($owned as $pid) {
                    $siblingMap[(int) $pid] = $matchedLandlord;
                }
            }
            $siblingIds = array_keys($siblingMap);
            $propertyId = (int) ($row->property_id ?? 0);
            if ($propertyId > 0 && ! in_array($propertyId, $siblingIds, true) && ! in_array($propertyId, $owned, true)) {
                $propertyId = 0;
            }
            if ($propertyId <= 0) {
                $propertyId = $this->propertyIdFromVoucherText(
                    $siblingIds,
                    $propertyMeta,
                    trim((string) ($row->paid_to.' '.$row->payee_name.' '.$row->particulars)),
                    (string) ($row->property_code ?? ''),
                );
            }

            $slices = $propertyId > 0
                ? [$propertyId => round((float) $row->amount, 2)]
                : $this->allocateRemittanceAmount((float) $row->amount, $siblingIds, (string) ($row->period_month ?? ''));
            $scope = $propertyIds !== [] ? $propertyIds : $siblingIds;
            $particulars = trim((string) ($row->particulars ?? ''));
            $added = false;
            foreach ($slices as $slicePropertyId => $sliceAmount) {
                if ($sliceAmount <= 0.009 || ! in_array((int) $slicePropertyId, $scope, true)) {
                    continue;
                }
                $rows[] = $this->remittanceRow(
                    (int) ($siblingMap[(int) $slicePropertyId] ?? $matchedLandlord),
                    (int) $slicePropertyId,
                    (float) $sliceAmount,
                    $particulars !== '' ? $particulars : 'Landlord remittance '.$row->ezen_voucher_no,
                    $row->txn_date ?? null,
                    (string) ($row->period_month ?? ''),
                    $marker,
                    (string) ($row->ezen_voucher_no ?: 'VCH-'.$row->id),
                );
                $added = true;
            }
            if ($added && $marker !== '') {
                $coveredMarkers[$marker] = true;
            }
        }
    }

    /**
     * Titles and bank-account words removed, so "DR. MURAGE" and "ITIBI INVESTMENT ACCOUNT" can match.
     *
     * @return list<string>
     */
    private function significantNameTokens(string $value): array
    {
        $stop = ['MR', 'MRS', 'MISS', 'DR', 'AND', 'THE', 'INVESTMENT', 'INVESTMENTS', 'ACCOUNT', 'ACCOUNTS', 'RENTAL', 'HOUSE', 'APARTMENT', 'APARTMENTS', 'COMPLEX'];
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9 ]/', ' ', $value) ?? $value;
        $out = [];
        foreach (preg_split('/\s+/', trim($value)) ?: [] as $part) {
            if (strlen($part) >= 4 && ! in_array($part, $stop, true)) {
                $out[] = $part;
            }
        }

        return array_values(array_unique($out));
    }

    private function strippedLandlordKey(string $name): string
    {
        return implode(' ', $this->significantNameTokens($name));
    }

    /**
     * One cheque is attached only when a single landlord name wins.
     * A shared surname such as Kamau or Githua is not enough on its own.
     */
    private function uniquePayeeLandlordName(string $payee, PassionPropertyCodeResolver $codes): string
    {
        $bestKey = '';
        $best = 0;
        $second = 0;
        foreach (array_keys($this->landlordNameIndex()) as $key) {
            $strength = $this->payeeMatchStrength($payee, $key, $codes);
            if ($strength > $best) {
                $second = $best;
                $best = $strength;
                $bestKey = $key;
            } elseif ($strength > $second) {
                $second = $strength;
            }
        }
        if ($best < 200 || $second >= $best) {
            return '';
        }
        if ($second >= 200 && $best < $second + 50) {
            return '';
        }

        return $bestKey;
    }

    private function payeeMatchStrength(string $payee, string $landlordKey, PassionPropertyCodeResolver $codes): int
    {
        $payeeTokens = $this->significantNameTokens($payee);
        $nameTokens = $this->significantNameTokens($landlordKey);
        if ($payeeTokens === [] || $nameTokens === []) {
            return 0;
        }

        $score = $codes->scoreNameMatch(implode(' ', $payeeTokens), implode(' ', $nameTokens));
        if ($score >= 300) {
            return $score;
        }

        $overlap = array_intersect($payeeTokens, $nameTokens);

        return count($overlap) >= 2 ? 200 + count($overlap) : 0;
    }

    /**
     * @return array<string, array{properties: array<int, int>}>
     */
    private function landlordNameIndex(): array
    {
        if ($this->landlordNameIndex !== null) {
            return $this->landlordNameIndex;
        }

        $index = [];
        $rows = DB::table('property_landlord as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->get(['u.id as user_id', 'u.name', 'pl.property_id']);
        foreach ($rows as $row) {
            $key = $this->strippedLandlordKey((string) $row->name);
            if ($key === '') {
                continue;
            }
            $index[$key]['properties'][(int) $row->property_id] = (int) $row->user_id;
        }

        return $this->landlordNameIndex = $index;
    }

    /**
     * @return array<int, int> property id => owning user id
     */
    private function propertiesOwnedByName(string $strippedKey): array
    {
        if ($strippedKey === '') {
            return [];
        }

        return $this->landlordNameIndex()[$strippedKey]['properties'] ?? [];
    }

    /**
     * @param  list<int>  $propertyIds
     * @return array<int, float>
     */
    private function allocateRemittanceAmount(float $amount, array $propertyIds, string $periodMonth): array
    {
        $propertyIds = array_values(array_unique(array_filter(array_map('intval', $propertyIds), fn (int $id) => $id > 0)));
        if ($amount <= 0 || $propertyIds === []) {
            return [];
        }
        if (count($propertyIds) === 1) {
            return [$propertyIds[0] => round($amount, 2)];
        }

        $weights = $this->collectionWeights($propertyIds, $periodMonth);
        $weightTotal = array_sum($weights);
        $allocated = [];
        $running = 0.0;
        $last = $propertyIds[array_key_last($propertyIds)];
        foreach ($propertyIds as $propertyId) {
            if ($propertyId === $last) {
                $allocated[$propertyId] = round($amount - $running, 2);
                break;
            }
            $share = $weightTotal > 0
                ? round($amount * (($weights[$propertyId] ?? 0.0) / $weightTotal), 2)
                : round($amount / count($propertyIds), 2);
            $allocated[$propertyId] = $share;
            $running += $share;
        }

        return $allocated;
    }

    /**
     * @param  list<int>  $propertyIds
     * @return array<int, float>
     */
    private function collectionWeights(array $propertyIds, string $periodMonth): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', $periodMonth) !== 1) {
            return [];
        }

        $cacheKey = $periodMonth.'|'.implode(',', $propertyIds);
        if (isset($this->collectionWeightCache[$cacheKey])) {
            return $this->collectionWeightCache[$cacheKey];
        }

        return $this->collectionWeightCache[$cacheKey] = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->whereIn('pu.property_id', $propertyIds)
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->where('i.billing_period', $periodMonth)
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount), 0) as total')
            ->pluck('total', 'property_id')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    /**
     * @param  list<int>  $ownedIds
     * @param  \Illuminate\Support\Collection<int, object>  $propertyMeta
     */
    private function propertyIdFromVoucherText(array $ownedIds, $propertyMeta, string $text, string $propertyCode): int
    {
        $code = strtoupper(trim($propertyCode));
        $textUpper = strtoupper($text);
        $hits = [];
        foreach ($ownedIds as $pid) {
            $meta = $propertyMeta->get($pid);
            if ($meta === null) {
                continue;
            }
            $propertyCodeOnFile = strtoupper(trim((string) ($meta->code ?? '')));
            if ($propertyCodeOnFile !== '' && ($code === $propertyCodeOnFile || str_contains($textUpper, $propertyCodeOnFile))) {
                $hits[$pid] = true;
            }
        }

        return count($hits) === 1 ? (int) array_key_first($hits) : 0;
    }

    /**
     * @param  list<int>  $propertyIds
     */
    public function remittedTotal(array $propertyIds, int $landlordId, ?Carbon $from, ?Carbon $until): float
    {
        $total = 0.0;
        foreach ($this->landlordRemittances($propertyIds, $landlordId, $from, $until) as $row) {
            $total += (float) $row['amount'];
        }

        return round($total, 2);
    }

    /**
     * Rent receipts that never became an invoice payment, keyed by bill month and property.
     * These are omitted from allocation totals, so a month can look under-collected next to the remittance.
     *
     * @param  list<int>  $propertyIds
     * @return array<string, array<int, float>>
     */
    public function unpostedReceiptsByPropertyMonth(array $propertyIds, Carbon $from, Carbon $until): array
    {
        $propertyIds = array_values(array_unique(array_filter(array_map('intval', $propertyIds), fn (int $id) => $id > 0)));
        if ($propertyIds === [] || ! Schema::hasTable('pm_ezen_receipt_register')) {
            return [];
        }

        $wanted = array_fill_keys($propertyIds, true);
        $propertyByLabel = [];
        foreach (DB::table('property_units')->get(['property_id', 'label']) as $unit) {
            $label = $this->normalizeUnitLabel((string) $unit->label);
            if ($label === '') {
                continue;
            }
            $propertyId = (int) $unit->property_id;
            if (! isset($propertyByLabel[$label])) {
                $propertyByLabel[$label] = $propertyId;
                continue;
            }
            if ($propertyByLabel[$label] !== $propertyId) {
                $propertyByLabel[$label] = 0;
            }
        }
        $propertyByLabel = array_filter(
            $propertyByLabel,
            fn (int $propertyId): bool => $propertyId > 0 && isset($wanted[$propertyId]),
        );
        if ($propertyByLabel === []) {
            return [];
        }

        $receipts = DB::table('pm_ezen_receipt_register')
            ->where('link_status', 'tenant_missing')
            ->where('amount', '>', 0)
            ->where('amount', '<=', 200000)
            ->get(['txn_date', 'unit_label', 'amount', 'particulars']);

        $seen = [];
        $totals = [];
        foreach ($receipts as $receipt) {
            $label = $this->normalizeUnitLabel((string) $receipt->unit_label);
            $propertyId = $propertyByLabel[$label] ?? 0;
            if ($propertyId <= 0) {
                continue;
            }
            $key = $receipt->txn_date.'|'.$label.'|'.$receipt->amount.'|'.$receipt->particulars;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $ym = $this->receiptBillMonth((string) $receipt->particulars, (string) $receipt->txn_date);
            if ($ym === null || $ym < $from->format('Y-m') || $ym > $until->format('Y-m')) {
                continue;
            }
            $totals[$ym][$propertyId] = ($totals[$ym][$propertyId] ?? 0.0) + (float) $receipt->amount;
        }

        return $totals;
    }

    private function normalizeUnitLabel(string $label): string
    {
        $label = strtoupper(trim($label));
        $label = preg_replace('/^HSE\s+/', '', $label) ?? $label;

        return trim($label);
    }

    private function receiptBillMonth(string $particulars, string $txnDate): ?string
    {
        if (preg_match_all('/rent for\s+([A-Za-z]+)\s*\/\s*(20\d{2})/i', $particulars, $matches, PREG_SET_ORDER) >= 1) {
            $last = $matches[count($matches) - 1];
            $parsed = \DateTime::createFromFormat('!M Y', ucfirst(strtolower(substr($last[1], 0, 3))).' '.$last[2]);
            if ($parsed instanceof \DateTime) {
                return $parsed->format('Y-m');
            }
        }

        return preg_match('/^\d{4}-\d{2}/', $txnDate) === 1 ? substr($txnDate, 0, 7) : null;
    }

    /**
     * @return list<array{line_type: string, description: string, amount: float, occurred_at: string|null}>
     */
    private function periodDeductions(int $landlordId, int $propertyId, Carbon $start, Carbon $end): array
    {
        $lines = PmLandlordLedgerEntry::query()
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

        foreach ($this->landlordRemittances([$propertyId], $landlordId, $start, $end) as $remittance) {
            $lines[] = [
                'line_type' => self::LINE_REMITTANCE,
                'description' => (string) $remittance['description'],
                'amount' => round((float) $remittance['amount'], 2),
                'occurred_at' => $remittance['occurred_at'],
            ];
        }

        return $lines;
    }

    /**
     * @return array{landlord_id: int, property_id: int, amount: float, description: string, occurred_at: string|null, period_month: string, marker: string, source_id: string}
     */
    private function remittanceRow(
        int $landlordId,
        int $propertyId,
        float $amount,
        string $description,
        mixed $occurredAt,
        string $periodMonth,
        string $marker,
        string $sourceId,
    ): array {
        $periodMonth = trim($periodMonth);
        if (preg_match('/^\d{4}-\d{2}$/', $periodMonth) !== 1) {
            $periodMonth = $occurredAt ? Carbon::parse($occurredAt)->format('Y-m') : '';
        }

        return [
            'landlord_id' => $landlordId,
            'property_id' => $propertyId,
            'amount' => round($amount, 2),
            'description' => $description,
            'occurred_at' => $occurredAt ? Carbon::parse($occurredAt)->format('Y-m-d') : ($periodMonth !== '' ? $periodMonth.'-01' : null),
            'period_month' => $periodMonth,
            'marker' => $marker,
            'source_id' => $sourceId,
        ];
    }

    private function remittanceFallsInRange(mixed $periodMonth, mixed $occurredAt, ?Carbon $from, ?Carbon $until): bool
    {
        $periodMonth = trim((string) $periodMonth);
        if (preg_match('/^\d{4}-\d{2}$/', $periodMonth) === 1) {
            $point = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();
        } elseif ($occurredAt) {
            $point = Carbon::parse($occurredAt);
        } else {
            return false;
        }

        if ($from !== null && $point->lt($from->copy()->startOfDay())) {
            return false;
        }
        if ($until !== null && $point->gt($until->copy()->endOfDay())) {
            return false;
        }

        return true;
    }

    private function ezenVoucherMarker(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/\[EZEN\s+(PM\d+)\]/i', $value, $match) === 1) {
            return '[EZEN '.strtoupper($match[1]).']';
        }
        if (preg_match('/\b(PM\d+)\b/i', $value, $match) === 1) {
            return '[EZEN '.strtoupper($match[1]).']';
        }

        return '';
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
