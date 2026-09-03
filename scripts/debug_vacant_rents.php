<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$text = file_get_contents(__DIR__.'/../storage/passion-legacy/property_unit_register.txt');
$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$parsed = $parser->parse($text);

$vacant = array_values(array_filter($parsed, fn (array $r) => $r['status'] === 'vacant'));
echo 'Parsed vacant units: '.count($vacant).PHP_EOL;
echo 'With zero market and current: '.count(array_filter($vacant, fn ($r) => (float) ($r['market_rent'] ?? 0) <= 0 && (float) ($r['current_rent'] ?? 0) <= 0)).PHP_EOL;
echo 'With market>0 current=0: '.count(array_filter($vacant, fn ($r) => (float) ($r['market_rent'] ?? 0) > 0 && (float) ($r['current_rent'] ?? 0) <= 0)).PHP_EOL;

foreach (array_slice($vacant, 0, 12) as $r) {
    echo $r['unit_label'].' | '.$r['property_name'].' | market='.($r['market_rent'] ?? 'null').' curr='.($r['current_rent'] ?? 'null').PHP_EOL;
}

$dbVacant = App\Models\PropertyUnit::query()->where('status', 'vacant')->get();
echo PHP_EOL.'DB vacant: '.$dbVacant->count().PHP_EOL;
echo 'DB zero listed: '.$dbVacant->filter(fn ($u) => $u->listedRentAmount() <= 0)->count().PHP_EOL;
foreach ($dbVacant->filter(fn ($u) => $u->listedRentAmount() <= 0)->take(12) as $u) {
    echo $u->label.' | rent='.$u->rent_amount.' market='.($u->market_rent ?? 'null').PHP_EOL;
}

echo PHP_EOL.'Vacant with market>0 but rent_amount=0: '.$dbVacant->filter(fn ($u) => (float)($u->market_rent ?? 0) > 0 && (float)$u->rent_amount <= 0)->count().PHP_EOL;
foreach ($dbVacant->filter(fn ($u) => (float)($u->market_rent ?? 0) > 0 && (float)$u->rent_amount <= 0)->take(8) as $u) {
    echo $u->label.' rent='.$u->rent_amount.' market='.$u->market_rent.' listed='.$u->listedRentAmount().PHP_EOL;
}
