<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmPayment;

foreach ([4367, 4372] as $id) {
    $p = PmPayment::withoutGlobalScopes()->with('allocations.invoice')->find($id);
    echo "PAY-{$id} amt={$p->amount} paid={$p->paid_at} inv=".($p->allocations->first()?->invoice?->invoice_number ?? '?')." meta=".json_encode($p->meta)."\n";
}
