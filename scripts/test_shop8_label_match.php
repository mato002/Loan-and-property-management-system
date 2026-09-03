<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tests = [
    ['SHOP 8', '8', false],
    ['SHOP 8', 'SHOP 8', true],
    ['HSE 10', '10', true],
    ['SHOP 10', '10', false],
    ['HSE B7', 'B7', true],
];

foreach ($tests as [$register, $db, $expected]) {
    $actual = App\Services\Property\PassionLegacyTextNormalizer::registerUnitLabelMatch($register, $db);
    $status = $actual === $expected ? 'OK' : 'FAIL';
    echo "{$status}: register={$register} db={$db} expected=".($expected ? 'match' : 'no').' got='.($actual ? 'match' : 'no').PHP_EOL;
}
