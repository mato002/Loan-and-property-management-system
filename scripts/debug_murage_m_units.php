<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$text = file_get_contents(__DIR__.'/../storage/passion-legacy/property_unit_register.txt');
$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$parsed = $parser->parse($text);

$murage = array_values(array_filter($parsed, static function (array $r): bool {
    return stripos($r['property_name'], 'LUGAS -M') !== false
        || stripos($r['property_name'], 'LUGAS -M-APARTMENTS') !== false;
}));

foreach ($murage as $r) {
    echo $r['unit_label']
        .' | market='.($r['market_rent'] ?? 'null')
        .' | curr='.($r['current_rent'] ?? 'null')
        .' | '.($r['tenant_name'] ?? '-')
        .PHP_EOL;
}

echo 'Total M-units parsed: '.count($murage).PHP_EOL;
