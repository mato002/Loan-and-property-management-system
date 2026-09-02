<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$units = app(App\Services\Property\PassionLegacyUnitRegisterParser::class)
    ->parse(file_get_contents(__DIR__.'/../storage/passion-legacy/property_unit_register.txt'));
$resolver = app(App\Services\Property\PassionPropertyCodeResolver::class);

$miss = [];
foreach ($units as $u) {
    if (! $resolver->resolveByName($u['property_name'])) {
        $miss[$u['property_name']] = ($miss[$u['property_name']] ?? 0) + 1;
    }
}

echo 'Units: '.count($units)."\n";
echo 'Missing property names: '.count($miss)."\n";
foreach ($miss as $name => $count) {
    echo "  {$count}x {$name}\n";
}

echo "\nDB properties:\n";
foreach (App\Models\Property::withoutGlobalScopes()->orderBy('code')->get(['code', 'name']) as $p) {
    echo "  {$p->code} | {$p->name}\n";
}
