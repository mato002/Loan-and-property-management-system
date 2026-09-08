<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Models\PmTenant;

$sep = PmPayment::withoutGlobalScopes()
    ->where('channel', 'ezen_import')
    ->whereDate('paid_at', '2026-09-01')
    ->get();

$withRef = $sep->filter(fn ($p) => trim((string) data_get($p->meta, 'mpesa_ref', '')) !== '');
$noRef = $sep->filter(fn ($p) => trim((string) data_get($p->meta, 'mpesa_ref', '')) === '');

echo 'Sep payments: '.$sep->count()."\n";
echo 'With ref: '.$withRef->count()."\n";
echo 'Missing ref: '.$noRef->count()."\n\n";

$fixable = 0;
foreach ($noRef->take(10) as $payment) {
    $tenant = PmTenant::withoutGlobalScopes()->find($payment->pm_tenant_id);
    $regs = PmEzenReceiptRegister::withoutGlobalScopes()
        ->where('pm_tenant_id', $payment->pm_tenant_id)
        ->whereYear('banking_date', 2026)
        ->whereMonth('banking_date', 9)
        ->get();
    $regSum = round((float) $regs->sum('amount'), 2);
    $paySum = round((float) PmPayment::withoutGlobalScopes()
        ->where('pm_tenant_id', $payment->pm_tenant_id)
        ->where('channel', 'ezen_import')
        ->whereDate('paid_at', '2026-09-01')
        ->sum('amount'), 2);

    $exactReg = $regs->first(fn ($r) => abs((float) $r->amount - (float) $payment->amount) < 0.02);
    echo "PAY-{$payment->id} amt={$payment->amount} tenant=".($tenant?->account_number ?? '?')." sep_regs={$regs->count()} regSum={$regSum} paySum={$paySum}";
    if ($exactReg) {
        echo " exactReg={$exactReg->ezen_receipt_no} ref={$exactReg->ref_no}";
        $fixable++;
    }
    echo "\n";
}

echo "\nFixable in sample: {$fixable}/10\n";
