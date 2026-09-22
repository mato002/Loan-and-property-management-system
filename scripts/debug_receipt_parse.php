<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\EzenRentReceiptListingParser::class);
$text = file_get_contents(__DIR__.'/../storage/app/tmp_rent_receipts_listing_2.pdf.txt');
$rows = $parser->parseText($text);
echo 'parsed: '.count($rows).PHP_EOL;
if ($rows) {
    echo json_encode($rows[0], JSON_PRETTY_PRINT).PHP_EOL;
    foreach (array_slice($rows, 0, 5) as $r) {
        echo ($r['ezen_receipt_no'] ?? '').' '.$r['amount'].' '.$r['tnt_account'].' '.$r['receipted_to'].PHP_EOL;
    }
} else {
    // Try one combined sample
    $ref = new ReflectionClass($parser);
    $m = $ref->getMethod('peelFinishFromCombined');
    $m->setAccessible(true);
    $sample = 'RC25250 22/09/2026 22/09/2026 UIMNQ7JCKW HSE B2 TNT001135 TRACY AREGE 0708733828 Rent for Sep/2026, Late payment charge September/2026 9,000 CO-OPERATIVE BANK LYDIAH MBUGUA';
    var_export($m->invoke($parser, $sample));
    echo PHP_EOL;
}
