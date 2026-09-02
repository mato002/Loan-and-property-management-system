<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$type = $argv[1] ?? 'all';
$base = __DIR__.'/../storage';

$parsers = [
    'landlords' => [App\Services\Property\PassionLegacyLandlordRegisterParser::class, 'landlord_register_extracted.txt'],
    'units' => [App\Services\Property\PassionLegacyUnitRegisterParser::class, 'property_unit_register_extracted.txt'],
    'leases' => [App\Services\Property\PassionLegacyLeasesRegisterParser::class, 'leases_extracted.txt'],
];

foreach ($parsers as $key => [$class, $file]) {
    if ($type !== 'all' && $type !== $key) {
        continue;
    }

    $path = $base.'/'.$file;
    if (! is_file($path)) {
        echo "{$key}: missing {$path}\n";
        continue;
    }

    $parser = app($class);
    $records = $parser->parse(file_get_contents($path));
    echo strtoupper($key).': '.count($records)." rows\n";
    echo json_encode($records[0] ?? [], JSON_PRETTY_PRINT)."\n\n";
}
