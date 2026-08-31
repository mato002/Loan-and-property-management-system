<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Property\PropertyAccountingPostingService;
use Illuminate\Support\Facades\Schema;

$steps = [
    'repair_missing_database_tables' => __DIR__ . '/../database/migrations/core/2025_03_30_000000_repair_missing_database_tables.php',
    'add_hierarchy_fields_to_accounting_chart_accounts' => __DIR__ . '/../database/migrations/loan/2026_04_24_170000_add_hierarchy_fields_to_accounting_chart_accounts.php',
    'add_display_fields_to_accounting_chart_accounts' => __DIR__ . '/../database/migrations/loan/2026_04_24_160000_add_display_fields_to_accounting_chart_accounts.php',
    'upgrade_property_accounting_to_trust_gl' => __DIR__ . '/../database/migrations/property/2026_05_05_095500_upgrade_property_accounting_to_trust_gl.php',
];

foreach ($steps as $label => $path) {
    echo "Running {$label}...\n";
    $migration = require $path;
    $migration->up();
    echo "Done {$label}.\n";
}

foreach (['accounting_chart_accounts', 'accounting_journal_batches', 'accounting_journal_lines'] as $table) {
    echo $table . ':' . (Schema::hasTable($table) ? 'yes' : 'no') . PHP_EOL;
}

echo 'ledgerReady:' . (PropertyAccountingPostingService::ledgerReady() ? 'yes' : 'no') . PHP_EOL;
