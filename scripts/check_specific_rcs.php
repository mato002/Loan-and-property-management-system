<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\EzenRentReceiptListingParser::class);
$rows = $parser->parsePath('rent_receipts_listing (2).pdf');
foreach ($rows as $r) {
    if (in_array($r['ezen_receipt_no'], ['RC25239', 'RC25234', 'RC25215', 'RC25211'], true)) {
        echo json_encode($r, JSON_PRETTY_PRINT).PHP_EOL;
    }
}
