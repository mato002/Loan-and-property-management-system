<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use Illuminate\Support\Facades\Schema;

$regs = PmEzenReceiptRegister::query()
    ->where('agent_user_id', 2)
    ->where('ezen_receipt_no', '>=', 'RC25167')
    ->orderBy('ezen_receipt_no')
    ->get();

echo 'Register rows RC25167+: '.$regs->count().PHP_EOL;

$withPayment = 0;
$noPayment = [];
foreach ($regs as $r) {
    $has = false;
    if ($r->pm_payment_id) {
        $has = PmPayment::query()->whereKey($r->pm_payment_id)->exists();
    }
    if (! $has) {
        $rc = (string) $r->ezen_receipt_no;
        $ref = trim((string) ($r->ref_no ?? ''));
        $has = PmPayment::query()
            ->where(function ($q) use ($rc, $ref) {
                $q->where('external_ref', $rc);
                if ($ref !== '' && strtoupper($ref) !== 'CASH') {
                    $q->orWhere('external_ref', $ref);
                }
            })
            ->exists();
    }
    if ($has) {
        $withPayment++;
    } else {
        $noPayment[] = sprintf(
            '%s | %s | %s | %s | %s | link=%s',
            $r->ezen_receipt_no,
            optional($r->banking_date)->format('Y-m-d'),
            $r->tnt_account,
            number_format((float) $r->amount, 2),
            $r->ref_no,
            $r->link_status
        );
    }
}

echo "Linked/found payment: {$withPayment}\n";
echo 'Still no payment row: '.count($noPayment).PHP_EOL;
foreach (array_slice($noPayment, 0, 40) as $line) {
    echo $line.PHP_EOL;
}
