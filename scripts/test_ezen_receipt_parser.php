<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sample = <<<'TXT'
PASSION SHELTAZ INVESTMENTS
RENT RECEIPT LISTING
RECEIPT # TXN DATE BANKING DATE REF # UNIT # A/C NO TENANT Phone No PARTICULARS AMOUNT RECEIPTED TO Done by
RC24959 08/09/2026 08/09/2026 UI8LSUIFGC HSE 7 TNT00566 ANN MUTHONI 0708136764 Rent for Sep/2026,GARBAGE for Sep/2026,WATER Meter Reading 3.00 units 5,200 CO-OPERATIVE BANK LYDIAH MBUGUA
RC24958 08/09/2026 07/09/2026 UI8MP613X2 S3 TNT00744 MWANGI JOYCE NJOKI 0712345678 Rent for Sep/2026 13,500 CO-OPERATIVE BANK LYDIAH MBUGUA
TXT;

$parser = new App\Services\Property\EzenRentReceiptListingParser();
$rows = $parser->parseText($sample);
echo 'count='.count($rows).PHP_EOL;
var_export($rows);
