<?php

require __DIR__.'/../vendor/autoload.php';

$json = json_decode(file_get_contents(__DIR__.'/../storage/app/missing_receipts_from_rent_receipts_listing_2_pdf.json'), true);
$missing = $json['missing'] ?? [];
$good = [];
$bad = [];
foreach ($missing as $r) {
    $amt = (float) ($r['amount'] ?? 0);
    if ($amt <= 0 || $amt > 500000) {
        $bad[] = $r;
    } else {
        $good[] = $r;
    }
}

echo 'Total missing RC: '.count($missing).PHP_EOL;
echo 'Sane amounts (<=500k): '.count($good).' total '.number_format(array_sum(array_map(fn ($r) => (float) $r['amount'], $good)), 2).PHP_EOL;
echo 'Suspect parse errors: '.count($bad).PHP_EOL;
foreach ($bad as $r) {
    echo ($r['ezen_receipt_no'] ?? '').' | '.($r['banking_date'] ?? '').' | '.($r['tnt_account'] ?? '').' | '.($r['amount'] ?? '').' | '.($r['ref_no'] ?? '').' | '.($r['tenant_name'] ?? '').PHP_EOL;
}

echo PHP_EOL.'--- GOOD missing by date ---'.PHP_EOL;
$by = [];
foreach ($good as $r) {
    $d = (string) ($r['banking_date'] ?? 'unknown');
    $by[$d] = ($by[$d] ?? 0) + 1;
}
krsort($by);
foreach ($by as $d => $c) {
    echo "{$d}: {$c}".PHP_EOL;
}

echo PHP_EOL.'Good list:'.PHP_EOL;
foreach ($good as $r) {
    echo sprintf(
        "%s | %s | %s | %s | %s | %s".PHP_EOL,
        $r['ezen_receipt_no'] ?? '',
        $r['banking_date'] ?? '',
        $r['tnt_account'] ?? '',
        number_format((float) ($r['amount'] ?? 0), 2),
        $r['ref_no'] ?? '',
        $r['tenant_name'] ?? ''
    );
}

// Peek raw extracted lines around bad RCs
$text = file_get_contents(__DIR__.'/../storage/app/tmp_rent_receipts_listing_2.pdf.txt');
echo PHP_EOL.'--- Raw snippets for bad RCs ---'.PHP_EOL;
foreach ($bad as $r) {
    $rc = (string) ($r['ezen_receipt_no'] ?? '');
    $pos = stripos($text, $rc);
    if ($pos === false) {
        echo "{$rc}: not found in text".PHP_EOL;
        continue;
    }
    echo "==== {$rc} ====".PHP_EOL;
    echo substr($text, max(0, $pos - 20), 450).PHP_EOL.PHP_EOL;
}
