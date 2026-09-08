<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmPayment;
use App\Models\PmTenant;

$t = PmTenant::withoutGlobalScopes()->where('account_number', 'TNT000416')->first();
$pays = PmPayment::withoutGlobalScopes()->with('allocations.invoice.unit')
    ->where('pm_tenant_id', $t->id)->where('channel', 'ezen_import')
    ->whereYear('paid_at', 2026)->whereMonth('paid_at', 9)->get();
foreach ($pays as $p) {
    echo "PAY-{$p->id} amt={$p->amount} unit=".($p->allocations->first()?->invoice?->unit?->label ?? '?')." ref=".data_get($p->meta,'mpesa_ref','')." receipt=".data_get($p->meta,'receipted_to','')."\n";
}
echo "sum=".$pays->sum('amount')."\n";
