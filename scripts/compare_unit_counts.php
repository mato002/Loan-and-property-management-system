<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\PassionLegacyRegisterParser;
use App\Services\Property\PassionLegacyRegisterPdfTextExtractor;
use App\Services\Property\PassionLegacyTextNormalizer;
use App\Services\Property\PassionLegacyUnitRegisterParser;
use App\Services\Property\PassionPropertyCodeResolver;

$agentId = (int) ($argv[1] ?? 1);
$base = storage_path('passion-legacy');

$extractor = app(PassionLegacyRegisterPdfTextExtractor::class);
$unitParser = app(PassionLegacyUnitRegisterParser::class);
$propertyParser = app(PassionLegacyRegisterParser::class);
$codeResolver = app(PassionPropertyCodeResolver::class);

$propertyRegister = $propertyParser->parse($extractor->extract($base.'/property_register.txt'));
$unitRecords = $unitParser->parse($extractor->extract($base.'/property_unit_register.txt'));

$occupied = array_sum(array_column($propertyRegister, 'occupied_count'));
$vacant = array_sum(array_column($propertyRegister, 'vacant_count'));

echo '=== Counts ==='.PHP_EOL;
echo 'Ezen dashboard target: 445'.PHP_EOL;
echo 'Unit register parsed rows: '.count($unitRecords).PHP_EOL;
echo 'Property register occ+vac: '.($occupied + $vacant)." (occ {$occupied}, vac {$vacant})".PHP_EOL;
echo 'Property register properties: '.count($propertyRegister).PHP_EOL;

$dbUnits = PropertyUnit::query()
    ->withoutGlobalScopes()
    ->whereHas('property', fn ($q) => $q->where('agent_user_id', $agentId))
    ->with('property:id,code,name')
    ->get();

echo 'DB units (agent '.$agentId.'): '.$dbUnits->count().PHP_EOL;
echo 'Gap vs Ezen 445: '.(445 - $dbUnits->count()).PHP_EOL;
echo 'Gap vs parsed '.count($unitRecords).': '.(count($unitRecords) - $dbUnits->count()).PHP_EOL;

/** @var array<string, list<array<string, mixed>>> $expectedByProperty */
$expectedByProperty = [];
$unresolvedRegister = [];

foreach ($unitRecords as $record) {
    $property = $codeResolver->resolveByNameViaRegister($record['property_name'], $propertyRegister);
    if (! $property) {
        $unresolvedRegister[] = $record;

        continue;
    }

    $key = (int) $property->id;
    $label = PassionLegacyTextNormalizer::normalizeUnitLabel($record['unit_label']);
    $expectedByProperty[$key][] = [
        'label' => $label,
        'register_label' => $record['unit_label'],
        'property_name' => $record['property_name'],
        'property_code' => $property->code,
        'status' => $record['status'],
    ];
}

echo PHP_EOL.'=== Register rows with no matching property ==='.PHP_EOL;
echo 'Count: '.count($unresolvedRegister).PHP_EOL;
foreach ($unresolvedRegister as $r) {
    echo '  '.$r['unit_label'].' | '.$r['property_name'].PHP_EOL;
}

/** @var list<array<string, mixed>> $missingInDb */
$missingInDb = [];
/** @var list<array<string, mixed>> $extraInDb */
$extraInDb = [];

foreach ($expectedByProperty as $propertyId => $expectedUnits) {
    $dbOnProperty = $dbUnits->where('property_id', $propertyId);

    foreach ($expectedUnits as $expected) {
        $found = $dbOnProperty->first(
            fn (PropertyUnit $u) => PassionLegacyTextNormalizer::registerUnitLabelMatch($expected['label'], $u->label),
        );

        if (! $found) {
            $missingInDb[] = array_merge($expected, ['property_id' => $propertyId]);
        }
    }

    foreach ($dbOnProperty as $unit) {
        $matched = collect($expectedUnits)->contains(
            fn (array $exp) => PassionLegacyTextNormalizer::registerUnitLabelMatch($exp['label'], $unit->label),
        );

        if (! $matched) {
            $extraInDb[] = [
                'property_id' => $propertyId,
                'property_code' => $unit->property?->code,
                'property_name' => $unit->property?->name,
                'label' => $unit->label,
                'status' => $unit->status,
            ];
        }
    }
}

echo PHP_EOL.'=== In register but MISSING in DB ==='.PHP_EOL;
echo 'Count: '.count($missingInDb).PHP_EOL;
foreach ($missingInDb as $m) {
    echo '  ['.$m['property_code'].'] '.$m['label'].' ('.$m['status'].') — register: '.$m['register_label'].PHP_EOL;
}

echo PHP_EOL.'=== In DB but NOT in unit register ==='.PHP_EOL;
echo 'Count: '.count($extraInDb).PHP_EOL;
foreach (array_slice($extraInDb, 0, 30) as $e) {
    echo '  ['.($e['property_code'] ?? '?').'] '.$e['label'].' ('.$e['status'].') — '.$e['property_name'].PHP_EOL;
}
if (count($extraInDb) > 30) {
    echo '  ... and '.(count($extraInDb) - 30).' more'.PHP_EOL;
}

echo PHP_EOL.'=== Property register vs unit listing per property ==='.PHP_EOL;
$propertyRegisterByCode = collect($propertyRegister)->keyBy(fn ($r) => $codeResolver->normalizeCode((string) $r['code']));

$properties = Property::query()
    ->withoutGlobalScopes()
    ->where('agent_user_id', $agentId)
    ->orderBy('name')
    ->get(['id', 'code', 'name']);

foreach ($properties as $property) {
    $code = $codeResolver->normalizeCode((string) $property->code);
    $reg = $propertyRegisterByCode->get($code);
    $regTotal = $reg ? ((int) ($reg['occupied_count'] ?? 0) + (int) ($reg['vacant_count'] ?? 0)) : 0;
    $listed = count($expectedByProperty[$property->id] ?? []);
    $dbCount = $dbUnits->where('property_id', $property->id)->count();

    if ($regTotal !== $listed || $regTotal !== $dbCount || $listed !== $dbCount) {
        echo sprintf(
            "  [%s] %s — register %d | parsed %d | db %d\n",
            $code,
            $property->name,
            $regTotal,
            $listed,
            $dbCount,
        );
    }
}
