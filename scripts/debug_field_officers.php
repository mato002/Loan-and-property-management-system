<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\PmFieldOfficer::withoutGlobalScopes()->orderBy('name')->get() as $officer) {
    $stats = $officer->portfolioStats();
    echo "{$officer->name} ({$officer->phone})\n";
    echo "  landlords={$stats['landlords']} properties={$stats['properties']} units={$stats['units']} tenants={$stats['tenants']} rent=".number_format($stats['rent_portfolio'], 2)."\n";
}
