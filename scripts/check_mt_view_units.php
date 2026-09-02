<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$records = $parser->parse($extractor->extract(storage_path('passion-legacy/property_unit_register.txt')));

$mtView = array_values(array_filter(
    $records,
    fn (array $row) => stripos($row['property_name'] ?? '', 'MT. VIEW') !== false
        || stripos($row['property_name'] ?? '', 'MT VIEW') !== false,
));

echo 'MT VIEW parsed: '.count($mtView).PHP_EOL;
foreach ($mtView as $unit) {
    echo ' - '.$unit['unit_label'].PHP_EOL;
}

$hasOne = false;
foreach ($mtView as $unit) {
    if ($unit['unit_label'] === '1') {
        $hasOne = true;
        echo 'Unit 1 tenant: '.($unit['tenant_name'] ?? '').PHP_EOL;
    }
}
echo $hasOne ? "Unit 1 OK\n" : "Unit 1 MISSING\n";
