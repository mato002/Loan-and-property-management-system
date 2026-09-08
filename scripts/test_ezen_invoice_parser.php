<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = new App\Services\Property\EzenRentalInvoiceScheduleParser();
$rows = $parser->parsePath(__DIR__.'/../storage/rent_invoices_listing_extracted.txt');
echo 'count='.count($rows).PHP_EOL;
$sample = collect($rows)->firstWhere('ezen_invoice_no', 'INV38125');
var_export($sample);
echo PHP_EOL;
$goshen = collect($rows)->firstWhere('ezen_invoice_no', 'INV38661');
var_export($goshen);
echo PHP_EOL;
