<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(\App\Services\Property\PassionLegacyUnitRegisterParser::class);
$ref = new ReflectionClass($parser);
$m = $ref->getMethod('parseUnitLine');
$m->setAccessible(true);

$lines = [
    'SHOP 1 GOSHEN APARTMENT JUSTUS MUTETI OCCP 2,500.00 2,800.00  Retail/Shop 0  Occupied 28/02/2029',
    'SHOP 2 GOSHEN APARTMENT KABEA BETH 2,500.00 2,500.00  Retail/Shop 0  Occupied 31/12/2028',
];

foreach ($lines as $line) {
    $r = $m->invoke($parser, $line, 'GOSHEN APARTMENT');
    echo ($r ? json_encode($r) : 'NULL').PHP_EOL;
}

$records = $parser->parse(file_get_contents(storage_path('passion-legacy/property_unit_register.txt')));
$shops = array_filter($records, fn ($r) => str_starts_with($r['unit_label'], 'SHOP') && stripos($r['property_name'], 'GOSHEN') !== false);
echo 'SHOP goshen parsed: '.count($shops).PHP_EOL;
