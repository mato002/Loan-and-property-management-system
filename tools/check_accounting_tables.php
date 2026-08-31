<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = [
    'accounting_chart_accounts',
    'accounting_journal_entries',
    'accounting_journal_lines',
    'accounting_journal_batches',
    'accounting_periods',
    'pm_accounting_entries',
];

foreach ($tables as $table) {
    echo $table . ':' . (Illuminate\Support\Facades\Schema::hasTable($table) ? 'yes' : 'no') . PHP_EOL;
}
