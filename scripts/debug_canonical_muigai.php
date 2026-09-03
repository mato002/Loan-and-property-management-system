<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\PassionLegacyImportReconciliationService;
use App\Services\Property\PassionLegacyLeasesRegisterParser;
use App\Services\Property\PassionLegacyRegisterPdfTextExtractor;
use App\Services\Property\PassionLegacyTextNormalizer;
use App\Services\Property\PassionLegacyUnitRegisterParser;
use App\Services\Property\PassionPropertyCodeResolver;

$agentUserId = 1;
$unitsPath = storage_path('passion-legacy/property_unit_register.txt');
$leasesPath = storage_path('passion-legacy/leases.txt');

$unitParser = app(PassionLegacyUnitRegisterParser::class);
$leasesParser = app(PassionLegacyLeasesRegisterParser::class);
$extractor = app(PassionLegacyRegisterPdfTextExtractor::class);
$codeResolver = app(PassionPropertyCodeResolver::class);

$propertyRegister = app(\App\Services\Property\PassionLegacyRegisterParser::class)
    ->parse($extractor->extract(storage_path('passion-legacy/property_register.txt')));
$unitRecords = $unitParser->parse($extractor->extract($unitsPath));
$leaseRecords = $leasesParser->parse($extractor->extract($leasesPath));

$expectedUnits = [];
foreach ($unitRecords as $record) {
    $property = $codeResolver->resolveByNameViaRegister($record['property_name'], $propertyRegister);
    if (! $property) {
        continue;
    }
    $label = PassionLegacyTextNormalizer::normalizeUnitLabel($record['unit_label']);
    $expectedUnits[$property->id.'|'.$label] = [
        'property_id' => $property->id,
        'label' => $label,
        'status' => $record['status'],
    ];
}

$property = Property::withoutGlobalScopes()->where('code', 'M00001A')->first();

foreach (['HSE 10', 'HSE 11'] as $leaseLabel) {
    foreach ($leaseRecords as $record) {
        if ($record['property_code'] !== 'M00001A' || $record['unit_label'] !== $leaseLabel) {
            continue;
        }
        $label = PassionLegacyTextNormalizer::normalizeUnitLabel($record['unit_label']);
        $canonical = null;
        foreach ($expectedUnits as $expected) {
            if ($expected['property_id'] !== $property->id) {
                continue;
            }
            if (PassionLegacyTextNormalizer::registerUnitLabelMatch($expected['label'], $leaseLabel)) {
                $canonical = PropertyUnit::withoutGlobalScopes()->where('property_id', $property->id)->get()
                    ->first(fn ($u) => PassionLegacyTextNormalizer::unitLabelsMatch($u->label, $expected['label']));
                break;
            }
        }
        $tenant = PmTenant::withoutGlobalScopes()->where('account_number', strtoupper($record['account_number']))->first();
        $lease = PmLease::withoutGlobalScopes()->where('pm_tenant_id', $tenant->id)->where('status', 'active')->with('units')->first();
        $current = $lease?->units->first();
        echo "$leaseLabel acct={$record['account_number']} canonical=".($canonical?->label ?? 'null')." id=".($canonical?->id ?? '-')." current=".($current?->label ?? '-')." id=".($current?->id ?? '-')."\n";
        echo "  registerUnitLabelMatch HSE 10 vs HSE 1: ".(PassionLegacyTextNormalizer::registerUnitLabelMatch('HSE 10', 'HSE 1') ? 'yes' : 'no')."\n";
    }
}
