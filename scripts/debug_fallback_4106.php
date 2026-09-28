<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use Carbon\Carbon;

$p = PmPayment::withoutGlobalScopes()->with('allocations.invoice.unit')->find(4106);
$paidAt = Carbon::parse($p->paid_at);
$amount = (float) $p->amount;
$unitLabel = strtoupper(trim((string) ($p->allocations->first()?->invoice?->unit?->label ?? '')));

$candidates = PmEzenReceiptRegister::withoutGlobalScopes()
    ->where('agent_user_id', 2)
    ->where('pm_tenant_id', $p->pm_tenant_id)
    ->whereNull('pm_payment_id')
    ->whereNotNull('ref_no')
    ->where('ref_no', '!=', '')
    ->get()
    ->filter(function ($register) use ($amount, $unitLabel, $paidAt) {
        if (abs((float) $register->amount - $amount) > 0.02) {
            return false;
        }
        $regUnit = strtoupper(trim((string) ($register->unit_label ?? '')));
        if ($unitLabel !== '' && $regUnit !== '' && $unitLabel !== $regUnit && ! str_contains($unitLabel, $regUnit) && ! str_contains($regUnit, $unitLabel)) {
            return false;
        }
        $bankDate = Carbon::parse($register->banking_date ?? $register->txn_date);
        $monthDiff = abs(($paidAt->year * 12 + $paidAt->month) - ($bankDate->year * 12 + $bankDate->month));
        echo "  cand {$register->ezen_receipt_no} monthDiff={$monthDiff} unit={$regUnit}\n";

        return $monthDiff <= 6;
    });

echo "count=".$candidates->count()."\n";
