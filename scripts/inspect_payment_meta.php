<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmPayment;

foreach ([4367, 4305, 4106, 2238] as $id) {
    $p = PmPayment::withoutGlobalScopes()->find($id);
    echo "PAY-{$id} ext={$p->external_ref} meta=".json_encode($p->meta)."\n\n";
}
