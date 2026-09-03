<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyLeasesRegisterParser::class);
$resolver = app(App\Services\Property\PassionPropertyCodeResolver::class);
$unitResolver = app(App\Services\Property\PassionLegacyUnitResolver::class);
$records = $parser->parse(file_get_contents(__DIR__.'/../storage/passion-legacy/leases.txt'));

foreach (['TNT001287', 'TNT000424', 'TNT001137'] as $account) {
    $record = collect($records)->firstWhere('account_number', $account);
    $property = $resolver->resolveOne($record['property_code']);
    $unit = $unitResolver->findOnProperty($property->id, $record['unit_label']);
    $units = App\Models\PropertyUnit::withoutGlobalScopes()->where('property_id', $property->id)->get();
    echo "$account property={$property->id} label={$record['unit_label']} found=".($unit?->id ?? 'NO').' units_on_property='.$units->count().PHP_EOL;
    foreach ($units as $u) {
        if (App\Services\Property\PassionLegacyTextNormalizer::registerUnitLabelMatch($u->label, $record['unit_label'])) {
            echo "  match: #{$u->id} {$u->label}\n";
        }
    }
}
