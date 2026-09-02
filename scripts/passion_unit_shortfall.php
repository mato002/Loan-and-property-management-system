<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$parser = app(App\Services\Property\PassionLegacyRegisterParser::class);
$resolver = app(App\Services\Property\PassionPropertyCodeResolver::class);

$records = $parser->parse($extractor->extract(storage_path('passion-legacy/property_register.txt')));

$targetTotal = 0;
$currentTotal = 0;
$shortfallTotal = 0;

foreach ($records as $record) {
    $property = $resolver->resolveOne($record['code']);
    if (! $property) {
        continue;
    }

    $target = (int) ($record['occupied_count'] ?? 0) + (int) ($record['vacant_count'] ?? 0);
    $current = App\Models\PropertyUnit::query()->withoutGlobalScopes()->where('property_id', $property->id)->count();
    $shortfall = max(0, $target - $current);

    $targetTotal += $target;
    $currentTotal += $current;
    $shortfallTotal += $shortfall;

    if ($shortfall > 0) {
        echo sprintf("%s %s: target=%d current=%d shortfall=%d\n", $record['code'], $property->name, $target, $current, $shortfall);
    }
}

echo "\nTarget spaces (register): {$targetTotal}\n";
echo "Current DB units: {$currentTotal}\n";
echo "Shortfall to create: {$shortfallTotal}\n";
echo 'After fill: '.($currentTotal + $shortfallTotal)."\n";
