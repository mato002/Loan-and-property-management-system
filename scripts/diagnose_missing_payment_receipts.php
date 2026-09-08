<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Services\Property\EzenRentReceiptListingParser;
use Carbon\Carbon;

$paymentIds = array_slice(array_map('intval', $argv), 1);
if ($paymentIds === []) {
    $paymentIds = PmPayment::withoutGlobalScopes()
        ->where('channel', 'ezen_import')
        ->whereDate('paid_at', '2026-09-01')
        ->get()
        ->filter(fn ($p) => trim((string) data_get($p->meta, 'mpesa_ref', '')) === '')
        ->pluck('id')
        ->all();
}

$parser = app(EzenRentReceiptListingParser::class);
$txtPath = __DIR__.'/../storage/passion-legacy/rent_receipts_listing.txt';
$pdfRows = file_exists($txtPath) ? $parser->parsePath($txtPath) : [];

foreach ($paymentIds as $id) {
    $payment = PmPayment::withoutGlobalScopes()->with(['allocations.invoice.unit.property', 'tenant'])->find($id);
    if (! $payment) {
        echo "PAY-{$id}: NOT FOUND\n\n";
        continue;
    }

    $tenant = PmTenant::withoutGlobalScopes()->find($payment->pm_tenant_id);
    $account = $tenant?->account_number ?? '?';
    $ref = trim((string) data_get($payment->meta, 'mpesa_ref', ''));
    $ext = trim((string) ($payment->external_ref ?? ''));
    $unit = $payment->allocations->first()?->invoice?->unit;
    $unitLabel = trim((string) ($unit?->label ?? ''));
    $propCode = trim((string) ($unit?->property?->code ?? ''));

    echo "=== PAY-{$id} amt={$payment->amount} tenant={$account} ext={$ext} ref=".($ref ?: '—')." ===\n";
    echo "  unit={$propCode} / {$unitLabel}\n";
    echo "  paid_at={$payment->paid_at}\n";

    $regs = PmEzenReceiptRegister::withoutGlobalScopes()
        ->where('pm_tenant_id', $payment->pm_tenant_id)
        ->get();

    $sepRegs = $regs->filter(function ($r) {
        $d = Carbon::parse($r->banking_date ?? $r->txn_date);

        return $d->year === 2026 && $d->month === 9;
    });

    echo "  register rows (tenant): {$regs->count()}, sep2026: {$sepRegs->count()}\n";

    $exactReg = $regs->first(fn ($r) => abs((float) $r->amount - (float) $payment->amount) < 0.02);
    if ($exactReg) {
        echo "  exact amount register: {$exactReg->ezen_receipt_no} ref={$exactReg->ref_no} unit={$exactReg->unit_label} date={$exactReg->banking_date}\n";
    }

    $paySum = round((float) PmPayment::withoutGlobalScopes()
        ->where('pm_tenant_id', $payment->pm_tenant_id)
        ->where('channel', 'ezen_import')
        ->whereYear('paid_at', 2026)
        ->whereMonth('paid_at', 9)
        ->sum('amount'), 2);
    $regSum = round((float) $sepRegs->sum('amount'), 2);
    echo "  sep paySum={$paySum} regSum={$regSum}\n";

    // PDF rows for tenant
    $tenantRows = array_values(array_filter($pdfRows, function (array $row) use ($account, $tenant): bool {
        $acc = strtoupper(trim((string) ($row['tnt_account'] ?? '')));
        if ($acc === strtoupper($account)) {
            return true;
        }
        if ($tenant && preg_match('/^TNT0*(\d+)$/', $acc, $m)) {
            return preg_match('/^TNT0*(\d+)$/', strtoupper($account), $tm) && $m[1] === $tm[1];
        }

        return false;
    }));

    $exactPdf = array_values(array_filter($tenantRows, fn ($r) => abs((float) ($r['amount'] ?? 0) - (float) $payment->amount) < 0.02));
    echo '  PDF rows for tenant: '.count($tenantRows).', exact amount: '.count($exactPdf)."\n";
    foreach (array_slice($exactPdf, 0, 3) as $row) {
        echo "    PDF {$row['ezen_receipt_no']} amt={$row['amount']} ref={$row['ref_no']} unit={$row['unit_label']} bank={$row['banking_date']}\n";
    }

    $linkedReg = PmEzenReceiptRegister::withoutGlobalScopes()->where('pm_payment_id', $id)->first();
    echo '  linked register: '.($linkedReg ? $linkedReg->ezen_receipt_no.' ref='.$linkedReg->ref_no : 'none')."\n\n";
}
