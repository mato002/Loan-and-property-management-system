<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyLeasesRegisterParser::class);
$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$resolver = app(App\Services\Property\PassionLegacyUnitResolver::class);
$codeResolver = app(App\Services\Property\PassionPropertyCodeResolver::class);

$records = $parser->parse($extractor->extract(storage_path('passion-legacy/leases.txt')));
$miss = [];

foreach ($records as $i => $record) {
    $property = $codeResolver->resolveOne($record['property_code']);
    if (! $property) {
        $miss[] = [
            'row' => $i + 1,
            'why' => 'no property',
            'code' => $record['property_code'],
            'unit' => $record['unit_label'],
        ];

        continue;
    }

    $unit = $resolver->findOnProperty($property->id, $record['unit_label']);
    if (! $unit) {
        $miss[] = [
            'row' => $i + 1,
            'why' => 'no unit',
            'code' => $record['property_code'],
            'property' => $property->name,
            'property_id' => $property->id,
            'unit' => $record['unit_label'],
            'account' => $record['account_number'],
        ];
    }
}

echo 'Parsed='.count($records).' Would miss='.count($miss).PHP_EOL;
foreach ($miss as $entry) {
    echo json_encode($entry).PHP_EOL;
}
