<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;

foreach (['RC22612', 'RC24930', 'RC24850', 'RC24932'] as $rc) {
    $r = PmEzenReceiptRegister::withoutGlobalScopes()->where('ezen_receipt_no', $rc)->first();
    if (! $r) {
        echo "{$rc}: missing\n";
        continue;
    }
    echo "{$rc} tenant={$r->pm_tenant_id} amt={$r->amount} ref={$r->ref_no} receipted_to={$r->receipted_to} linked={$r->pm_payment_id}\n";
}
