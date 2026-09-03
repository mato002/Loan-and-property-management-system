<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$agentId = (int) ($argv[1] ?? 1);

$propertyIds = App\Models\Property::query()
    ->withoutGlobalScopes()
    ->where('agent_user_id', $agentId)
    ->pluck('id');

$units = DB::table('property_units')->whereIn('property_id', $propertyIds)->count();
$tenants = DB::table('pm_tenants')->where('agent_user_id', $agentId)->count();
$leases = DB::table('pm_leases')
    ->whereIn('pm_tenant_id', DB::table('pm_tenants')->where('agent_user_id', $agentId)->pluck('id'))
    ->count();
$landlords = App\Models\User::query()
    ->where('property_portal_role', 'landlord')
    ->where('agent_user_id', $agentId)
    ->count();
$landlordsTotal = App\Models\User::query()
    ->where('property_portal_role', 'landlord')
    ->count();
$landlordsLinked = DB::table('property_landlord')
    ->whereIn('property_id', $propertyIds)
    ->distinct('user_id')
    ->count('user_id');

echo "Agent {$agentId} totals:\n";
echo "  Properties: {$propertyIds->count()}\n";
echo "  Landlords (agent_user_id): {$landlords}\n";
echo "  Landlords (all portal): {$landlordsTotal}\n";
echo "  Landlords (linked to properties): {$landlordsLinked}\n";
echo "  Units: {$units}\n";
echo "  Tenants: {$tenants}\n";
echo "  Leases: {$leases}\n";

// Spot checks
$checks = [
    ['KOINANGE', 'SHOP 1 & 2'],
    ['LEMAYAN APPARTMENT A', 'HSE A14'],
    ['PETER KURIA', '1'],
];

$sampleLandlords = App\Models\User::query()
    ->where('property_portal_role', 'landlord')
    ->orderByDesc('id')
    ->limit(5)
    ->get(['id', 'name', 'agent_user_id']);
echo "Sample landlords agent_user_id:\n";
foreach ($sampleLandlords as $l) {
    echo "  #{$l->id} {$l->name}: agent_user_id=" . ($l->agent_user_id ?? 'null') . "\n";
}

foreach ($checks as [$propName, $unitLabel]) {
    $prop = App\Models\Property::query()
        ->withoutGlobalScopes()
        ->where('agent_user_id', $agentId)
        ->where('name', 'like', '%'.$propName.'%')
        ->first();
    if (! $prop) {
        echo "  MISSING property: {$propName}\n";
        continue;
    }
    $unitCount = DB::table('property_units')->where('property_id', $prop->id)->count();
    $unit = DB::table('property_units')
        ->where('property_id', $prop->id)
        ->where('label', $unitLabel)
        ->first();
    echo "  {$prop->name}: {$unitCount} units" . ($unit ? ", has {$unitLabel}" : ", MISSING {$unitLabel}") . "\n";
}
