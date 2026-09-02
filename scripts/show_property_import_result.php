<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use Illuminate\Support\Facades\DB;

$properties = Property::query()
    ->withoutGlobalScopes()
    ->orderBy('code')
    ->get(['id', 'code', 'name', 'address_line', 'city', 'agent_user_id', 'management_status']);

$overridesRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
$overrides = json_decode($overridesRaw, true) ?: [];

echo '=== Portfolio after phase 1 import ==='.PHP_EOL;
echo 'Properties: '.$properties->count().PHP_EOL;
echo 'Units: '.PropertyUnit::query()->withoutGlobalScopes()->count().PHP_EOL;
echo 'Landlord links: '.DB::table('property_landlord')->count().PHP_EOL;
echo 'Commission overrides: '.count($overrides).PHP_EOL.PHP_EOL;

echo str_pad('CODE', 10).' | '.str_pad('CITY', 12).' | '.str_pad('FEE%', 5).' | NAME'.PHP_EOL;
echo str_repeat('-', 90).PHP_EOL;

foreach ($properties as $p) {
    $fee = $overrides[(string) $p->id] ?? '-';
    echo str_pad((string) $p->code, 10).' | '
        .str_pad((string) ($p->city ?? ''), 12).' | '
        .str_pad((string) $fee, 5).' | '
        .$p->name.PHP_EOL;
}

echo PHP_EOL.'Sample detail (first property):'.PHP_EOL;
$first = $properties->first();
if ($first) {
    echo json_encode($first->toArray(), JSON_PRETTY_PRINT).PHP_EOL;
}
