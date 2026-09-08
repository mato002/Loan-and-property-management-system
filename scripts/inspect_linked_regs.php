<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;

foreach (['RC24158', 'RC24939', 'RC24850', 'RC24880', 'RC24820'] as $rc) {
    $r = PmEzenReceiptRegister::withoutGlobalScopes()->where('ezen_receipt_no', $rc)->first();
    if (! $r) {
        echo "{$rc}: not found\n";
        continue;
    }
    echo "{$rc} amt={$r->amount} linked={$r->pm_payment_id} tenant={$r->pm_tenant_id}\n";
    if ($r->pm_payment_id) {
        $p = PmPayment::withoutGlobalScopes()->find($r->pm_payment_id);
        echo "  -> PAY-{$p->id} amt={$p->amount} ref=".data_get($p->meta, 'mpesa_ref', '—')."\n";
    }
}
