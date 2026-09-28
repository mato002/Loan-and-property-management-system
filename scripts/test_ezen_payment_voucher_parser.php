<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = new App\Services\Property\EzenPaymentVoucherListingParser();

$csvPath = __DIR__.'/../storage/passion-legacy/payment_vouchers_listing.sample.csv';
$csvRows = $parser->parsePath($csvPath);
echo 'csv_count='.count($csvRows).PHP_EOL;
foreach ($csvRows as $row) {
    echo implode(' | ', [
        $row['ezen_voucher_no'],
        $row['method'],
        $row['category'],
        $row['period_month'] ?? '',
        $row['property_code'] ?: '-',
        $row['payee_name'],
        number_format((float) $row['amount'], 2),
    ]).PHP_EOL;
}

$layout = <<<'TXT'
PASSION SHELTAZ INVESTMENTS
PAYMENT VOUCHER LISTING
VOUCHER # METHOD REF NO DATE PARTICULARS PAID FROM PAID TO AMOUNT RECORDED BY
PM04791 Cash CASH 31/08/2026 AIRTIME CASH ACCOUNT SAFARICOM 100 MWANGI HELLEN
PM04789 Mpesa UHB9M2N5HK 31/08/2026 Rent Remittance, period of AUGUST/2026 CO-OPERATIVE BANK [M00044B] SUNRISE KIAMUNYI 18,900 SAM 0
PM04785 Bank Deposit KEP5EhbkipE/ABC123 18/08/2026 RENTAL REMITTANCE AUGUST 2026 CO-OPERATIVE BANK DAVID NJOROGE MUNIU 252,557 MWANGI HELLEN
PM04774 Cheque 000219 18/08/2026 Rent Remittance, period of AUGUST/2026 CO-OPERATIVE BANK FRACIAH NYAMBURA GITHUA 279,460 SAM 0
PM04780 Cash CASH 18/08/2026 COMMISSION LEMAYAN CASH ACCOUNT PATRICK KAMAU 80,000 MWANGI HELLEN
TXT;

$layoutRows = $parser->parseText($layout);
echo 'layout_count='.count($layoutRows).PHP_EOL;
foreach ($layoutRows as $row) {
    echo implode(' | ', [
        $row['ezen_voucher_no'],
        $row['method'],
        $row['ref_no'],
        $row['category'],
        $row['paid_to'],
        number_format((float) $row['amount'], 2),
    ]).PHP_EOL;
}

if (count($csvRows) !== 8) {
    fwrite(STDERR, 'Expected 8 CSV rows, got '.count($csvRows).PHP_EOL);
    exit(1);
}
if (count($layoutRows) !== 5) {
    fwrite(STDERR, 'Expected 5 layout rows, got '.count($layoutRows).PHP_EOL);
    exit(1);
}

echo "ok\n";
