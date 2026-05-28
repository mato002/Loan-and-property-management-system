<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPenaltyRule;
use App\Models\User;
use App\Models\UtilityAuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WaterPenaltyService
{
    /**
     * Preview penalties without applying (dry run).
     *
     * @return Collection<int, array{invoice_id: int, invoice_no: string, base: float, penalty: float, rule: string}>
     */
    public function preview(?string $asOfDate = null): Collection
    {
        $today = $asOfDate ?: now()->toDateString();
        $rules = $this->activeWaterRules();
        $previews = collect();

        foreach ($rules as $rule) {
            $threshold = now()->parse($today)->subDays((int) ($rule->grace_days ?? 0))->toDateString();

            PmInvoice::query()
                ->where('invoice_type', PmInvoice::TYPE_WATER)
                ->whereColumn('amount_paid', '<', 'amount')
                ->whereDate('due_date', '<', $threshold)
                ->orderBy('due_date')
                ->limit(1000)
                ->get()
                ->each(function (PmInvoice $invoice) use ($rule, $threshold, $previews, $today) {
                    $already = PmInvoicePenaltyApplication::query()
                        ->where('pm_invoice_id', $invoice->id)
                        ->where('pm_penalty_rule_id', $rule->id)
                        ->where('threshold_date', $threshold)
                        ->whereNull('reversed_at')
                        ->exists();
                    if ($already) {
                        return;
                    }

                    $base = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
                    $penalty = $this->calculatePenalty($rule, $base);
                    if ($penalty <= 0) {
                        return;
                    }

                    $previews->push([
                        'invoice_id' => (int) $invoice->id,
                        'invoice_no' => (string) $invoice->invoice_no,
                        'base' => round($base, 2),
                        'penalty' => round($penalty, 2),
                        'rule' => (string) $rule->name,
                        'threshold_date' => $threshold,
                        'as_of' => $today,
                    ]);
                });
        }

        return $previews;
    }

    /**
     * @return array{applied: int, skipped: int}
     */
    public function apply(?string $asOfDate = null, ?User $actor = null, string $source = 'manual'): array
    {
        $today = $asOfDate ?: now()->toDateString();
        $rules = $this->activeWaterRules();
        $applied = 0;
        $skipped = 0;

        foreach ($rules as $rule) {
            $graceDays = (int) ($rule->grace_days ?? 0);
            $threshold = now()->parse($today)->subDays($graceDays)->toDateString();

            $invoices = PmInvoice::query()
                ->where('invoice_type', PmInvoice::TYPE_WATER)
                ->whereColumn('amount_paid', '<', 'amount')
                ->whereDate('due_date', '<', $threshold)
                ->orderBy('due_date')
                ->limit(1000)
                ->get();

            foreach ($invoices as $invoice) {
                $result = $this->applyPenaltyToInvoice($invoice, $rule, $threshold, $today, $actor, $source);
                if ($result) {
                    $applied++;
                } else {
                    $skipped++;
                }
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    public function reverseApplication(PmInvoicePenaltyApplication $application, ?User $actor = null, ?string $reason = null, ?int $utilityOverrideRequestId = null): bool
    {
        if ($application->reversed_at) {
            return false;
        }

        $application->loadMissing('invoice');
        if ($application->invoice) {
            app(UtilityPeriodGuardService::class)->assertInvoiceMutable(
                $application->invoice,
                UtilityPeriodGuardService::ACTION_REVERSE_PENALTY,
                $actor,
                $utilityOverrideRequestId,
            );
        }

        return DB::transaction(function () use ($application, $actor, $reason) {
            $application = PmInvoicePenaltyApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->reversed_at) {
                return false;
            }

            $invoice = PmInvoice::query()->whereKey($application->pm_invoice_id)->lockForUpdate()->firstOrFail();
            $penaltyAmount = round((float) $application->amount, 2);
            if ($penaltyAmount <= 0) {
                return false;
            }

            $invoice->amount = max(0.0, round((float) $invoice->amount - $penaltyAmount, 2));
            $invoice->total_amount = $invoice->amount;
            $invoice->description = trim(preg_replace('/\s*\|\s*Water penalty[^|]*/', '', (string) $invoice->description) ?: $invoice->description);
            $invoice->save();
            $invoice->refreshComputedStatus();

            PropertyAccountingPostingService::reverseWaterPenalty($invoice, $penaltyAmount, (int) $application->id, $actor, $reason);

            $application->update([
                'reversed_at' => now(),
                'reversed_by' => $actor?->id,
                'reversal_reason' => $reason ?: 'Penalty waived/reversed',
            ]);

            PmInvoiceEvent::record(
                (int) $invoice->id,
                PmInvoiceEvent::EVENT_PENALTY_APPLIED,
                $actor?->id,
                'Water penalty reversed: KES '.number_format($penaltyAmount, 2),
                ['penalty_application_id' => (int) $application->id, 'reversed' => true]
            );

            UtilityAuditLog::record('penalty_reversed', 'pm_invoice_penalty_application', (int) $application->id, [
                'pm_invoice_id' => (int) $invoice->id,
                'actor_user_id' => $actor?->id,
                'payload' => ['amount' => $penaltyAmount, 'reason' => $reason],
            ]);

            return true;
        });
    }

    private function applyPenaltyToInvoice(
        PmInvoice $invoice,
        PmPenaltyRule $rule,
        string $threshold,
        string $today,
        ?User $actor,
        string $source,
    ): bool {
        $base = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
        if ($base <= 0) {
            return false;
        }

        $penalty = $this->calculatePenalty($rule, $base);
        if ($penalty <= 0) {
            return false;
        }

        try {
            return DB::transaction(function () use ($invoice, $rule, $threshold, $today, $actor, $source, $penalty) {
                app(UtilityPeriodGuardService::class)->assertInvoiceMutable(
                    $invoice,
                    UtilityPeriodGuardService::ACTION_APPLY_PENALTY,
                    $actor,
                );

                $application = PmInvoicePenaltyApplication::query()->create([
                    'pm_invoice_id' => (int) $invoice->id,
                    'pm_penalty_rule_id' => (int) $rule->id,
                    'threshold_date' => $threshold,
                    'amount' => round($penalty, 2),
                    'applied_at' => now(),
                ]);

                $invoice = PmInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                $invoice->amount = round((float) $invoice->amount + $penalty, 2);
                $invoice->total_amount = $invoice->amount;
                $invoice->description = trim(((string) $invoice->description).' | Water penalty '.$rule->name.' '.$today);
                $invoice->save();
                $invoice->refreshComputedStatus();

                PropertyAccountingPostingService::postWaterPenalty($invoice, $penalty, (int) $application->id, $actor);

                PmInvoiceEvent::record(
                    (int) $invoice->id,
                    PmInvoiceEvent::EVENT_PENALTY_APPLIED,
                    $actor?->id,
                    sprintf('Water penalty applied: %s (KES %s)', $rule->name, number_format($penalty, 2)),
                    [
                        'rule_id' => (int) $rule->id,
                        'application_id' => (int) $application->id,
                        'source' => $source,
                        'threshold_date' => $threshold,
                    ]
                );

                UtilityAuditLog::record('penalty_applied', 'pm_invoice_penalty_application', (int) $application->id, [
                    'pm_invoice_id' => (int) $invoice->id,
                    'actor_user_id' => $actor?->id,
                    'payload' => ['amount' => $penalty, 'rule' => $rule->name, 'source' => $source],
                ]);

                return true;
            });
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    private function calculatePenalty(PmPenaltyRule $rule, float $base): float
    {
        $penalty = 0.0;
        if (in_array($rule->formula, ['flat', 'fixed'], true)) {
            $penalty = (float) ($rule->amount ?? 0);
        } else {
            $penalty = $base * (((float) ($rule->percent ?? 0)) / 100);
            if ((float) ($rule->amount ?? 0) > 0) {
                $penalty += (float) $rule->amount;
            }
        }

        if ((float) ($rule->cap ?? 0) > 0) {
            $penalty = min($penalty, (float) $rule->cap);
        }

        return round(max(0.0, $penalty), 2);
    }

    /**
     * @return \Illuminate\Support\Collection<int, PmPenaltyRule>
     */
    private function activeWaterRules()
    {
        return PmPenaltyRule::query()
            ->where('is_active', true)
            ->where('scope', 'water')
            ->orderBy('id')
            ->get();
    }
}
