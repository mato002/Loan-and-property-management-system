<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyLeasesRegisterParser::class);
$reflection = new ReflectionClass($parser);
$preprocess = $reflection->getMethod('preprocess');
$preprocess->setAccessible(true);
$parseLeaseLine = $reflection->getMethod('parseLeaseLine');
$parseLeaseLine->setAccessible(true);

$samples = [
    'A8 TNT001252DANIEL ODONGO 0718519433 4,770 4,700 15/05/2026   0 Revision',
    'A7 TNT001162BONFACE KHASAKHALA OCHUTO 0769694787 9,640 4,700 20/10/2025   0 Revision',
    'HSE 1 TNT000811JAMES GACHERU 0713061482 7,528 7,000 01/11/2023   0 Revision',
];

foreach ($samples as $sample) {
    echo "SAMPLE: {$sample}\n";
    var_export($parseLeaseLine->invoke($parser, $sample, 'A00039A'));
    echo "\n\n";
}

$text = file_get_contents(__DIR__.'/../storage/leases_extracted.txt');
$processed = $preprocess->invoke($parser, App\Services\Property\PassionLegacyTextNormalizer::stripRegisterNoise($text));
$count = 0;
foreach (explode("\n", $processed) as $line) {
    if (preg_match('/TNT\d+/i', $line)) {
        $count++;
    }
}
echo "Processed lines with TNT: {$count}\n";
