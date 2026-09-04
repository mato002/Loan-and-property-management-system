<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$records = $parser->parse(file_get_contents(storage_path('passion-legacy/property_unit_register.txt')));

echo "Owner-occupied units in register:\n";
foreach ($records as $row) {
    if ($row['status'] !== App\Models\PropertyUnit::STATUS_OWNER_OCCUPIED) {
        continue;
    }
    echo "  {$row['unit_label']} @ {$row['property_name']}\n";
}

$winta = App\Models\Property::withoutGlobalScopes()->where('name', 'like', '%WINTA END%')->first();
if ($winta) {
    echo "\nDB units for {$winta->name} ({$winta->code}):\n";
    foreach ($winta->units()->withoutGlobalScopes()->orderBy('label')->get(['label', 'status']) as $unit) {
        echo "  {$unit->label} ({$unit->status})\n";
    }
}
