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
    ->where('external_ref', 'like', 'EZEN-INV%')
    ->get(['id', 'pm_tenant_id', 'amount', 'paid_at']);

function monthKey($tenantId, $amount, Carbon $date): string {
    return $tenantId.'|'.number_format((float) $amount, 2, '.', '').'|'.$date->format('Y-m');
}

$index = $payments->groupBy(fn ($p) => monthKey($p->pm_tenant_id, $p->amount, Carbon::parse($p->paid_at)));

$parser = app(EzenRentReceiptListingParser::class);
$rows = $parser->parsePath(__DIR__.'/../storage/passion-legacy/rent_receipts_listing.txt');

$same = 0; $prev = 0; $next = 0; $none = 0; $resolved = 0;

foreach ($rows as $row) {
    $account = strtoupper(trim((string) ($row['tnt_account'] ?? '')));
    $candidates = [$account];
    if (preg_match('/^TNT0*(\d+)$/', $account, $m)) {
        $candidates[] = 'TNT'.str_pad($m[1], 5, '0', STR_PAD_LEFT);
    }
    $tenant = PmTenant::withoutGlobalScopes()->whereIn('account_number', array_unique($candidates))->orderByDesc('id')->first();
    if (! $tenant) continue;
    $resolved++;

    $bank = Carbon::parse((string) ($row['banking_date'] ?? $row['txn_date']));
    $amount = (float) $row['amount'];
    $keys = [
        'same' => monthKey($tenant->id, $amount, $bank),
        'prev' => monthKey($tenant->id, $amount, $bank->copy()->subMonth()),
        'next' => monthKey($tenant->id, $amount, $bank->copy()->addMonth()),
    ];

    if (($index->get($keys['same'], collect()))->isNotEmpty()) { $same++; continue; }
    if (($index->get($keys['prev'], collect()))->isNotEmpty()) { $prev++; continue; }
    if (($index->get($keys['next'], collect()))->isNotEmpty()) { $next++; continue; }
    $none++;
}

echo "Resolved receipts: {$resolved}\n";
echo "Same month match: {$same}\n";
echo "Previous month match: {$prev}\n";
echo "Next month match: {$next}\n";
echo "No month window match: {$none}\n";
