<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$propertyParser = app(App\Services\Property\PassionLegacyRegisterParser::class);
$unitParser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$codeResolver = app(App\Services\Property\PassionPropertyCodeResolver::class);

$properties = $propertyParser->parse($extractor->extract(storage_path('passion-legacy/property_register.txt')));
$units = $unitParser->parse($extractor->extract(storage_path('passion-legacy/property_unit_register.txt')));

$codes = ['F00047A', 'M00021A', 'M00021B'];

echo "=== Francis properties: Ezen register vs unit listing ===\n\n";
printf("%-10s %-35s %8s %8s %8s %8s\n", 'Code', 'Property', 'Ezen occ', 'Ezen vac', 'Ezen tot', 'Listing');
echo str_repeat('-', 90)."\n";

foreach ($properties as $record) {
    $code = $codeResolver->normalizeCode($record['code'] ?? '');
    if (! in_array($code, $codes, true)) {
        continue;
    }

    $occ = (int) ($record['occupied_count'] ?? 0);
    $vac = (int) ($record['vacant_count'] ?? 0);
    $listing = 0;

    foreach ($units as $unitRow) {
        $property = $codeResolver->resolveByNameViaRegister($unitRow['property_name'], $properties);
        if ($property && $codeResolver->normalizeCode($property->code) === $code) {
            $listing++;
        }
    }

    printf(
        "%-10s %-35s %8d %8d %8d %8d\n",
        $code,
        substr($record['name'] ?? '', 0, 35),
        $occ,
        $vac,
        $occ + $vac,
        $listing,
    );
}

echo "\n=== Passion UI (from screenshot) ===\n";
echo "F00047A KIAMUNYI:  9 units (matches Ezen)\n";
echo "M00021A KOINANGE: 20 units (Ezen: 14, diff +6)\n";
echo "M00021B PCEA:      9 units (Ezen:  8, diff +1)\n";
echo "Francis total:    38 vs Ezen 31 (+7)\n";
echo "\n=== Portfolio-wide ===\n";
echo "Passion dashboard: 485 total (442 occ + 43 vac)\n";
echo "Ezen target:       445 total (396 tenants + ~49 non-leased spaces)\n";
echo "Property register: 442 occupied+vacant spaces\n";
echo "Gap:               485 - 442 = 43 extra units (lease-import stubs)\n";
