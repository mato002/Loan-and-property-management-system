<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tablesInOrder = [
    'pm_payment_allocations',
    'pm_payments',
    'pm_invoices',
    'pm_lease_unit',
    'pm_leases',
    'pm_maintenance_jobs',
    'pm_maintenance_requests',
    'deposit_definition_property_unit',
    'property_unit_expense_rules',
    'property_landlord',
    'property_units',
    'properties',
];

DB::statement('SET FOREIGN_KEY_CHECKS=0');

foreach ($tablesInOrder as $table) {
    if (! Schema::hasTable($table)) {
        continue;
    }
    $count = DB::table($table)->count();
    DB::table($table)->truncate();
    echo "Truncated {$table} ({$count} rows)\n";
}

DB::statement('SET FOREIGN_KEY_CHECKS=1');

if (Schema::hasTable('property_portal_settings')) {
    $row = DB::table('property_portal_settings')
        ->where('key', 'commission_property_overrides_json')
        ->whereNull('agent_user_id')
        ->first();

    if ($row) {
        DB::table('property_portal_settings')
            ->where('id', $row->id)
            ->update(['value' => '[]']);
        echo "Reset commission_property_overrides_json\n";
    }
}

echo 'Done. properties: '.DB::table('properties')->count()."\n";
