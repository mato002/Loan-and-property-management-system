<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$records = $parser->parse($extractor->extract(storage_path('passion-legacy/property_unit_register.txt')));

$koinange = array_values(array_filter(
    $records,
    fn (array $row) => stripos($row['property_name'] ?? '', 'KOINANGE') !== false,
));

echo 'KOINANGE parsed: '.count($koinange).PHP_EOL;
foreach ($koinange as $unit) {
    echo ' - '.$unit['unit_label'].PHP_EOL;
}

$missing = ['CARWASH', 'SHOP 3', 'SHOP 4', 'SHOP 5', 'SHOP 6', 'SHOP 7', 'SHOP 1 & 2'];
$labels = array_column($koinange, 'unit_label');
foreach ($missing as $label) {
    $found = false;
    foreach ($labels as $l) {
        if (PassionLegacyTextNormalizer::unitLabelsMatch($l, $label)) {
            $found = true;
            break;
        }
    }
    echo ($found ? 'OK' : 'MISSING')." {$label}\n";
}
