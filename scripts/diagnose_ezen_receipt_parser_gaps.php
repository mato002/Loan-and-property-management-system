<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = __DIR__.'/../storage/passion-legacy/rent_receipts_listing.txt';
$text = file_get_contents($path);

preg_match_all('/\b(RC\d+)\b/i', $text, $all);
$allRc = array_unique(array_map('strtoupper', $all[1]));
sort($allRc);

$parser = new App\Services\Property\EzenRentReceiptListingParser();
$rows = $parser->parsePath($path);
$parsedRc = array_unique(array_column($rows, 'ezen_receipt_no'));
sort($parsedRc);

$missing = array_values(array_diff($allRc, $parsedRc));
echo 'Total RC refs: '.count($allRc).PHP_EOL;
echo 'Parsed rows: '.count($rows).PHP_EOL;
echo 'Unique parsed RC: '.count($parsedRc).PHP_EOL;
echo 'Missing RC: '.count($missing).PHP_EOL;

foreach (array_slice($missing, 0, 10) as $rc) {
    echo "\n--- {$rc} ---\n";
    $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
    foreach ($lines as $i => $line) {
        if (stripos($line, $rc) !== false) {
            for ($j = max(0, $i); $j <= min(count($lines) - 1, $i + 4); $j++) {
                echo ($j + 1).': '.$lines[$j].PHP_EOL;
            }
            break;
        }
    }
}
