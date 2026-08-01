<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmLease;
use App\Models\PropertyUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RentInvoiceGenerator
{
    public const REASON_MISSING = 'missing_invoice';

    public const REASON_ALREADY = 'already_invoiced';

    public const REASON_UNDERBILLED = 'underbilled';

    public const REASON_NO_UNIT = 'no_unit';

    public const REASON_ZERO_RENT = 'zero_rent';

    private const AMOUNT_EPSILON = 0.01;

    /**
     * @return array{ym: string, period_start: Carbon, period_end: Carbon, issue_date: string}
     */
    public function resolveBillingMonth(?string $ym = null): array
    {
        $ym = $ym && preg_match('/^\d{4}-\d{2}$/', $ym) ? $ym : now()->format('Y-m');
        $periodStart = now()->setTimezone(config('app.timezone'))->parse($ym.'-01')->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        return [
            'ym' => $ym,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'issue_date' => $periodStart->toDateString(),
        ];
    }

    /**
     * @return array{from: string, to: string}
     */
    public function issueDateMonthBounds(string $issueDate): array
    {
        $anchor = Carbon::parse($issueDate)->startOfDay();

        return [
            'from' => $anchor->copy()->startOfMonth()->toDateString(),
            'to' => $anchor->copy()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Legacy helper: any rent charge row for lease + unit in the billing month (by billing_period or issue month).
     */
    public function rentInvoiceExistsForLeaseUnitMonth(int $leaseId, int $unitId, string $issueDate): bool
    {
        $billingYm = Carbon::parse($issueDate)->format('Y-m');

        return $this->invoicedRentTotalForLeaseUnitBillingMonth($leaseId, $unitId, $billingYm) > self::AMOUNT_EPSILON;
    }

    public function isFullyInvoicedForLeaseUnitBillingMonth(int $leaseId, int $unitId, string $billingYm, float $expectedAmount): bool
    {
        $expected = round(max(0.0, $expectedAmount), 2);
        if ($expected <= 0) {
            return true;
        }

        return $this->invoicedRentTotalForLeaseUnitBillingMonth($leaseId, $unitId, $billingYm) >= ($expected - self::AMOUNT_EPSILON);
    }

    public function invoicedRentTotalForLeaseUnitBillingMonth(int $leaseId, int $unitId, string $billingYm): float
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $billingYm)) {
            return 0.0;
        }

        $bounds = $this->issueDateMonthBounds($billingYm.'-01');

        $invoices = $this->rentChargeQueryForBillingMonth($leaseId, $unitId, $billingYm, $bounds)
            ->get(['id', 'amount']);

        $total = (float) $invoices->sum(fn (PmInvoice $inv) => (float) $inv->amount);

        $invoiceIds = $invoices->pluck('id')->filter()->values();
        if ($invoiceIds->isNotEmpty()) {
            $creditTotal = (float) PmInvoice::query()
                ->where('invoice_kind', PmInvoice::KIND_CREDIT_NOTE)
                ->whereIn('original_invoice_id', $invoiceIds)
                ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
                ->sum('amount');
            $total += $creditTotal;
        }

        return round(max(0.0, $total), 2);
    }

    /**
     * @param  array{from: string, to: string}  $issueMonthBounds
     */
    private function rentChargeQueryForBillingMonth(int $leaseId, int $unitId, string $billingYm, array $issueMonthBounds): Builder
    {
        return PmInvoice::query()
            ->where('pm_lease_id', $leaseId)
            ->where('property_unit_id', $unitId)
            ->where('invoice_type', PmInvoice::TYPE_RENT)
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->where(function ($q) use ($billingYm, $issueMonthBounds) {
                $q->where('billing_period', $billingYm)
                    ->orWhere(function ($q2) use ($issueMonthBounds) {
                        $q2->whereNull('billing_period')
                            ->whereBetween('issue_date', [$issueMonthBounds['from'], $issueMonthBounds['to']]);
                    });
            })
            ->where(function ($q) {
                $q->whereNull('invoice_kind')
                    ->orWhereIn('invoice_kind', [
                        PmInvoice::KIND_INVOICE,
                        PmInvoice::KIND_RENT_SUPPLEMENT,
                    ]);
            });
    }

    /**
     * @return Collection<int, PmLease>
     */
    public function eligibleLeases(Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $periodEnd->toDateString());
            })
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $periodStart->toDateString());
            })
            ->whereHas('units.property', function ($q) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('properties', 'management_status')) {
                    $q->whereNotIn('management_status', [
                        \App\Models\Property::MANAGEMENT_ARCHIVED,
                        \App\Models\Property::MANAGEMENT_ENDED,
                    ]);
                }
            })
            ->with(['units.property', 'pmTenant:id,name,phone'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array{
     *   key: string,
     *   lease_id: int,
     *   unit_id: int|null,
     *   tenant_id: int,
     *   tenant_name: string,
     *   property_name: string,
     *   unit_label: string,
     *   monthly_rent: float,
     *   expected_amount: float,
     *   invoiced_amount: float,
     *   bill_amount: float,
     *   due_date: string,
     *   reason: string,
     *   reason_label: string,
     *   can_generate: bool,
     *   can_generate_supplement: bool,
     * }>
     */
    public function reportRows(?string $ym = null): array
    {
        $period = $this->resolveBillingMonth($ym);
        $rows = [];

        foreach ($this->eligibleLeases($period['period_start'], $period['period_end']) as $lease) {
            $units = $lease->units;
            if ($units->isEmpty()) {
                $rows[] = $this->rowFromLease($lease, null, $period, self::REASON_NO_UNIT);

                continue;
            }

            $perUnitAmount = (float) $lease->monthly_rent;
            if ($units->count() > 1) {
                $perUnitAmount = round($perUnitAmount / $units->count(), 2);
            }
            if ($perUnitAmount <= 0) {
                foreach ($units as $unit) {
                    $rows[] = $this->rowFromLease($lease, $unit, $period, self::REASON_ZERO_RENT, 0.0);
                }

                continue;
            }

            foreach ($units as $unit) {
                $invoiced = $this->invoicedRentTotalForLeaseUnitBillingMonth(
                    (int) $lease->id,
                    (int) $unit->id,
                    (string) $period['ym'],
                );
                $reason = self::REASON_MISSING;
                if ($invoiced >= ($perUnitAmount - self::AMOUNT_EPSILON)) {
                    $reason = self::REASON_ALREADY;
                } elseif ($invoiced > self::AMOUNT_EPSILON) {
                    $reason = self::REASON_UNDERBILLED;
                }

                $rows[] = $this->rowFromLease(
                    $lease,
                    $unit,
                    $period,
                    $reason,
                    $perUnitAmount,
                    $invoiced,
                );
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>|null  $keys  lease_id-unit_id keys; null = all missing
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function generateMissing(?string $ym = null, ?array $keys = null, ?User $actor = null): array
    {
        $period = $this->resolveBillingMonth($ym);
        $created = 0;
        $skipped = 0;
        $errors = [];

        $missingRows = collect($this->reportRows($period['ym']))
            ->filter(fn (array $r) => $r['reason'] === self::REASON_MISSING);

        if ($keys !== null) {
            $keySet = array_fill_keys($keys, true);
            $missingRows = $missingRows->filter(fn (array $r) => isset($keySet[$r['key']]));
        }

        foreach ($missingRows as $row) {
            try {
                $invoice = $this->createInvoiceForRow($row, $period, $actor);
                if ($invoice) {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = ($row['tenant_name'] ?? 'Tenant').': '.$e->getMessage();
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  list<string>|null  $keys
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function generateRentSupplements(?string $ym = null, ?array $keys = null, ?User $actor = null): array
    {
        $period = $this->resolveBillingMonth($ym);
        $created = 0;
        $skipped = 0;
        $errors = [];

        $rows = collect($this->reportRows($period['ym']))
            ->filter(fn (array $r) => $r['reason'] === self::REASON_UNDERBILLED);

        if ($keys !== null) {
            $keySet = array_fill_keys($keys, true);
            $rows = $rows->filter(fn (array $r) => isset($keySet[$r['key']]));
        }

        foreach ($rows as $row) {
            try {
                $invoice = $this->createSupplementInvoiceForRow($row, $period, $actor);
                if ($invoice) {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = ($row['tenant_name'] ?? 'Tenant').': '.$e->getMessage();
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param  array{key: string, lease_id: int, unit_id: int|null, tenant_id: int, bill_amount: float, due_date: string}  $row
     */
    public function generateOne(array $row, ?string $ym = null, ?User $actor = null): ?PmInvoice
    {
        if ($row['reason'] !== self::REASON_MISSING || ! $row['unit_id']) {
            return null;
        }

        $period = $this->resolveBillingMonth($ym);

        return $this->createInvoiceForRow($row, $period, $actor);
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function createInvoiceForRow(array $row, array $period, ?User $actor): ?PmInvoice
    {
        $lease = PmLease::query()
            ->with(['units.property', 'pmTenant'])
            ->find($row['lease_id']);
        $unit = PropertyUnit::query()->with('property')->find($row['unit_id']);

        if (! $lease || ! $unit || (int) $lease->pm_tenant_id !== (int) $row['tenant_id']) {
            return null;
        }

        $amount = round((float) $row['bill_amount'], 2);
        if ($amount <= 0) {
            return null;
        }

        if ($this->invoicedRentTotalForLeaseUnitBillingMonth((int) $lease->id, (int) $unit->id, (string) $period['ym']) > self::AMOUNT_EPSILON) {
            return null;
        }

        $dueDate = $row['due_date'] ?: app(RentDueDayResolver::class)->dueDateForBillingMonth($lease, $period['period_start']);

        return DB::transaction(function () use ($lease, $unit, $amount, $period, $dueDate, $actor) {
            $invoiceNo = PmInvoice::nextInvoiceNumber();
            $agentUserId = optional($unit->property)->agent_user_id;

            $inv = PmInvoice::query()->create([
                'pm_lease_id' => $lease->id,
                'property_unit_id' => $unit->id,
                'pm_tenant_id' => $lease->pm_tenant_id,
                'agent_user_id' => $agentUserId,
                'invoice_no' => $invoiceNo,
                'issue_date' => $period['issue_date'],
                'due_date' => $dueDate,
                'amount' => $amount,
                'amount_paid' => 0,
                'subtotal_amount' => $amount,
                'total_amount' => $amount,
                'status' => PmInvoice::STATUS_SENT,
                'sent_at' => now(),
                'invoice_type' => PmInvoice::TYPE_RENT,
                'billing_period' => $period['ym'],
                'description' => 'Rent '.$lease->pmTenant?->name.' · '.$period['issue_date'].' → '.$dueDate,
            ]);
            $inv->refreshComputedStatus();

            PropertyAccountingPostingService::postInvoiceIssued($inv, $actor);

            if ($lease->pm_tenant_id) {
                app(TenantCreditService::class)->autoApplyForTenant(
                    (int) $lease->pm_tenant_id,
                    $actor,
                );
            }

            PmInvoiceEvent::record(
                (int) $inv->id,
                PmInvoiceEvent::EVENT_ISSUED,
                $actor?->id,
                'Rent invoice generated from uninvoiced leases report',
                ['source' => 'revenue.uninvoiced_leases', 'billing_period' => $period['ym']]
            );

            return $inv;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $period
     */
    private function createSupplementInvoiceForRow(array $row, array $period, ?User $actor): ?PmInvoice
    {
        if ($row['reason'] !== self::REASON_UNDERBILLED || empty($row['unit_id'])) {
            return null;
        }

        $lease = PmLease::query()
            ->with(['units.property', 'pmTenant'])
            ->find($row['lease_id']);
        $unit = PropertyUnit::query()->with('property')->find($row['unit_id']);

        if (! $lease || ! $unit || (int) $lease->pm_tenant_id !== (int) $row['tenant_id']) {
            return null;
        }

        $expected = round((float) ($row['expected_amount'] ?? $row['bill_amount']), 2);
        $invoiced = $this->invoicedRentTotalForLeaseUnitBillingMonth(
            (int) $lease->id,
            (int) $unit->id,
            (string) $period['ym'],
        );
        $amount = round($expected - $invoiced, 2);
        if ($amount <= self::AMOUNT_EPSILON) {
            return null;
        }

        $bounds = $this->issueDateMonthBounds($period['ym'].'-01');
        $anchorInvoice = $this->rentChargeQueryForBillingMonth(
            (int) $lease->id,
            (int) $unit->id,
            (string) $period['ym'],
            $bounds,
        )->orderBy('id')->first(['id']);

        $issueDate = now()->toDateString();
        $dueDate = $row['due_date'] ?: app(RentDueDayResolver::class)->dueDateForBillingMonth($lease, $period['period_start']);
        if (Carbon::parse($dueDate)->lt(Carbon::parse($issueDate))) {
            $dueDate = $issueDate;
        }

        return DB::transaction(function () use ($lease, $unit, $amount, $period, $dueDate, $issueDate, $actor, $anchorInvoice, $invoiced, $expected) {
            $invoiceNo = PmInvoice::nextInvoiceNumber();
            $agentUserId = optional($unit->property)->agent_user_id;

            $inv = PmInvoice::query()->create([
                'pm_lease_id' => $lease->id,
                'property_unit_id' => $unit->id,
                'pm_tenant_id' => $lease->pm_tenant_id,
                'agent_user_id' => $agentUserId,
                'invoice_no' => $invoiceNo,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'amount' => $amount,
                'amount_paid' => 0,
                'subtotal_amount' => $amount,
                'total_amount' => $amount,
                'status' => PmInvoice::STATUS_SENT,
                'sent_at' => now(),
                'invoice_type' => PmInvoice::TYPE_RENT,
                'invoice_kind' => PmInvoice::KIND_RENT_SUPPLEMENT,
                'original_invoice_id' => $anchorInvoice?->id,
                'billing_period' => $period['ym'],
                'description' => 'Rent supplement (lease increase) · '.$period['ym'].' · was KES '.number_format($invoiced, 2).' → KES '.number_format($expected, 2),
            ]);
            $inv->refreshComputedStatus();

            PropertyAccountingPostingService::postInvoiceIssued($inv, $actor);

            if ($lease->pm_tenant_id) {
                app(TenantCreditService::class)->autoApplyForTenant(
                    (int) $lease->pm_tenant_id,
                    $actor,
                );
            }

            PmInvoiceEvent::record(
                (int) $inv->id,
                PmInvoiceEvent::EVENT_ISSUED,
                $actor?->id,
                'Rent supplement for billing period '.$period['ym'],
                [
                    'source' => 'revenue.uninvoiced_leases.supplement',
                    'billing_period' => $period['ym'],
                    'expected' => $expected,
                    'previously_invoiced' => $invoiced,
                    'supplement_amount' => $amount,
                ]
            );

            return $inv;
        });
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function rowFromLease(
        PmLease $lease,
        ?PropertyUnit $unit,
        array $period,
        string $reason,
        ?float $expectedPerUnit = null,
        ?float $invoicedAmount = null,
    ): array {
        $units = $lease->units;
        $expected = $expectedPerUnit;
        if ($expected === null && $units->isNotEmpty()) {
            $expected = (float) $lease->monthly_rent;
            if ($units->count() > 1) {
                $expected = round($expected / $units->count(), 2);
            }
        }
        $expected = round(max(0.0, (float) ($expected ?? 0)), 2);
        $invoiced = round(max(0.0, (float) ($invoicedAmount ?? 0)), 2);

        $billAmount = match ($reason) {
            self::REASON_UNDERBILLED => round(max(0.0, $expected - $invoiced), 2),
            self::REASON_MISSING => $expected,
            default => $expected,
        };

        $dueDate = app(RentDueDayResolver::class)->dueDateForBillingMonth($lease, $period['period_start']);
        $unitId = $unit?->id;
        $key = $unitId ? $lease->id.'-'.$unitId : (string) $lease->id.'-0';

        return [
            'key' => $key,
            'lease_id' => (int) $lease->id,
            'unit_id' => $unitId ? (int) $unitId : null,
            'property_id' => (int) ($unit?->property_id ?? $units->first()?->property_id ?? 0),
            'tenant_id' => (int) $lease->pm_tenant_id,
            'tenant_name' => (string) ($lease->pmTenant?->name ?? '—'),
            'property_name' => (string) ($unit?->property?->name ?? ($units->first()?->property?->name ?? '—')),
            'unit_label' => (string) ($unit?->label ?? '—'),
            'monthly_rent' => (float) $lease->monthly_rent,
            'expected_amount' => $expected,
            'invoiced_amount' => $invoiced,
            'bill_amount' => $billAmount,
            'due_date' => $dueDate,
            'reason' => $reason,
            'reason_label' => $this->reasonLabel($reason),
            'can_generate' => $reason === self::REASON_MISSING && $unitId !== null,
            'can_generate_supplement' => $reason === self::REASON_UNDERBILLED && $unitId !== null,
        ];
    }

    public function reasonLabel(string $reason): string
    {
        return match ($reason) {
            self::REASON_MISSING => 'Not invoiced',
            self::REASON_ALREADY => 'Already invoiced',
            self::REASON_UNDERBILLED => 'Rent increase due',
            self::REASON_NO_UNIT => 'No unit on lease',
            self::REASON_ZERO_RENT => 'Zero rent',
            default => ucfirst(str_replace('_', ' ', $reason)),
        };
    }
}
