<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;

$checks = [
    ['id' => 408, 'amt' => 7352],
    ['id' => 147, 'amt' => 424],
    ['id' => 632, 'amt' => 6],
    ['id' => 4106, 'amt' => 9000],
    ['id' => 4305, 'amt' => 1572],
    ['id' => 5010, 'amt' => 4000],
];

foreach ($checks as $c) {
    $p = PmPayment::withoutGlobalScopes()->with('allocations.invoice.unit')->find($c['id']);
    $unit = strtoupper(trim((string) ($p->allocations->first()?->invoice?->unit?->label ?? '')));
    $regs = PmEzenReceiptRegister::withoutGlobalScopes()
        ->where('pm_tenant_id', $p->pm_tenant_id)
        ->get()
        ->filter(fn ($r) => abs((float) $r->amount - (float) $c['amt']) < 0.02
            || abs((float) $r->amount - (float) $c['amt']) < 500);
    echo "PAY-{$c['id']} amt={$c['amt']} unit={$unit} close regs:\n";
    foreach ($regs->take(5) as $r) {
        echo "  {$r->ezen_receipt_no} amt={$r->amount} ref={$r->ref_no} unit={$r->unit_label} date={$r->banking_date} linked={$r->pm_payment_id}\n";
    }
    echo "\n";
}
