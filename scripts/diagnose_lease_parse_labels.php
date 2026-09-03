<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyLeasesRegisterParser::class);
$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$records = $parser->parse($extractor->extract(storage_path('passion-legacy/leases.txt')));

$needles = ['D4', 'SHOP 4', 'SHOP 6', 'SHOP A1', 'SHOP A2', 'HSE S2A', 'JOHN GITU'];
foreach ($records as $i => $record) {
    foreach ($needles as $needle) {
        if (str_contains($record['unit_label'], $needle) || str_contains($record['property_code'], $needle)) {
            echo ($i + 1)."\t".$record['property_code']."\t".$record['unit_label']."\t".$record['account_number'].PHP_EOL;
            break;
        }
    }
}
