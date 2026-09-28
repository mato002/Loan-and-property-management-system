<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\DeriveExpenseRulesFromStandingChargesService;
use Illuminate\Console\Command;

class DeriveExpenseRulesFromStandingChargesCommand extends Command
{
    protected $signature = 'property:derive-expense-rules
        {--agent-user-id= : Passion agent staff user id (required)}
        {--dry-run : Preview rules without saving}
        {--force : Confirm write}';

    protected $description = 'Create property/unit expense rules from migrated lease standing charges (garbage / service charge).';

    public function handle(DeriveExpenseRulesFromStandingChargesService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        if ($agentUserId <= 0) {
            $this->error('Pass --agent-user-id=ID.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! $this->option('force')) {
            $this->error('Preview with --dry-run or save with --force.');

            return self::FAILURE;
        }

        $agent = User::query()->find($agentUserId);
        $this->info(sprintf(
            'Agent #%d (%s): derive garbage/service rules from lease extras.',
            $agentUserId,
            $agent?->email ?? 'unknown',
        ));

        try {
            $result = $service->derive($agentUserId, (bool) $this->option('dry-run'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $prefix = $result['dry_run'] ? '[DRY RUN] ' : '';
        $this->info(sprintf(
            '%s%d properties · %d property-wide rules · %d unit rules',
            $prefix,
            $result['properties'],
            $result['property_wide'],
            $result['unit_scoped'],
        ));

        foreach ($result['warnings'] as $warning) {
            $this->comment('  · '.$warning);
        }

        $rows = collect($result['rules'])->map(fn (array $rule): array => [
            $rule['property_code'],
            $rule['scope'],
            $rule['charge_key'],
            number_format((float) $rule['amount_value'], 2),
            $rule['property_unit_id'] === null ? 'all units' : '#'.$rule['property_unit_id'],
        ]);
        if ($rows->isNotEmpty()) {
            $this->table(['Property', 'Scope', 'Type', 'Amount', 'Unit'], $rows->all());
        }

        if (! $result['dry_run']) {
            $this->info('Rules saved to Settings → Expense charge rules (and utility templates).');
            $this->comment('Monthly generation: php artisan utility:materialize-attached-charges --month=YYYY-MM');
        }

        return self::SUCCESS;
    }
}
