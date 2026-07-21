<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmLease;
use App\Models\PmWaterReading;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Models\UtilityAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WaterBillingService
{
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
                    'issue_date' => now()->toDateString(),
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
}
