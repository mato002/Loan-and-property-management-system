<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return array<string, list<string>>
     */
    private function legacyToGranularMap(): array
    {
        return [
            'loanbook.view' => [
                'loan_applications.view', 'loan_applications.create', 'loan_applications.update',
                'loans.view', 'loans.create', 'loans.update',
                'disbursements.view', 'collections.view',
            ],
            'clients.view' => ['clients.view', 'clients.create', 'clients.update'],
            'payments.view' => ['payments.view', 'payments.create', 'payments.update', 'collections.create', 'collections.update'],
            'accounting.view' => ['accounting.view', 'journals.view', 'chart_of_accounts.view'],
            'financial.view' => ['accounting.approve', 'journals.approve', 'reports.view'],
            'analytics.view' => ['reports.view', 'reports.export'],
            'branches.view' => ['branches.view'],
            'system.help.view' => ['system_setup.view', 'access_roles.view', 'audit_logs.view'],
            'dashboard.view' => ['dashboard.view'],
            'employees.view' => ['employees.view'],
            'bulksms.view' => ['bulksms.view'],
            'my_account.view' => ['my_account.view'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function defaultsByBaseRole(): array
    {
        return [
            'admin' => ['*'],
            'manager' => ['*'],
            'accountant' => [
                'dashboard.view', 'clients.view', 'payments.view', 'payments.create', 'payments.update',
                'accounting.view', 'accounting.approve', 'financial.view',
                'journals.view', 'journals.create', 'journals.update', 'journals.approve',
                'chart_of_accounts.view', 'reports.view', 'reports.export',
                'audit_logs.view', 'my_account.view', 'system.help.view',
            ],
            'officer' => [
                'dashboard.view', 'employees.view',
                'clients.view', 'clients.create', 'clients.update',
                'loan_applications.view', 'loan_applications.create', 'loan_applications.update',
                'loans.view', 'loans.create', 'loans.update',
                'disbursements.view', 'collections.view', 'collections.create', 'collections.update',
                'payments.view', 'bulksms.view', 'branches.view', 'reports.view',
                'my_account.view', 'system.help.view',
            ],
            'user' => [
                'dashboard.view', 'clients.view',
                'loan_applications.view', 'loan_applications.create',
                'loans.view', 'collections.view', 'payments.view',
                'bulksms.view', 'my_account.view', 'system.help.view',
            ],
            'applicant' => ['dashboard.view', 'my_account.view', 'system.help.view'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('loan_roles')) {
            return;
        }

        $legacyMap = $this->legacyToGranularMap();
        $defaults = $this->defaultsByBaseRole();

        DB::table('loan_roles')->orderBy('id')->chunkById(100, function ($rows) use ($legacyMap, $defaults): void {
            foreach ($rows as $row) {
                $raw = $row->permissions;
                $items = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
                if (! is_array($items)) {
                    $items = [];
                }

                $current = [];
                foreach ($items as $key) {
                    $k = strtolower(trim((string) $key));
                    if ($k !== '') {
                        $current[] = $k;
                    }
                }
                $current = array_values(array_unique($current));

                if ($current === []) {
                    $base = strtolower(trim((string) ($row->base_role ?? 'user')));
                    $next = $defaults[$base] ?? $defaults['user'];
                    DB::table('loan_roles')->where('id', $row->id)->update([
                        'permissions' => json_encode(array_values(array_unique($next))),
                        'updated_at' => now(),
                    ]);
                    continue;
                }

                if (in_array('*', $current, true)) {
                    continue;
                }

                $next = $current;
                foreach ($current as $existingKey) {
                    foreach (($legacyMap[$existingKey] ?? []) as $mappedKey) {
                        $next[] = $mappedKey;
                    }
                }
                $next = array_values(array_unique($next));

                if ($next !== $current) {
                    DB::table('loan_roles')->where('id', $row->id)->update([
                        'permissions' => json_encode($next),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Backfill only; no destructive rollback.
    }
};

