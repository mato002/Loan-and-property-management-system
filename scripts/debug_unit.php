<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int) ($argv[1] ?? 1404);
$leaseId = (int) ($argv[2] ?? 910);

echo 'unit '.$id.': '.json_encode(DB::table('property_units')->where('id', $id)->first()).PHP_EOL;
echo 'lease '.$leaseId.': '.json_encode(DB::table('pm_leases')->where('id', $leaseId)->first()).PHP_EOL;
echo 'lease_unit rows: '.json_encode(DB::table('pm_lease_unit')->where('property_unit_id', $id)->orWhere('pm_lease_id', $leaseId)->get()).PHP_EOL;
