<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Models\PmTenant;

$accounts = ['TNT001029', 'TNT000432', 'TNT000416', 'TNT001167', 'TNT000379', 'TNT000892', 'TNT001050', 'TNT001091', 'TNT000858'];

$showAllSep = in_array('--all-sep', $argv, true);

foreach ($accounts as $account) {
    $tenant = PmTenant::withoutGlobalScopes()->where('account_number', $account)->first();
    if (! $tenant) {
        echo "{$account}: no tenant\n\n";
        continue;
    }
    echo "=== {$account} (id={$tenant->id}) ===\n";
    $regs = PmEzenReceiptRegister::withoutGlobalScopes()
        ->where('pm_tenant_id', $tenant->id)
        ->whereYear('banking_date', 2026)
        ->whereMonth('banking_date', 9)
        ->get(['ezen_receipt_no', 'amount', 'ref_no', 'unit_label', 'banking_date']);
    foreach ($regs as $r) {
        echo "  REG {$r->ezen_receipt_no} amt={$r->amount} ref={$r->ref_no} unit={$r->unit_label}\n";
    }
    $paysQuery = PmPayment::withoutGlobalScopes()
        ->with('allocations.invoice.unit')
        ->where('pm_tenant_id', $tenant->id)
        ->where('channel', 'ezen_import');
    if (! $showAllSep) {
        $paysQuery->whereYear('paid_at', 2026)->whereMonth('paid_at', 9);
    }
    $pays = $paysQuery->get();
    echo '  paySum='.round((float) $pays->sum('amount'), 2).' count='.$pays->count()."\n";
    foreach ($pays as $p) {
        $unit = $p->allocations->first()?->invoice?->unit?->label ?? '?';
        $ref = data_get($p->meta, 'mpesa_ref', '') ?: '—';
        echo "  PAY-{$p->id} amt={$p->amount} unit={$unit} ref={$ref}\n";
    }
    echo "\n";
}
