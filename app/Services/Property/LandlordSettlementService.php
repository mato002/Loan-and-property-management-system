<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLandlordPayout;
use App\Models\PmLandlordPayoutItem;
use App\Models\PmPayment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Support\Property\PropertyUnitOccupancyStats;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        $openAdvances = $this->openAdvances($landlordId, $propertyId);
        $agreedPayDay = $link->agreed_pay_day !== null ? (int) $link->agreed_pay_day : null;
        $nextAgreedPayDate = app(LandlordAdvanceService::class)->nextAgreedPayDate($agreedPayDay, $periodEnd);
        $unitStats = PropertyUnitOccupancyStats::forProperty($propertyId);
        $unitLines = $this->unitSettlementLines($propertyId, $periodStart, $periodEnd);

        return [
            'property_id' => $propertyId,
            'landlord_id' => $landlordId,
            'property_name' => (string) $link->property_name,
            'landlord_name' => (string) $link->landlord_name,
            'ownership_percent' => $ownershipPct,
            'commission_percent' => $commissionPct,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_label' => $periodStart->format('F Y'),
            'period_month' => $periodStart->format('Y-m'),
            'unit_stats' => $unitStats,
            'collected' => $collected,
            'owner_collected' => $ownerCollected,
            'management_fee' => $managementFee,
            'net_collected' => $netCollected,
            'balance_brought_forward' => $balanceBf,
            'period_credits' => $periodCredits,
            'period_debits' => $periodDebits,
            'deductions' => $deductions,
            'deductions_total' => round(collect($deductions)->sum('amount'), 2),
            'open_advances' => $openAdvances,
            'open_advances_total' => round(collect($openAdvances)->sum('amount'), 2),
            'agreed_pay_day' => $agreedPayDay,
            'agreed_pay_notes' => (string) ($link->agreed_pay_notes ?? ''),
            'next_agreed_pay_date' => $nextAgreedPayDate?->format('Y-m-d'),
            'closing_balance' => $closingBalance,
            'net_amount_due' => $netAmountDue,
            'unit_lines' => $unitLines,
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

    public function approvePayout(PmLandlordPayout $payout, User $actor): void
    {
        if ($payout->status !== 'draft') {
            throw new RuntimeException('Only draft payouts can be approved.');
        }

        $payout->update([
            'status' => 'approved',
            'approved_by' => (int) $actor->id,
        ]);
    }

    public function markPayoutPaid(PmLandlordPayout $payout, User $actor): void
    {
        if (! in_array($payout->status, ['draft', 'approved'], true)) {
            throw new RuntimeException('Payout cannot be marked paid from current status.');
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
     * @return list<array<string, mixed>>
     */
    public function unitSettlementLines(int $propertyId, Carbon $start, Carbon $end): array
    {
        $rows = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->leftJoin('pm_tenants as t', 't.id', '=', 'pay.pm_tenant_id')
            ->where('pu.property_id', $propertyId)
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$start, $end])
            ->groupBy('pu.id', 'pu.label', 'pu.status', 't.name')
            ->orderBy('pu.label')
            ->selectRaw('pu.id as unit_id, pu.label as unit_label, pu.status as unit_status, MAX(t.name) as tenant_name')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_RENT."' THEN a.amount ELSE 0 END), 0) as rent_received,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_GARBAGE."' THEN a.amount ELSE 0 END), 0) as garbage_received,
                COALESCE(SUM(CASE WHEN i.invoice_type = '".PmInvoice::TYPE_WATER."' THEN a.amount ELSE 0 END), 0) as water_received,
                COALESCE(SUM(a.amount), 0) as total_received
            ")
            ->get()
            ->map(fn ($row) => [
                'unit_id' => (int) $row->unit_id,
                'unit_label' => (string) $row->unit_label,
                'unit_status' => (string) $row->unit_status,
                'tenant_name' => (string) ($row->tenant_name ?? '—'),
                'rent_received' => round((float) $row->rent_received, 2),
                'garbage_received' => round((float) $row->garbage_received, 2),
                'water_received' => round((float) $row->water_received, 2),
                'total_received' => round((float) $row->total_received, 2),
            ])
            ->keyBy('unit_id');

        $ownerUnits = PropertyUnit::query()
            ->where('property_id', $propertyId)
            ->where('status', PropertyUnit::STATUS_OWNER_OCCUPIED)
            ->orderBy('label')
            ->get(['id', 'label', 'status']);

        foreach ($ownerUnits as $unit) {
            if ($rows->has($unit->id)) {
                continue;
            }

            $rows->put($unit->id, [
                'unit_id' => (int) $unit->id,
                'unit_label' => (string) $unit->label,
                'unit_status' => (string) $unit->status,
                'tenant_name' => 'Owner (LLD)',
                'rent_received' => 0.0,
                'garbage_received' => 0.0,
                'water_received' => 0.0,
                'total_received' => 0.0,
            ]);
        }

        return $rows->values()->sortBy('unit_label')->values()->all();
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
