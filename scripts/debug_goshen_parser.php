<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Property\PassionLegacyUnitRegisterParser;
use App\Services\Property\PassionLegacyTextNormalizer;

$parser = app(PassionLegacyUnitRegisterParser::class);
$text = PassionLegacyTextNormalizer::stripRegisterNoise(file_get_contents(storage_path('passion-legacy/property_unit_register.txt')));

$ref = new ReflectionClass($parser);
$methods = ['looksLikeUnitStart', 'looksLikeStatusTail', 'looksLikeUnitTypeContinuation', 'bufferLooksComplete', 'isPropertySectionHeader', 'isFooterTotal'];
$fn = [];
foreach ($methods as $m) {
    $r = $ref->getMethod($m);
    $r->setAccessible(true);
    $fn[$m] = fn (...$args) => $r->invoke($parser, ...$args);
}

$inGoshen = false;
foreach (preg_split('/\R/', $text) ?: [] as $i => $line) {
    $line = trim($line);
    if (str_contains($line, 'GOSHEN APARTMENT, RESIDENTIAL')) {
        $inGoshen = true;
    }
    if ($inGoshen && str_contains($line, 'LEMAYAN APPARTMENT')) {
        break;
    }
    if ($inGoshen && $line !== '') {
        echo sprintf(
            "L%d: %-80s unitStart=%s statusTail=%s typeCont=%s footer=%s\n",
            $i,
            substr($line, 0, 80),
            $fn['looksLikeUnitStart']($line) ? 'Y' : 'N',
            $fn['looksLikeStatusTail']($line) ? 'Y' : 'N',
            $fn['looksLikeUnitTypeContinuation']($line) ? 'Y' : 'N',
            $fn['isFooterTotal']($line) ? 'Y' : 'N',
        );
    }
}

$records = $parser->parse(file_get_contents(storage_path('passion-legacy/property_unit_register.txt')));
echo "\nGOSHEN records: ".count(array_filter($records, fn ($r) => stripos($r['property_name'], 'GOSHEN') !== false)).PHP_EOL;
foreach (array_filter($records, fn ($r) => stripos($r['property_name'], 'GOSHEN') !== false) as $r) {
    echo $r['unit_label'].' '.$r['status'].PHP_EOL;
}
