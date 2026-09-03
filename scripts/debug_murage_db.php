<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$props = App\Models\Property::query()
    ->where('name', 'like', '%MURAGE%LUGAS%M%')
    ->orWhere('name', 'like', '%LUGAS -M%')
    ->get(['id', 'name', 'code']);

foreach ($props as $p) {
    echo "Property #{$p->id}: {$p->name} ({$p->code})\n";
    $units = App\Models\PropertyUnit::query()
        ->where('property_id', $p->id)
        ->orderBy('label')
        ->get(['id', 'label', 'rent_amount', 'status']);
    foreach ($units as $u) {
        echo "  {$u->label} | rent={$u->rent_amount} | {$u->status}\n";
    }
    echo '  count: '.$units->count()."\n\n";
}
