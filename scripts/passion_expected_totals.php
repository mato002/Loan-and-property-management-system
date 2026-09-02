<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);

$properties = app(App\Services\Property\PassionLegacyRegisterParser::class)
    ->parse($extractor->extract(storage_path('passion-legacy/property_register.txt')));
$landlords = app(App\Services\Property\PassionLegacyLandlordRegisterParser::class)
    ->parse($extractor->extract(storage_path('passion-legacy/landlord_register.txt')));
$units = app(App\Services\Property\PassionLegacyUnitRegisterParser::class)
    ->parse($extractor->extract(storage_path('passion-legacy/property_unit_register.txt')));
$leases = app(App\Services\Property\PassionLegacyLeasesRegisterParser::class)
    ->parse($extractor->extract(storage_path('passion-legacy/leases.txt')));

$occupied = array_sum(array_column($properties, 'occupied_count'));
$vacant = array_sum(array_column($properties, 'vacant_count'));

echo "=== Register file counts ===\n";
echo 'Properties: '.count($properties)."\n";
echo 'Landlords (register rows): '.count($landlords)."\n";
echo 'Unit listing rows: '.count($units)."\n";
echo 'Active leases/tenants: '.count($leases)."\n";
echo 'Property register occupied+vacant total: '.($occupied + $vacant)." (occ={$occupied}, vac={$vacant})\n";

$unitStatuses = array_count_values(array_column($units, 'status'));
echo 'Unit listing by status: '.json_encode($unitStatuses)."\n";

echo "\n=== Old Ezen dashboard ===\n";
echo "Landlords: 36 | Properties: 38 | Units/Spaces: 445 | Tenants: 396\n";
