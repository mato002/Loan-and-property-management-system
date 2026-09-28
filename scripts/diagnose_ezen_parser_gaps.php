<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = __DIR__.'/../storage/rent_invoices_listing_extracted.txt';
$text = file_get_contents($path);

preg_match_all('/\b(INV\d+)\b/i', $text, $all);
$allInv = array_unique(array_map('strtoupper', $all[1]));
sort($allInv);

$parser = new App\Services\Property\EzenRentalInvoiceScheduleParser();
$rows = $parser->parsePath($path);
$parsedInv = array_unique(array_column($rows, 'ezen_invoice_no'));
sort($parsedInv);

$missing = array_values(array_diff($allInv, $parsedInv));
echo 'Total INV refs: '.count($allInv).PHP_EOL;
echo 'Parsed rows: '.count($rows).PHP_EOL;
echo 'Unique parsed INV: '.count($parsedInv).PHP_EOL;
echo 'Missing INV: '.count($missing).PHP_EOL;

// Show context for first 15 missing
$lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
foreach (array_slice($missing, 0, 15) as $inv) {
    echo "\n--- {$inv} ---\n";
    foreach ($lines as $i => $line) {
        if (stripos($line, $inv) !== false) {
            for ($j = max(0, $i - 0); $j <= min(count($lines) - 1, $i + 4); $j++) {
                echo ($j + 1).': '.$lines[$j].PHP_EOL;
            }
            break;
        }
    }
}
