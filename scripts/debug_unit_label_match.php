<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$n = App\Services\Property\PassionLegacyTextNormalizer::class;

$pairs = [
    ['HSE M1', 'HSE M10'],
    ['HSE M1', 'HSE M9'],
    ['HSE M1', 'Unit 1'],
    ['HSE M9', 'Unit 9'],
    ['HSE M10', 'HSE M1'],
];

foreach ($pairs as [$a, $b]) {
    echo "{$a} vs {$b}: match=".( $n::registerUnitLabelMatch($a, $b) ? 'YES' : 'no').PHP_EOL;
}
