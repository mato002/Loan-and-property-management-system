<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmLease;
use App\Models\PmWaterReading;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Models\UtilityAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WaterBillingService
{
    private const AMOUNT_EPSILON = 0.01;

    public function __construct(
        private readonly TenantCreditService $tenantCreditService,
    ) {}

    /**
     * @return array{units_used: float, amount: float}
     */
    public function calculateReadingAmount(
        float $previousReading,
        float $currentReading,
        float $ratePerUnit,
        float $fixedCharge = 0.0,
        bool $isMeterReset = false,
    ): array {
        $previous = round(max(0.0, $previousReading), 3);
        $current = round(max(0.0, $currentReading), 3);
        $rate = round(max(0.0, $ratePerUnit), 2);
        $fixed = round(max(0.0, $fixedCharge), 2);

        if (! $isMeterReset && $current < $previous) {
            throw ValidationException::withMessages([
                'current_reading' => 'Current reading cannot be less than previous reading ('.$previous.'). Use meter reset if the meter was replaced.',
            ]);
        }

        $unitsUsed = $isMeterReset
            ? $current
            : round($current - $previous, 3);

        $amount = round(($unitsUsed * $rate) + $fixed, 2);

        return [
            'units_used' => max(0.0, $unitsUsed),
            'amount' => max(0.0, $amount),
        ];
    }

    public function defaultPreviousReading(int $unitId, string $billingMonth): float
    {
        return round((float) (PmWaterReading::query()
            ->where('property_unit_id', $unitId)
            ->where('billing_month', '<', $billingMonth)
            ->orderByDesc('billing_month')
            ->value('current_reading') ?? 0), 3);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function recordReading(array $data, ?User $actor = null, bool $skipPeriodGuard = false): PmWaterReading
    {
        $unitId = (int) $data['property_unit_id'];
        $month = (string) $data['billing_month'];
        $overrideId = isset($data['utility_override_request_id']) ? (int) $data['utility_override_request_id'] : null;

        if (! $skipPeriodGuard) {
            app(UtilityPeriodGuardService::class)->assertMutable(
                $month,
                UtilityPeriodGuardService::ACTION_EDIT_READING,
                $actor,
                $overrideId ?: null,
                'pm_water_reading',
                null,
            );
        }

        $isMeterReset = (bool) ($data['is_meter_reset'] ?? false);
        $isEstimated = (bool) ($data['is_estimated'] ?? false);

        $exists = PmWaterReading::query()
            ->where('property_unit_id', $unitId)
            ->where('billing_month', $month)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'billing_month' => 'A water reading already exists for this unit and month.',
            ]);
        }

        $prev = isset($data['previous_reading']) && $data['previous_reading'] !== ''
            ? (float) $data['previous_reading']
            : $this->defaultPreviousReading($unitId, $month);

        $calc = $this->calculateReadingAmount(
            $prev,
            (float) $data['current_reading'],
            (float) $data['rate_per_unit'],
            (float) ($data['fixed_charge'] ?? 0),
            $isMeterReset,
        );

        return DB::transaction(function () use ($data, $unitId, $month, $prev, $calc, $isMeterReset, $isEstimated, $actor) {
            $reading = PmWaterReading::query()->create([
                'property_unit_id' => $unitId,
                'billing_month' => $month,
                'previous_reading' => $prev,
                'current_reading' => (float) $data['current_reading'],
                'units_used' => $calc['units_used'],
                'rate_per_unit' => (float) $data['rate_per_unit'],
                'fixed_charge' => (float) ($data['fixed_charge'] ?? 0),
                'amount' => $calc['amount'],
                'status' => 'recorded',
                'is_estimated' => $isEstimated,
                'is_meter_reset' => $isMeterReset,
                'notes' => $data['notes'] ?? null,
            ]);

            UtilityAuditLog::record('reading_recorded', 'pm_water_reading', (int) $reading->id, [
                'billing_month' => $month,
                'property_unit_id' => $unitId,
                'actor_user_id' => $actor?->id,
                'payload' => [
                    'units_used' => $calc['units_used'],
                    'amount' => $calc['amount'],
                    'is_estimated' => $isEstimated,
                    'is_meter_reset' => $isMeterReset,
                ],
            ]);

            return $reading;
        });
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function updateReading(PmWaterReading $reading, array $data, ?User $actor = null, ?int $overrideId = null): PmWaterReading
    {
        if ($reading->pm_invoice_id) {
            throw ValidationException::withMessages([
                'reading' => 'Invoiced readings cannot be edited. Use water rate corrections to bill the difference, or edit the water invoice if the period is still open.',
            ]);
        }

        app(UtilityPeriodGuardService::class)->assertReadingMutable(
            $reading,
            UtilityPeriodGuardService::ACTION_EDIT_READING,
            $actor,
            $overrideId,
        );

        $unitId = (int) $reading->property_unit_id;
        $month = (string) $reading->billing_month;
        $isMeterReset = (bool) ($data['is_meter_reset'] ?? $reading->is_meter_reset);
        $isEstimated = (bool) ($data['is_estimated'] ?? $reading->is_estimated);

        $prev = isset($data['previous_reading']) && $data['previous_reading'] !== ''
            ? (float) $data['previous_reading']
            : (float) $reading->previous_reading;

        $current = isset($data['current_reading'])
            ? (float) $data['current_reading']
            : (float) $reading->current_reading;

        $rate = isset($data['rate_per_unit'])
            ? (float) $data['rate_per_unit']
            : (float) $reading->rate_per_unit;

        $fixed = array_key_exists('fixed_charge', $data)
            ? (float) ($data['fixed_charge'] ?? 0)
            : (float) $reading->fixed_charge;

        $calc = $this->calculateReadingAmount($prev, $current, $rate, $fixed, $isMeterReset);

        return DB::transaction(function () use ($reading, $data, $prev, $current, $rate, $fixed, $calc, $isMeterReset, $isEstimated, $actor, $unitId, $month) {
            $before = $reading->only([
                'previous_reading', 'current_reading', 'units_used', 'rate_per_unit', 'fixed_charge', 'amount',
            ]);

            $reading->update([
                'previous_reading' => $prev,
                'current_reading' => $current,
                'units_used' => $calc['units_used'],
                'rate_per_unit' => $rate,
                'fixed_charge' => $fixed,
                'amount' => $calc['amount'],
                'is_estimated' => $isEstimated,
                'is_meter_reset' => $isMeterReset,
                'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?? null) : $reading->notes,
                'status' => 'recorded',
            ]);

            UtilityAuditLog::record('reading_updated', 'pm_water_reading', (int) $reading->id, [
                'billing_month' => $month,
                'property_unit_id' => $unitId,
                'actor_user_id' => $actor?->id,
                'payload' => [
                    'before' => $before,
                    'after' => $reading->only([
                        'previous_reading', 'current_reading', 'units_used', 'rate_per_unit', 'fixed_charge', 'amount',
                    ]),
                ],
            ]);

            return $reading->fresh();
        });
    }

    /**
     * @param  array<int, float>  $currentReadings
     * @param  array<int|string, mixed>  $previousReadings
     * @param  array<int|string, mixed>  $estimatedFlags
     * @param  array<int|string, mixed>  $resetFlags
     * @param  array<int, string>  $unitLabels
     * @return array{saved: int, errors: array<string, string>}
     */
    public function recordBulkReadings(
        array $currentReadings,
        string $billingMonth,
        float $ratePerUnit,
        float $fixedCharge,
        ?string $notes,
        array $previousReadings,
        array $estimatedFlags,
        array $resetFlags,
        array $unitLabels,
        ?User $actor = null,
        ?int $utilityOverrideRequestId = null,
    ): array {
        $errors = [];
        $saved = 0;

        app(UtilityPeriodGuardService::class)->assertMutable(
            $billingMonth,
            UtilityPeriodGuardService::ACTION_EDIT_READING,
            $actor,
            $utilityOverrideRequestId,
            'pm_water_reading',
            null,
        );

        DB::transaction(function () use (
            $currentReadings,
            $billingMonth,
            $ratePerUnit,
            $fixedCharge,
            $notes,
            $previousReadings,
            $estimatedFlags,
            $resetFlags,
            $unitLabels,
            $actor,
            &$errors,
            &$saved,
            $utilityOverrideRequestId,
        ) {
            foreach ($currentReadings as $unitId => $current) {
                $unitId = (int) $unitId;
                $label = $unitLabels[$unitId] ?? ('Unit '.$unitId);
                $rawPrev = $previousReadings[$unitId] ?? $previousReadings[(string) $unitId] ?? null;
                $isEstimated = (bool) ($estimatedFlags[$unitId] ?? $estimatedFlags[(string) $unitId] ?? false);
                $isMeterReset = (bool) ($resetFlags[$unitId] ?? $resetFlags[(string) $unitId] ?? false);

                try {
                    $this->recordReading([
                        'property_unit_id' => $unitId,
                        'billing_month' => $billingMonth,
                        'current_reading' => $current,
                        'previous_reading' => ($rawPrev !== null && $rawPrev !== '') ? (float) $rawPrev : null,
                        'rate_per_unit' => $ratePerUnit,
                        'fixed_charge' => $fixedCharge,
                        'notes' => $notes,
                        'is_estimated' => $isEstimated,
                        'is_meter_reset' => $isMeterReset,
                    ], $actor, skipPeriodGuard: true);
                    $saved++;
                } catch (ValidationException $e) {
                    $message = collect($e->errors())->flatten()->first() ?? 'Invalid reading.';
                    $errors['current_readings.'.$unitId] = $label.': '.$message;
                } catch (\App\Exceptions\Property\UtilityPeriodClosedException $e) {
                    throw $e;
                }
            }
        });

        return ['saved' => $saved, 'errors' => $errors];
    }

    /**
     * Generate water invoices from uninvoiced readings for a billing month.
     *
     * @return array{created: int, skipped_no_lease: int, skipped_duplicate: int, credit_applied: int}
     */
    public function generateInvoicesForMonth(
        string $billingMonth,
        string $dueDate,
        ?User $actor = null,
        string $source = 'manual',
        bool $postToGl = true,
        bool $autoApplyCredit = true,
        ?int $utilityOverrideRequestId = null,
    ): array {
        app(UtilityPeriodGuardService::class)->assertMutable(
            $billingMonth,
            UtilityPeriodGuardService::ACTION_GENERATE_INVOICE,
            $actor,
            $utilityOverrideRequestId,
            'utility_billing_period',
            null,
        );

        $readings = PmWaterReading::query()
            ->with(['unit.property'])
            ->where('billing_month', $billingMonth)
            ->whereNull('pm_invoice_id')
            ->lockForUpdate()
            ->orderBy('property_unit_id')
            ->get();

        $stats = [
            'created' => 0,
            'skipped_no_lease' => 0,
            'skipped_duplicate' => 0,
            'credit_applied' => 0,
        ];

        foreach ($readings as $reading) {
            DB::transaction(function () use ($reading, $billingMonth, $dueDate, $actor, $source, $postToGl, $autoApplyCredit, &$stats) {
                $locked = PmWaterReading::query()->whereKey($reading->id)->lockForUpdate()->first();
                if (! $locked || $locked->pm_invoice_id) {
                    return;
                }

                $lease = PmLease::query()
                    ->where('status', PmLease::STATUS_ACTIVE)
                    ->whereHas('units', fn ($q) => $q->where('property_units.id', $locked->property_unit_id))
                    ->with('pmTenant')
                    ->first();

                if (! $lease || ! $lease->pm_tenant_id) {
                    $stats['skipped_no_lease']++;

                    return;
                }

                $property = $locked->unit?->property;
                if ($property && ! app(\App\Services\Property\PropertyManagementGuardService::class)->allowsWaterBilling($property)) {
                    $stats['skipped_no_lease']++;

                    return;
                }

                $duplicate = PmInvoice::query()
                    ->where('property_unit_id', $locked->property_unit_id)
                    ->where('pm_tenant_id', $lease->pm_tenant_id)
                    ->where('invoice_type', PmInvoice::TYPE_WATER)
                    ->where('billing_period', $billingMonth)
                    ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
                    ->exists();

                if ($duplicate) {
                    $stats['skipped_duplicate']++;

                    return;
                }

                $agentUserId = $locked->unit?->property?->agent_user_id;
                $amount = round((float) $locked->amount, 2);
                if ($amount <= 0) {
                    return;
                }

                $invoice = PmInvoice::query()->create([
                    'pm_lease_id' => $lease->id,
                    'property_unit_id' => $locked->property_unit_id,
                    'pm_tenant_id' => $lease->pm_tenant_id,
                    'agent_user_id' => $agentUserId,
                    'invoice_no' => PmInvoice::nextInvoiceNumber(),
                    'issue_date' => PmInvoice::issueDateForBillingPeriod($billingMonth),
                    'due_date' => $dueDate,
                    'amount' => $amount,
                    'amount_paid' => 0,
                    'subtotal_amount' => $amount,
                    'total_amount' => $amount,
                    'status' => PmInvoice::STATUS_SENT,
                    'sent_at' => now(),
                    'invoice_type' => PmInvoice::TYPE_WATER,
                    'billing_period' => $billingMonth,
                    'description' => 'Water bill '.$billingMonth.' ('.number_format((float) $locked->units_used, 3).' units)',
                ]);
                $invoice->refreshComputedStatus();

                $locked->update([
                    'pm_invoice_id' => $invoice->id,
                    'status' => 'invoiced',
                ]);

                if ($postToGl) {
                    PropertyAccountingPostingService::postInvoiceIssued($invoice, $actor);
                }

                PmInvoiceEvent::record(
                    (int) $invoice->id,
                    PmInvoiceEvent::EVENT_ISSUED,
                    $actor?->id,
                    'Water invoice generated ('.$source.')',
                    [
                        'source' => $source,
                        'water_reading_id' => (int) $locked->id,
                        'units_used' => (float) $locked->units_used,
                    ]
                );

                UtilityAuditLog::record('invoice_generated', 'pm_invoice', (int) $invoice->id, [
                    'billing_month' => $billingMonth,
                    'property_unit_id' => (int) $locked->property_unit_id,
                    'pm_tenant_id' => (int) $lease->pm_tenant_id,
                    'pm_invoice_id' => (int) $invoice->id,
                    'actor_user_id' => $actor?->id,
                    'payload' => ['reading_id' => (int) $locked->id, 'amount' => $amount, 'source' => $source],
                ]);

                if ($autoApplyCredit && $this->tenantCreditService->isEnabled()) {
                    $applied = $this->tenantCreditService->autoApplyForTenant(
                        (int) $lease->pm_tenant_id,
                        $actor,
                        (int) $invoice->id,
                    );
                    if ($applied !== []) {
                        $stats['credit_applied']++;
                    }
                }

                $stats['created']++;
            });
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function uninvoicedReadingsReport(?string $billingMonth = null): array
    {
        $query = PmWaterReading::query()
            ->with('unit.property')
            ->whereNull('pm_invoice_id')
            ->orderByDesc('billing_month')
            ->orderBy('property_unit_id');

        if ($billingMonth) {
            $query->where('billing_month', $billingMonth);
        }

        return $query->limit(500)->get()->map(fn (PmWaterReading $r) => [
            'month' => $r->billing_month,
            'property' => $r->unit?->property?->name ?? '—',
            'unit' => $r->unit?->label ?? '—',
            'units_used' => (float) $r->units_used,
            'amount' => (float) $r->amount,
            'status' => $r->status,
        ])->all();
    }

    /**
     * @param  array{rate_per_unit?: float|null, fixed_charge?: float|null}|null  $template
     */
    public function expectedAmountForReading(PmWaterReading $reading, ?array $template = null): float
    {
        $unitsUsed = round(max(0.0, (float) $reading->units_used), 3);
        $readingRate = round(max(0.0, (float) $reading->rate_per_unit), 2);
        $readingFixed = round(max(0.0, (float) $reading->fixed_charge), 2);

        $rate = $readingRate;
        $fixed = $readingFixed;
        if (is_array($template)) {
            if (isset($template['rate_per_unit']) && is_numeric($template['rate_per_unit'])) {
                $rate = round(max(0.0, (float) $template['rate_per_unit']), 2);
            }
            if (isset($template['fixed_charge']) && is_numeric($template['fixed_charge'])) {
                $fixed = round(max(0.0, (float) $template['fixed_charge']), 2);
            }
        }

        return round(($unitsUsed * $rate) + $fixed, 2);
    }

    public function invoicedWaterTotalForUnitTenantMonth(int $unitId, int $tenantId, string $billingYm): float
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $billingYm)) {
            return 0.0;
        }

        $invoices = PmInvoice::query()
            ->where('property_unit_id', $unitId)
            ->where('pm_tenant_id', $tenantId)
            ->where('invoice_type', PmInvoice::TYPE_WATER)
            ->where('billing_period', $billingYm)
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->where(function ($q) {
                $q->whereNull('invoice_kind')
                    ->orWhereIn('invoice_kind', [
                        PmInvoice::KIND_INVOICE,
                        PmInvoice::KIND_WATER_SUPPLEMENT,
                    ]);
            })
            ->get(['id', 'amount']);

        $total = (float) $invoices->sum(fn (PmInvoice $inv) => (float) $inv->amount);

        $invoiceIds = $invoices->pluck('id')->filter()->values();
        if ($invoiceIds->isNotEmpty()) {
            $total += (float) PmInvoice::query()
                ->where('invoice_kind', PmInvoice::KIND_CREDIT_NOTE)
                ->whereIn('original_invoice_id', $invoiceIds)
                ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
                ->sum('amount');
        }

        return round(max(0.0, $total), 2);
    }

    /**
     * Invoiced readings where current property water rate yields more than was billed.
     *
     * @param  array<string, array{rate_per_unit?: float|null, fixed_charge?: float|null, label?: string}>  $waterTemplateByUnit
     * @return list<array{
     *   key: string,
     *   reading_id: int,
     *   billing_month: string,
     *   property_name: string,
     *   unit_label: string,
     *   tenant_name: string,
     *   units_used: float,
     *   reading_amount: float,
     *   expected_amount: float,
     *   invoiced_amount: float,
     *   bill_amount: float,
     *   invoice_id: int|null,
     *   can_bill_supplement: bool,
     * }>
     */
    public function waterRateAdjustmentRows(string $billingMonth, array $waterTemplateByUnit): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
            return [];
        }

        $readings = PmWaterReading::query()
            ->with(['unit.property', 'invoice'])
            ->where('billing_month', $billingMonth)
            ->whereNotNull('pm_invoice_id')
            ->orderBy('property_unit_id')
            ->get();

        $rows = [];

        foreach ($readings as $reading) {
            $unitId = (int) $reading->property_unit_id;
            $template = $waterTemplateByUnit[(string) $unitId] ?? null;
            if (! is_array($template) || ! isset($template['rate_per_unit'])) {
                continue;
            }

            $expected = $this->expectedAmountForReading($reading, $template);
            $readingAmount = round((float) $reading->amount, 2);
            if ($expected <= ($readingAmount + self::AMOUNT_EPSILON)) {
                continue;
            }

            $lease = PmLease::query()
                ->where('status', PmLease::STATUS_ACTIVE)
                ->whereHas('units', fn ($q) => $q->where('property_units.id', $unitId))
                ->with('pmTenant:id,name')
                ->first();

            if (! $lease?->pm_tenant_id) {
                continue;
            }

            $tenantId = (int) $lease->pm_tenant_id;
            $invoiced = $this->invoicedWaterTotalForUnitTenantMonth($unitId, $tenantId, $billingMonth);
            if ($invoiced >= ($expected - self::AMOUNT_EPSILON)) {
                continue;
            }

            $delta = round($expected - $invoiced, 2);
            if ($delta <= self::AMOUNT_EPSILON) {
                continue;
            }

            $rows[] = [
                'key' => (string) $reading->id,
                'reading_id' => (int) $reading->id,
                'billing_month' => $billingMonth,
                'property_name' => (string) ($reading->unit?->property?->name ?? '—'),
                'unit_label' => (string) ($reading->unit?->label ?? '—'),
                'tenant_name' => (string) ($lease->pmTenant?->name ?? '—'),
                'units_used' => (float) $reading->units_used,
                'reading_amount' => $readingAmount,
                'expected_amount' => $expected,
                'invoiced_amount' => $invoiced,
                'bill_amount' => $delta,
                'invoice_id' => $reading->pm_invoice_id ? (int) $reading->pm_invoice_id : null,
                'can_bill_supplement' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>|null  $readingIds
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function generateWaterSupplements(
        string $billingMonth,
        array $waterTemplateByUnit,
        ?array $readingIds = null,
        ?User $actor = null,
        ?string $dueDate = null,
        ?int $utilityOverrideRequestId = null,
    ): array {
        app(UtilityPeriodGuardService::class)->assertMutable(
            $billingMonth,
            UtilityPeriodGuardService::ACTION_GENERATE_INVOICE,
            $actor,
            $utilityOverrideRequestId,
            'utility_billing_period',
            null,
        );

        $rows = collect($this->waterRateAdjustmentRows($billingMonth, $waterTemplateByUnit));
        if ($readingIds !== null) {
            $idSet = array_fill_keys(array_map('intval', $readingIds), true);
            $rows = $rows->filter(fn (array $r) => isset($idSet[(int) $r['reading_id']]));
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            try {
                $invoice = $this->createWaterSupplementForRow($row, $billingMonth, $dueDate, $actor);
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
     * @param  array<string, mixed>  $row
     */
    private function createWaterSupplementForRow(array $row, string $billingMonth, ?string $dueDate, ?User $actor): ?PmInvoice
    {
        $reading = PmWaterReading::query()
            ->with(['unit.property', 'invoice'])
            ->find($row['reading_id'] ?? 0);

        if (! $reading || (string) $reading->billing_month !== $billingMonth) {
            return null;
        }

        $lease = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->where('property_units.id', (int) $reading->property_unit_id))
            ->first();

        if (! $lease?->pm_tenant_id) {
            return null;
        }

        $tenantId = (int) $lease->pm_tenant_id;
        $unitId = (int) $reading->property_unit_id;
        $expected = round((float) ($row['expected_amount'] ?? 0), 2);
        $invoiced = $this->invoicedWaterTotalForUnitTenantMonth($unitId, $tenantId, $billingMonth);
        $amount = round($expected - $invoiced, 2);

        if ($amount <= self::AMOUNT_EPSILON) {
            return null;
        }

        $issueDate = PmInvoice::issueDateForBillingPeriod($billingMonth);
        $dueDate = $dueDate ?: ($reading->invoice?->due_date?->toDateString() ?? $issueDate);
        if (\Carbon\Carbon::parse($dueDate)->lt(\Carbon\Carbon::parse($issueDate))) {
            $dueDate = $issueDate;
        }

        return DB::transaction(function () use ($reading, $lease, $amount, $billingMonth, $issueDate, $dueDate, $actor, $invoiced, $expected, $row) {
            $agentUserId = $reading->unit?->property?->agent_user_id;
            $anchorId = $reading->pm_invoice_id ? (int) $reading->pm_invoice_id : null;

            $invoice = PmInvoice::query()->create([
                'pm_lease_id' => $lease->id,
                'property_unit_id' => (int) $reading->property_unit_id,
                'pm_tenant_id' => (int) $lease->pm_tenant_id,
                'agent_user_id' => $agentUserId,
                'invoice_no' => PmInvoice::nextInvoiceNumber(),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'amount' => $amount,
                'amount_paid' => 0,
                'subtotal_amount' => $amount,
                'total_amount' => $amount,
                'status' => PmInvoice::STATUS_SENT,
                'sent_at' => now(),
                'invoice_type' => PmInvoice::TYPE_WATER,
                'invoice_kind' => PmInvoice::KIND_WATER_SUPPLEMENT,
                'original_invoice_id' => $anchorId,
                'billing_period' => $billingMonth,
                'description' => 'Water supplement (rate correction) · '.$billingMonth
                    .' · was KES '.number_format($invoiced, 2).' → KES '.number_format($expected, 2),
            ]);
            $invoice->refreshComputedStatus();

            PropertyAccountingPostingService::postInvoiceIssued($invoice, $actor);

            if ($this->tenantCreditService->isEnabled()) {
                $this->tenantCreditService->autoApplyForTenant(
                    (int) $lease->pm_tenant_id,
                    $actor,
                    (int) $invoice->id,
                );
            }

            PmInvoiceEvent::record(
                (int) $invoice->id,
                PmInvoiceEvent::EVENT_ISSUED,
                $actor?->id,
                'Water supplement for billing month '.$billingMonth,
                [
                    'source' => 'revenue.utilities.water_supplement',
                    'water_reading_id' => (int) $reading->id,
                    'expected' => $expected,
                    'previously_invoiced' => $invoiced,
                    'supplement_amount' => $amount,
                ]
            );

            UtilityAuditLog::record('water_supplement_issued', 'pm_invoice', (int) $invoice->id, [
                'billing_month' => $billingMonth,
                'property_unit_id' => (int) $reading->property_unit_id,
                'pm_tenant_id' => (int) $lease->pm_tenant_id,
                'water_reading_id' => (int) $reading->id,
                'actor_user_id' => $actor?->id,
                'payload' => $row,
            ]);

            return $invoice;
        });
    }

    /**
     * @return array<string, array{rate_per_unit?: float, fixed_charge?: float, label?: string}>
     */
    public function waterTemplateByUnitFromSettings(): array
    {
        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $byUnit = [];
        foreach (PropertyUnit::query()->select(['id', 'property_id'])->get() as $unit) {
            $templates = (array) ($decoded[(string) $unit->property_id] ?? []);
            foreach ($templates as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $scopeUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== ''
                    ? (int) $row['property_unit_id']
                    : null;
                if ($scopeUnitId !== null && $scopeUnitId !== (int) $unit->id) {
                    continue;
                }
                if (strtolower(trim((string) ($row['charge_type'] ?? ''))) !== 'water') {
                    continue;
                }
                $byUnit[(string) $unit->id] = [
                    'rate_per_unit' => is_numeric($row['rate_per_unit'] ?? null) ? (float) $row['rate_per_unit'] : 0.0,
                    'fixed_charge' => is_numeric($row['fixed_charge'] ?? null) ? (float) $row['fixed_charge'] : 0.0,
                    'label' => trim((string) ($row['label'] ?? '')),
                ];
            }
        }

        return $byUnit;
    }
}
