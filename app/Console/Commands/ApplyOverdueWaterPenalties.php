<?php

namespace App\Console\Commands;

use App\Models\PropertyPortalSetting;
use App\Services\Property\WaterPenaltyService;
use Illuminate\Console\Command;

class ApplyOverdueWaterPenalties extends Command
{
    protected $signature = 'water:apply-penalties {--date= : As-of date YYYY-MM-DD (default: today)} {--preview : Preview only, do not apply}';

    protected $description = 'Apply active water penalty rule(s) to overdue, unpaid water invoices.';

    public function handle(WaterPenaltyService $penalties): int
    {
        $enabled = PropertyPortalSetting::isWaterPenaltyAutomationEnabled();
        if (! $enabled && ! $this->option('preview')) {
            $this->info('Water penalty automation is off. Skipping water penalties.');

            return self::SUCCESS;
        }

        $today = (string) ($this->option('date') ?: now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            $this->error('Invalid --date. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        if ($this->option('preview')) {
            $simulation = $penalties->simulate($today);
            $rows = $simulation['rows'];
            if ($rows->isEmpty()) {
                $this->info('No penalties would be applied.');

                return self::SUCCESS;
            }
            if ($simulation['warnings'] !== []) {
                $this->warn('Operator warnings:');
                foreach ($simulation['warnings'] as $warning) {
                    $this->line(' - '.$warning);
                }
            }
            $this->table(['Invoice', 'Base', 'Penalty', 'Rule', 'Mode', 'Days'], $rows->map(fn ($r) => [
                $r['invoice_no'],
                number_format($r['base'], 2),
                number_format($r['penalty'], 2),
                $r['rule'],
                str_replace('_', ' ', (string) ($r['compounding_mode'] ?? 'simple')),
                (string) ($r['days_overdue'] ?? '0'),
            ])->all());

            return self::SUCCESS;
        }

        $stats = $penalties->apply($today, null, 'water:apply-penalties');
        $this->info("Water penalties for {$today}: applied={$stats['applied']}, skipped={$stats['skipped']}.");

        return self::SUCCESS;
    }
}
