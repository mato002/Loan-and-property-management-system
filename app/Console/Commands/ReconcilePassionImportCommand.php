<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionLegacyImportReconciliationService;
use Illuminate\Console\Command;

class ReconcilePassionImportCommand extends Command
{
    protected $signature = 'property:reconcile-passion-import
                            {--agent-user-id= : Passion agent staff user id}
                            {--units= : Path to unit register .txt (default: storage/passion-legacy/property_unit_register.txt)}
                            {--leases= : Path to leases register .txt (default: storage/passion-legacy/leases.txt)}
                            {--dry-run : Report changes without saving}';

    protected $description = 'Reconcile Passion units and lease links against register exports (fixes extras, duplicates, wrong-property links)';

    public function handle(PassionLegacyImportReconciliationService $reconciler): int
    {
        $agentUserId = $this->resolveAgentUserId();
        if ($agentUserId <= 0) {
            $this->error('Pass --agent-user-id=ID for the Passion staff account.');

            return self::FAILURE;
        }

        $unitsPath = (string) ($this->option('units') ?: storage_path('passion-legacy/property_unit_register.txt'));
        $leasesPath = (string) ($this->option('leases') ?: storage_path('passion-legacy/leases.txt'));

        foreach ([$unitsPath => 'units', $leasesPath => 'leases'] as $path => $label) {
            if (! is_file($path)) {
                $this->error("{$label} register not found: {$path}");

                return self::FAILURE;
            }
        }

        $result = $reconciler->reconcile(
            $agentUserId,
            $unitsPath,
            $leasesPath,
            (bool) $this->option('dry-run'),
        );

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $prefix = $result['dry_run'] ? '[DRY RUN] ' : '';
        $this->info(sprintf(
            '%sExpected units=%d | DB units %d -> %d | Units created=%d | Leases relinked=%d | Labels aligned=%d | Duplicates removed=%d | Orphans removed=%d | Statuses synced=%d',
            $prefix,
            $result['expected_units'],
            $result['db_units_before'],
            $result['db_units_after'],
            $result['units_created'],
            $result['leases_relinked'],
            $result['labels_aligned'],
            $result['duplicate_units_removed'],
            $result['orphan_units_removed'],
            $result['statuses_synced'],
        ));

        if ($result['missing_active_leases'] !== []) {
            $this->warn('Missing active leases ('.count($result['missing_active_leases']).'):');
            foreach (array_slice($result['missing_active_leases'], 0, 20) as $account) {
                $this->line('  - '.$account);
            }
            if (count($result['missing_active_leases']) > 20) {
                $this->line('  ... and '.(count($result['missing_active_leases']) - 20).' more');
            }
        }

        return self::SUCCESS;
    }

    private function resolveAgentUserId(): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        if ($agentUserId > 0) {
            return $agentUserId;
        }

        return (int) (User::query()->where('is_super_admin', true)->orderBy('id')->value('id')
            ?: User::query()->orderBy('id')->value('id')
            ?: 0);
    }
}
