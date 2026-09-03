<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$line = 'HSE A14 LEMAYAN APPARTMENT A ADHIAMBO JUMA NANCY 0.00 8,000.00   0  Occupied 14/11/2028Sep 2, 2026, 1:27 PM';
$line = preg_replace('/(\d{2}\/\d{2}\/\d{4})Sep/i', '$1', $line);
$line = preg_replace('/\s*Sep \d+, \d{4}.*$/i', '', $line);
echo "Sanitized: {$line}\n";

$pattern = '/^(.+?)\s+([\d,]+\.\d{2})\s+([\d,]+\.\d{2})\s+(.*?)\s+(\d+)\s+(Occupied|Vacant|Owner\s*Occupied)\s*(\d{2}\/\d{2}\/\d{4})?\s*$/i';
if (preg_match($pattern, 'LEMAYAN APPARTMENT A ADHIAMBO JUMA NANCY 0.00 8,000.00   0  Occupied 14/11/2028', $m)) {
    echo "Tail match OK\n";
} else {
    echo "Tail match FAIL\n";
}

$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$text = $extractor->extract(storage_path('passion-legacy/property_unit_register.txt'));
foreach (preg_split('/\R/', $text) as $raw) {
    if (str_contains($raw, 'HSE A14')) {
        echo "RAW: {$raw}\n";
    }
}
