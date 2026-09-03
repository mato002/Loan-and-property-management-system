<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Property;
use App\Services\Property\PassionLegacyUnitRegisterParser;
use App\Services\Property\PassionLegacyRegisterParser;
use App\Services\Property\PassionLegacyRegisterPdfTextExtractor;
use App\Services\Property\PassionPropertyCodeResolver;

$code = $argv[1] ?? 'E00037A';
$p = Property::withoutGlobalScopes()->where('code', $code)->first();
$extractor = app(PassionLegacyRegisterPdfTextExtractor::class);
$unitParser = app(PassionLegacyUnitRegisterParser::class);
$propertyParser = app(PassionLegacyRegisterParser::class);
$codeResolver = app(PassionPropertyCodeResolver::class);
$propertyRegister = $propertyParser->parse($extractor->extract(storage_path('passion-legacy/property_register.txt')));
$unitRecords = $unitParser->parse($extractor->extract(storage_path('passion-legacy/property_unit_register.txt')));

$byName = [];
foreach ($unitRecords as $record) {
    $byName[$record['property_name']][] = $record['unit_label'];
}

echo "Property {$p->id} {$p->name} [{$p->code}]\n";
echo "Units in DB: ".$p->units()->withoutGlobalScopes()->count()."\n";

$resolved = 0;
foreach ($unitRecords as $record) {
    $prop = $codeResolver->resolveByNameViaRegister($record['property_name'], $propertyRegister);
    if ($prop && $prop->id === $p->id) {
        $resolved++;
    }
}
echo "Unit register rows resolved to this property: $resolved\n";

foreach ($byName as $name => $labels) {
    if (stripos($name, 'GOSHEN') !== false || stripos($name, 'PAUL KAHURIA') !== false) {
        $prop = $codeResolver->resolveByNameViaRegister($name, $propertyRegister);
        echo "\nRegister name: $name\n";
        echo '  units: '.count($labels).' -> property '.($prop?->code ?? 'NONE')." id=".($prop?->id ?? '-')."\n";
        echo '  labels: '.implode(', ', array_slice($labels, 0, 12)).(count($labels) > 12 ? '...' : '')."\n";
    }
}
