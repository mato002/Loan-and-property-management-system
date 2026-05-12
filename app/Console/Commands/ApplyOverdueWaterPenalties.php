<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPenaltyRule;
use App\Models\PropertyPortalSetting;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class ApplyOverdueWaterPenalties extends Command
{
    protected $signature = 'water:apply-penalties {--date= : As-of date YYYY-MM-DD (default: today)}';

    protected $description = 'Apply active water penalty rule(s) to overdue, unpaid water invoices.';

    public function handle(): int
    {
        $enabled = PropertyPortalSetting::isWaterPenaltyAutomationEnabled();
        if (! $enabled) {
            $this->info('Water penalty automation is off (workflow toggles or PROPERTY_WORKFLOW_AUTOMATION_ENABLED). Skipping water penalties.');

            return self::SUCCESS;
        }

        $today = (string) ($this->option('date') ?: now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            $this->error('Invalid --date. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $rules = PmPenaltyRule::query()
            ->where('is_active', true)
            ->where('scope', 'water')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            $this->info('No active penalty rules with scope=water. Nothing to apply.');

            return self::SUCCESS;
        }

        $applied = 0;
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
                $base = max(0, (float) $invoice->amount - (float) $invoice->amount_paid);
                if ($base <= 0) {
                    continue;
                }

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

                if ($penalty <= 0) {
                    continue;
                }

                // A4: idempotency guard. The unique key
                // (pm_invoice_id, pm_penalty_rule_id, threshold_date) on the
                // applications table prevents re-running the cron from
                // stacking penalties for the same invoice/rule/cutoff.
                try {
                    PmInvoicePenaltyApplication::query()->create([
                        'pm_invoice_id' => (int) $invoice->id,
                        'pm_penalty_rule_id' => (int) $rule->id,
                        'threshold_date' => $threshold,
                        'amount' => round($penalty, 2),
                        'applied_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    // Duplicate => already applied for this rule + threshold.
                    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                        continue;
                    }
                    throw $e;
                }

                $invoice->amount = (float) $invoice->amount + $penalty;
                $invoice->total_amount = (float) ($invoice->total_amount ?? $invoice->amount);
                $invoice->description = trim(((string) $invoice->description).' | Water penalty '.$rule->name.' '.$today);
                $invoice->save();
                $invoice->refreshComputedStatus();

                PmInvoiceEvent::record(
                    (int) $invoice->id,
                    PmInvoiceEvent::EVENT_PENALTY_APPLIED,
                    null,
                    sprintf('Penalty applied: %s (KES %s)', $rule->name, number_format($penalty, 2)),
                    [
                        'rule_id' => (int) $rule->id,
                        'rule_name' => $rule->name,
                        'threshold_date' => $threshold,
                        'amount' => round($penalty, 2),
                        'new_invoice_amount' => (float) $invoice->amount,
                    ]
                );

                $applied++;
            }
        }

        $this->info("Water penalties applied to {$applied} invoice(s).");

        return self::SUCCESS;
    }
}
