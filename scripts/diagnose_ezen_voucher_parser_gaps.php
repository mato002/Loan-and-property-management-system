<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = __DIR__.'/../storage/passion-legacy/payment_vouchers_listing.txt';
if (! is_file($path)) {
    $path = __DIR__.'/../storage/payment_vouchers_listing_extracted.txt';
}

$parser = new App\Services\Property\EzenPaymentVoucherListingParser();
$rows = $parser->parsePath($path);
$parsedNos = [];
foreach ($rows as $row) {
    $parsedNos[$row['ezen_voucher_no']] = true;
}

$text = file_get_contents($path);
preg_match_all('/^PM(\d+)/m', (string) $text, $matches);
$missing = [];
foreach ($matches[1] as $digits) {
    $no = 'PM'.str_pad((string) ((int) $digits), 5, '0', STR_PAD_LEFT);
    if (! isset($parsedNos[$no])) {
        $missing[$no] = true;
    }
}

echo 'parsed='.count($rows).PHP_EOL;
echo 'pm_starts='.count($matches[1]).PHP_EOL;
echo 'missing='.count($missing).PHP_EOL;

$cats = [];
foreach ($rows as $row) {
    $cat = $row['category'] ?? '?';
    $cats[$cat] = ($cats[$cat] ?? 0) + 1;
}
echo 'categories='.json_encode($cats).PHP_EOL;
echo 'sample_missing='.implode(',', array_slice(array_keys($missing), 0, 40)).PHP_EOL;

$lines = preg_split("/\r\n|\n|\r/", (string) $text) ?: [];
$shown = 0;
foreach ($lines as $i => $line) {
    if (preg_match('/^PM(\d+)/', trim($line), $m) !== 1) {
        continue;
    }
    $no = 'PM'.str_pad((string) ((int) $m[1]), 5, '0', STR_PAD_LEFT);
    if (! isset($missing[$no]) || $shown >= 8) {
        continue;
    }
    echo "---- {$no} line ".($i + 1)." ----\n";
    for ($j = $i; $j < min($i + 8, count($lines)); $j++) {
        echo $lines[$j]."\n";
        if ($j > $i && preg_match('/^PM\d+/', trim($lines[$j]))) {
            break;
        }
    }
    $shown++;
}
