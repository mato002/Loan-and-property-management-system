<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = new App\Services\Property\EzenBillsListingParser();

$csv = <<<'CSV'
BILL #,VEN. INV #,DATE,DUE DATE,CUSTOMER,MEMO,TOTAL AMT,TOTAL PAID,AMT DUE
Closed
4500,139,06/08/2025,15/08/2025,TOSHIA COMPANY LTD,GARBAGE COLLECTION BILL,4500,4500,0
AP0020,117,10/07/2025,15/07/2025,TOSHIA COMPANY LTD,GARBAGE COLLECTION BILL,4500,4500,0
CSV;
$csvRows = $parser->parseText($csv, 'sample.csv');
echo 'csv_count='.count($csvRows).PHP_EOL;

$paths = [
    __DIR__.'/../storage/passion-legacy/bills_list.txt',
    __DIR__.'/../storage/passion-legacy/bills_list.pdf',
    __DIR__.'/../bills_list.pdf',
];
$fileRows = [];
foreach ($paths as $path) {
    if (! is_file($path)) {
        echo 'missing '.$path.PHP_EOL;
        continue;
    }
    $fileRows = $parser->parsePath($path);
    echo 'file='.$path.' count='.count($fileRows).' total='.array_sum(array_column($fileRows, 'total_amount')).PHP_EOL;
    break;
}

if (count($csvRows) !== 2) {
    fwrite(STDERR, 'Expected 2 CSV rows, got '.count($csvRows).PHP_EOL);
    exit(1);
}

if ($fileRows === []) {
    fwrite(STDERR, "Could not parse bills list file.\n");
    exit(1);
}

$sum = round(array_sum(array_map(fn ($r) => (float) $r['total_amount'], $fileRows)), 2);
echo 'parsed_sum='.$sum.PHP_EOL;
$vendors = array_count_values(array_column($fileRows, 'vendor_name'));
print_r($vendors);

$wrapped = array_values(array_filter($fileRows, fn ($r) => $r['ezen_bill_no'] === 'AP0018' || $r['ezen_bill_no'] === 'AP0011'));
foreach ($wrapped as $row) {
    echo implode(' | ', [
        $row['ezen_bill_no'],
        $row['vendor_invoice_no'],
        $row['bill_date'],
        $row['vendor_name'],
        $row['memo'],
        $row['total_amount'],
    ]).PHP_EOL;
}

if (count($fileRows) < 50) {
    fwrite(STDERR, 'Expected at least 50 bills, got '.count($fileRows).PHP_EOL);
    exit(1);
}
if (abs($sum - 250400) > 0.5) {
    fwrite(STDERR, 'Expected total 250400, got '.$sum.PHP_EOL);
    exit(1);
}

echo "ok\n";
