<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Services\Property\PassionLegacyTextNormalizer;

$code = $argv[1] ?? 'F00047A';
$p = Property::withoutGlobalScopes()->where('code', $code)->first();

foreach (['TNT001308', 'TNT001256', 'TNT001257', 'TNT001258', 'TNT001259', 'TNT001260'] as $acct) {
    $t = PmTenant::withoutGlobalScopes()->where('account_number', $acct)->first();
    if (! $t) {
        echo "$acct: no tenant\n";
        continue;
    }
    $lease = PmLease::withoutGlobalScopes()->where('pm_tenant_id', $t->id)->where('status', 'active')->with('units')->first();
    $unit = $lease?->units->first();
    echo "$acct {$t->name} -> ".($unit?->label ?? 'no lease')." prop=".($unit?->property_id ?? '-')."\n";
}

echo "\nUnit label match HSE 3 vs HSE 3 (2BR): ";
echo PassionLegacyTextNormalizer::unitLabelsMatch('HSE 3', 'HSE 3 (2BR)') ? 'unitLabelsMatch yes' : 'unitLabelsMatch no';
echo ' | registerUnitLabelMatch: ';
echo PassionLegacyTextNormalizer::registerUnitLabelMatch('HSE 3 (2BR)', 'HSE 3') ? 'yes' : 'no';
echo "\n";
