<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$x = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$parser = app(App\Services\Property\EzenRentReceiptListingParser::class);

foreach (['rent_receipts_listing (1).pdf', 'rent_receipts_listing (2).pdf', 'rent_receipts_listing.pdf'] as $f) {
    echo "=== {$f} ===\n";
    try {
        $cands = $x->extractCandidates($f);
        echo 'candidates: '.count($cands)."\n";
        if ($cands) {
            $t = $cands[0];
            echo 'len: '.strlen($t)."\n";
            echo 'RC count approx: '.preg_match_all('/RC\d+/i', $t)."\n";
            echo substr($t, 0, 800)."\n---\n";
            file_put_contents(storage_path('app/tmp_'.str_replace([' ', '(', ')'], ['_', '', ''], $f).'.txt'), $t);
            $rows = $parser->parseText($t);
            echo 'parsed rows: '.count($rows)."\n";
            if ($rows) {
                usort($rows, fn ($a, $b) => strcmp($b['banking_date'] ?? '', $a['banking_date'] ?? ''));
                echo 'latest banking_date: '.($rows[0]['banking_date'] ?? '?')."\n";
                echo 'oldest banking_date: '.($rows[count($rows) - 1]['banking_date'] ?? '?')."\n";
                $maxRc = 0;
                foreach ($rows as $r) {
                    if (preg_match('/RC(\d+)/i', (string) $r['ezen_receipt_no'], $m)) {
                        $maxRc = max($maxRc, (int) $m[1]);
                    }
                }
                echo "max RC: RC{$maxRc}\n";
            }
        }
    } catch (Throwable $e) {
        echo 'ERR: '.$e->getMessage()."\n";
    }
    echo "\n";
}
