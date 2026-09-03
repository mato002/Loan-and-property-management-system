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

$text = App\Services\Property\PassionLegacyTextNormalizer::stripRegisterNoise(file_get_contents(__DIR__.'/../storage/leases_extracted.txt'));
$text = $preprocess->invoke($parser, $text);

$currentPropertyCode = '';
$parsed = 0;
$failedNoCode = 0;
$failedParse = 0;
$failedExamples = [];

foreach (preg_split('/\R/', $text) ?: [] as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    if (preg_match('/^\[([A-Z]\d{5}[A-Z]?)\]/', $line, $codeMatch)) {
        $currentPropertyCode = $codeMatch[1];
    }

    if (! preg_match('/TNT\d+/i', $line)) {
        continue;
    }

    if ($currentPropertyCode === '') {
        $failedNoCode++;
        if (count($failedExamples) < 5) {
            $failedExamples[] = $line;
        }
        continue;
    }

    $record = $parseLeaseLine->invoke($parser, $line, $currentPropertyCode);
    if ($record === null) {
        $failedParse++;
        if (count($failedExamples) < 10) {
            $failedExamples[] = $line;
        }
    } else {
        $parsed++;
    }
}

echo "Parsed: {$parsed}\n";
echo "Failed no code: {$failedNoCode}\n";
echo "Failed parse: {$failedParse}\n";
foreach ($failedExamples as $example) {
    echo "EX: {$example}\n";
}
