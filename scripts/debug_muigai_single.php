<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = App\Models\Property::withoutGlobalScopes()->where('code', 'M00001A')->first();
echo "DB units for {$p->name}:\n";
foreach ($p->units()->withoutGlobalScopes()->orderBy('label')->pluck('label') as $label) {
    echo "  $label\n";
}

$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$records = $parser->parse(file_get_contents(storage_path('passion-legacy/property_unit_register.txt')));
echo "\nRegister units for MUIGAI BARNABAS:\n";
foreach ($records as $row) {
    if (stripos($row['property_name'], 'MUIGAI BARNABAS') === false) {
        continue;
    }
    echo "  {$row['unit_label']} ({$row['status']})\n";
}

echo "\nSINGLE parsed anywhere:\n";
foreach ($records as $row) {
    if (strcasecmp($row['unit_label'], 'SINGLE') === 0) {
        echo "  property={$row['property_name']} status={$row['status']}\n";
    }
}
