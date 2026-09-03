<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$text = file_get_contents(__DIR__.'/../storage/leases_extracted.txt');
$parser = app(App\Services\Property\PassionLegacyLeasesRegisterParser::class);
$parsed = $parser->parse($text);

$parsedAccounts = [];
foreach ($parsed as $row) {
    $parsedAccounts[strtoupper($row['account_number'])] = true;
}

preg_match_all('/TNT\d+/i', $text, $matches);
$allAccounts = array_unique(array_map('strtoupper', $matches[0]));

$missing = array_values(array_diff($allAccounts, array_keys($parsedAccounts)));
echo 'File accounts: '.count($allAccounts)."\n";
echo 'Parsed accounts: '.count($parsedAccounts)."\n";
echo 'Missing: '.count($missing)."\n\n";

$currentCode = '';
$lines = preg_split('/\R/', $text) ?: [];
$shown = 0;
foreach ($lines as $line) {
    if (preg_match('/^\[([A-Z]\d{5}[A-Z]?)\]/', trim($line), $m)) {
        $currentCode = $m[1];
    }
    if (preg_match('/TNT\d+/i', $line, $m)) {
        $account = strtoupper($m[0]);
        if (in_array($account, $missing, true)) {
            echo "{$account} [{$currentCode}] {$line}\n";
            $shown++;
            if ($shown >= 20) {
                break;
            }
        }
    }
}
