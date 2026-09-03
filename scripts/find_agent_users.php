<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::query()
    ->select('id', 'name', 'email', 'property_portal_role')
    ->orderBy('id')
    ->get();

foreach ($users as $u) {
    echo sprintf(
        "%d | %s | %s | %s\n",
        $u->id,
        $u->property_portal_role ?? '-',
        $u->email,
        $u->name,
    );
}
