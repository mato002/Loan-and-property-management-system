<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = $argv[1] ?? '';
if ($path === '' || ! is_file($path)) {
    fwrite(STDERR, "Usage: php scripts/extract_passion_pdf.php <pdf-path>\n");
    exit(1);
}

$extractor = new App\Services\Property\PassionLegacyRegisterPdfTextExtractor();
$text = $extractor->extract($path);
$out = __DIR__.'/../storage/'.pathinfo($path, PATHINFO_FILENAME).'_extracted.txt';
file_put_contents($out, $text);
echo "Wrote {$out} (".strlen($text)." bytes)\n";
