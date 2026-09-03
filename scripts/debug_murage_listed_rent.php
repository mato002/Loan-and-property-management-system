<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$units = App\Models\PropertyUnit::query()
    ->where('property_id', 42)
    ->orderBy('label')
    ->get(['label', 'rent_amount', 'market_rent']);

foreach ($units as $u) {
    echo $u->label.' rent='.$u->rent_amount.' market='.$u->market_rent.' listed='.$u->listedRentAmount().PHP_EOL;
}
