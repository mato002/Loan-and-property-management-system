<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$text = file_get_contents(storage_path('passion-legacy/property_unit_register.txt'));

// Use reflection to dump merged lines for ST JOHNS block
$ref = new ReflectionClass($parser);
$pre = $ref->getMethod('preprocess');
$pre->setAccessible(true);
$merged = $pre->invoke($parser, App\Services\Property\PassionLegacyTextNormalizer::stripRegisterNoise($text));

foreach (preg_split('/\R/', $merged) ?: [] as $line) {
    if (stripos($line, 'KAGIO(ST') !== false || preg_match('/^HSE [2-8] MR STEPHEN/i', $line)) {
        echo $line.PHP_EOL;
        echo '---'.PHP_EOL;
    }
}
