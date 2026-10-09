<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::query()->find(1);
$pack = app(App\Services\Property\FieldMeterCaptureService::class)->pack($user, '2026-10');
$found = 0;
foreach ($pack['properties'] as $prop) {
    if (stripos($prop['name'], 'MURAGE') === false && stripos($prop['name'], 'LUGAS') === false) {
        continue;
    }
    $found++;
    echo $prop['name'].PHP_EOL;
    foreach (array_slice($prop['units'], 0, 6) as $unit) {
        echo '  '.$unit['label'].PHP_EOL;
        if ($unit['meters'] === []) {
            echo "    (no meters)\n";
        }
        foreach ($unit['meters'] as $m) {
            echo '    '.$m['kind'].' prev='.$m['previous'].' cur='.json_encode($m['current']).' recorded='.($m['already_recorded'] ? 'yes' : 'no').PHP_EOL;
        }
    }
}
if ($found === 0) {
    echo 'NO MATCH count='.count($pack['properties']).PHP_EOL;
}
