<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Services\Property\EzenRentReceiptListingParser;
use Illuminate\Support\Facades\Schema;

$agentUserId = 2;
$file = $argv[1] ?? 'rent_receipts_listing (1).pdf';

$parser = app(EzenRentReceiptListingParser::class);
$rows = $parser->parsePath($file);
echo "File: {$file}\n";
echo 'Parsed rows: '.count($rows)."\n";

$byRc = [];
foreach ($rows as $row) {
    $rc = strtoupper(trim((string) ($row['ezen_receipt_no'] ?? '')));
    if ($rc === '') {
        continue;
    }
    $byRc[$rc] = $row;
}

$hasRegister = Schema::hasTable('pm_ezen_receipt_register');
echo 'Register table: '.($hasRegister ? 'yes' : 'no')."\n";

$existingRc = [];
$maxExisting = null;
$registerCount = 0;
if ($hasRegister) {
    $q = PmEzenReceiptRegister::query();
    if (Schema::hasColumn('pm_ezen_receipt_register', 'agent_user_id')) {
        $q->where('agent_user_id', $agentUserId);
    }
    $registerCount = (clone $q)->count();
    foreach ($q->get(['ezen_receipt_no', 'banking_date', 'amount', 'ref_no', 'tnt_account']) as $reg) {
        $rc = strtoupper(trim((string) $reg->ezen_receipt_no));
        $existingRc[$rc] = $reg;
        if (preg_match('/RC(\d+)/i', $rc, $m)) {
            $n = (int) $m[1];
            if ($maxExisting === null || $n > $maxExisting) {
                $maxExisting = $n;
            }
        }
    }
}

echo "Register rows (agent {$agentUserId}): {$registerCount}\n";
echo 'Max register RC: '.($maxExisting ? 'RC'.$maxExisting : 'none')."\n";

$missing = [];
$present = 0;
foreach ($byRc as $rc => $row) {
    if (isset($existingRc[$rc])) {
        $present++;
        continue;
    }
    $missing[] = $row;
}

usort($missing, static function ($a, $b) {
    return strcmp((string) ($b['banking_date'] ?? ''), (string) ($a['banking_date'] ?? ''))
        ?: strcmp((string) ($b['ezen_receipt_no'] ?? ''), (string) ($a['ezen_receipt_no'] ?? ''));
});

echo "Already in register: {$present}\n";
echo 'Missing from register: '.count($missing)."\n";

$totalMissingAmount = 0.0;
foreach ($missing as $row) {
    $totalMissingAmount += (float) ($row['amount'] ?? 0);
}
echo 'Missing amount total: '.number_format($totalMissingAmount, 2)."\n";

// Group by banking date
$byDate = [];
foreach ($missing as $row) {
    $d = (string) ($row['banking_date'] ?? 'unknown');
    $byDate[$d] = ($byDate[$d] ?? 0) + 1;
}
krsort($byDate);
echo "\nMissing by banking date:\n";
foreach ($byDate as $d => $c) {
    echo "  {$d}: {$c}\n";
}

echo "\nLatest missing receipts (up to 40):\n";
foreach (array_slice($missing, 0, 40) as $row) {
    echo sprintf(
        "%s | %s | %s | %s | %s | %s | %s\n",
        $row['ezen_receipt_no'] ?? '',
        $row['banking_date'] ?? '',
        $row['tnt_account'] ?? '',
        $row['tenant_name'] ?? '',
        number_format((float) ($row['amount'] ?? 0), 2),
        $row['ref_no'] ?? '',
        $row['receipted_to'] ?? ''
    );
}

// Also check which missing RCs already have a pm_payment with that external_ref / EZEN receipt in meta
$paymentHits = 0;
$paymentMiss = 0;
if (Schema::hasTable('pm_payments')) {
    foreach ($missing as $row) {
        $rc = (string) ($row['ezen_receipt_no'] ?? '');
        $ref = trim((string) ($row['ref_no'] ?? ''));
        $exists = PmPayment::query()
            ->where(function ($q) use ($rc, $ref) {
                $q->where('external_ref', $rc);
                if ($ref !== '') {
                    $q->orWhere('external_ref', $ref)
                        ->orWhere('external_ref', 'like', '%'.$ref.'%');
                }
            })
            ->exists();
        if ($exists) {
            $paymentHits++;
        } else {
            $paymentMiss++;
        }
    }
    echo "\nOf missing register rows — already have pm_payment by ref/RC: {$paymentHits}\n";
    echo "Of missing register rows — no pm_payment found: {$paymentMiss}\n";
}

$out = storage_path('app/missing_receipts_from_'.preg_replace('/[^a-zA-Z0-9]+/', '_', $file).'.json');
file_put_contents($out, json_encode([
    'file' => $file,
    'parsed' => count($rows),
    'register_count' => $registerCount,
    'max_register_rc' => $maxExisting,
    'missing_count' => count($missing),
    'missing_amount' => $totalMissingAmount,
    'missing_by_date' => $byDate,
    'missing' => array_map(static fn ($r) => [
        'ezen_receipt_no' => $r['ezen_receipt_no'] ?? null,
        'banking_date' => $r['banking_date'] ?? null,
        'txn_date' => $r['txn_date'] ?? null,
        'tnt_account' => $r['tnt_account'] ?? null,
        'tenant_name' => $r['tenant_name'] ?? null,
        'amount' => $r['amount'] ?? null,
        'ref_no' => $r['ref_no'] ?? null,
        'receipted_to' => $r['receipted_to'] ?? null,
        'property_code' => $r['property_code'] ?? null,
        'unit_label' => $r['unit_label'] ?? null,
    ], $missing),
], JSON_PRETTY_PRINT));
echo "\nWrote {$out}\n";
