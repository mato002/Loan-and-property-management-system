<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Models\PmTenant;

$tenant = PmTenant::withoutGlobalScopes()->where('account_number', 'TNT000858')->first();
echo "tenant id={$tenant->id}\n\n";

$regs = PmEzenReceiptRegister::withoutGlobalScopes()
    ->where('pm_tenant_id', $tenant->id)
    ->whereYear('banking_date', 2026)
    ->whereMonth('banking_date', 9)
    ->get();

foreach ($regs as $r) {
    echo "{$r->ezen_receipt_no} amt={$r->amount} ref={$r->ref_no} unit={$r->unit_label} date={$r->banking_date} linked={$r->pm_payment_id}\n";
}

echo "\nPayments Sep 2026:\n";
$pays = PmPayment::withoutGlobalScopes()
    ->with('allocations.invoice.unit')
    ->where('pm_tenant_id', $tenant->id)
    ->where('channel', 'ezen_import')
    ->whereYear('paid_at', 2026)
    ->whereMonth('paid_at', 9)
    ->get();

foreach ($pays as $p) {
    $unit = $p->allocations->first()?->invoice?->unit?->label ?? '?';
    $ref = data_get($p->meta, 'mpesa_ref', '');
    echo "PAY-{$p->id} amt={$p->amount} unit={$unit} ref=".($ref ?: '—')."\n";
}
