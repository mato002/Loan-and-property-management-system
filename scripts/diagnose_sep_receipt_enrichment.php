<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Services\Property\EzenRentReceiptListingParser;
use Carbon\Carbon;

$payments = PmPayment::withoutGlobalScopes()
    ->where('channel', 'ezen_import')
    ->whereDate('paid_at', '2026-09-01')
    ->get(['id', 'pm_tenant_id', 'amount', 'paid_at', 'external_ref', 'meta']);

echo "Sep-1 ezen_import payments: {$payments->count()}\n";

$parser = app(EzenRentReceiptListingParser::class);
$rows = $parser->parsePath(__DIR__.'/../storage/passion-legacy/rent_receipts_listing.txt');

$sepReceipts = array_values(array_filter($rows, function (array $row): bool {
    $bank = Carbon::parse((string) ($row['banking_date'] ?? ''));
    return $bank->year === 2026 && $bank->month === 9;
}));
echo 'September 2026 receipts: '.count($sepReceipts)."\n\n";

$byTenantAmount = $payments->groupBy(fn ($p) => $p->pm_tenant_id.'|'.number_format((float) $p->amount, 2, '.', ''));

$matched = 0;
$noTenant = 0;
$noPayment = 0;
$multi = 0;

foreach ($sepReceipts as $row) {
    $account = strtoupper(trim((string) ($row['tnt_account'] ?? '')));
    $candidates = [$account];
    if (preg_match('/^TNT0*(\d+)$/', $account, $m)) {
        $candidates[] = 'TNT'.str_pad($m[1], 5, '0', STR_PAD_LEFT);
        $candidates[] = 'TNT'.str_pad($m[1], 6, '0', STR_PAD_LEFT);
    }
    $tenant = PmTenant::withoutGlobalScopes()->whereIn('account_number', array_unique($candidates))->orderByDesc('id')->first();
    if (! $tenant) {
        $noTenant++;
        continue;
    }

    $key = $tenant->id.'|'.number_format((float) $row['amount'], 2, '.', '');
    $hits = $byTenantAmount->get($key, collect());
    if ($hits->isEmpty()) {
        $noPayment++;
        continue;
    }
    if ($hits->count() > 1) {
        $multi++;
    }
    $matched++;
}

echo "Sep receipts matched to Sep-1 payment (tenant+amount): {$matched}\n";
echo "Sep receipts no tenant: {$noTenant}\n";
echo "Sep receipts no payment match: {$noPayment}\n";
echo "Sep receipts multi payment match: {$multi}\n";

// sample unmatched
$sample = 0;
foreach ($sepReceipts as $row) {
    if ($sample >= 5) break;
    $account = strtoupper(trim((string) ($row['tnt_account'] ?? '')));
    $tenant = PmTenant::withoutGlobalScopes()->where('account_number', $account)->first();
    if (! $tenant && preg_match('/^TNT0*(\d+)$/', $account, $m)) {
        $tenant = PmTenant::withoutGlobalScopes()->where('account_number', 'TNT'.str_pad($m[1], 5, '0', STR_PAD_LEFT))->first();
    }
    if (! $tenant) continue;
    $key = $tenant->id.'|'.number_format((float) $row['amount'], 2, '.', '');
    if ($byTenantAmount->get($key, collect())->isEmpty()) {
        echo "Unmatched: {$row['ezen_receipt_no']} {$account} amt={$row['amount']} ref={$row['ref_no']}\n";
        $sample++;
    }
}
