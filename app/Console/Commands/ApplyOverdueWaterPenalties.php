<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmPenaltyRule;
use App\Models\PropertyPortalSetting;
use Illuminate\Console\Command;

class ApplyOverdueWaterPenalties extends Command
{
    protected $signature = 'water:apply-penalties {--date= : As-of date YYYY-MM-DD (default: today)}';

    protected $description = 'Apply active water penalty rule(s) to overdue, unpaid water invoices.';

    public function handle(): int
    {
        $enabled = PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1';
        if (! $enabled) {
            $this->info('Auto workflows disabled (workflow_auto_reminders=0). Skipping water penalties.');
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

                $invoice->amount = (float) $invoice->amount + $penalty;
                $invoice->description = trim(((string) $invoice->description).' | Water penalty '.$rule->name.' '.$today);
                $invoice->save();
                $invoice->refreshComputedStatus();

                $applied++;
            }
        }

        $this->info("Water penalties applied to {$applied} invoice(s).");
        return self::SUCCESS;
    }
}

