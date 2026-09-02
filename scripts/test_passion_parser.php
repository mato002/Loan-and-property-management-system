<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$text = file_get_contents(__DIR__.'/../storage/passion_register_extracted.txt');
$records = collect(app(App\Services\Property\PassionLegacyRegisterParser::class)->parse($text))->keyBy('code');

foreach (['A00039A', 'M00015A', 'M00044B', 'M00002A', 'M00011A', 'M00024A', 'M00031A', 'E00037A'] as $code) {
    $r = $records->get($code);
    echo "{$code}: ".($r['name'] ?? '?')."\n";
}
