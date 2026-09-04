<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$parser = app(App\Services\Property\PassionLegacyUnitRegisterParser::class);
$text = file_get_contents(storage_path('passion-legacy/property_unit_register.txt'));
$parsed = $parser->parse($text);

$filter = static fn (array $r) => stripos($r['property_name'], 'ST. JOHN') !== false
    || stripos($r['property_name'], 'KAGIO') !== false
    || stripos($r['unit_label'], 'HSE') !== false && stripos($r['property_name'], 'MAINA') !== false;

$stJohns = array_values(array_filter($parsed, $filter));
echo 'ST JOHNS parsed: '.count($stJohns).PHP_EOL;
foreach ($stJohns as $r) {
    echo $r['unit_label'].' | '.$r['property_name'].' | status='.$r['status'].' | tenant='.($r['tenant_name'] ?? 'null').PHP_EOL;
}

echo PHP_EOL.'=== All properties with register vs parsed gap ==='.PHP_EOL;
$propParser = app(App\Services\Property\PassionLegacyRegisterParser::class);
$extractor = app(App\Services\Property\PassionLegacyRegisterPdfTextExtractor::class);
$propReg = $propParser->parse($extractor->extract(storage_path('passion-legacy/property_register.txt')));
$codeResolver = app(App\Services\Property\PassionPropertyCodeResolver::class);

$parsedByProp = [];
foreach ($parsed as $r) {
    $p = $codeResolver->resolveByNameViaRegister($r['property_name'], $propReg);
    if (! $p) {
        continue;
    }
    $parsedByProp[$p->code][] = $r['unit_label'];
}

$gaps = [];
foreach ($propReg as $row) {
    $code = (string) $row['code'];
    $expected = (int) ($row['occupied_count'] ?? 0) + (int) ($row['vacant_count'] ?? 0);
    $got = count($parsedByProp[$code] ?? []);
    if ($expected !== $got) {
        $gaps[] = [
            'code' => $code,
            'name' => $row['name'],
            'expected' => $expected,
            'parsed' => $got,
            'delta' => $got - $expected,
        ];
    }
}

usort($gaps, fn ($a, $b) => $a['delta'] <=> $b['delta']);
foreach ($gaps as $g) {
    echo sprintf("[%s] %s — register %d, parsed %d (delta %+d)\n", $g['code'], trim(preg_replace('/\s+/', ' ', $g['name'])), $g['expected'], $g['parsed'], $g['delta']);
}

$missingTotal = array_sum(array_map(fn ($g) => max(0, $g['expected'] - $g['parsed']), $gaps));
$extraTotal = array_sum(array_map(fn ($g) => max(0, $g['parsed'] - $g['expected']), $gaps));
echo PHP_EOL."Under-parsed vs property register: {$missingTotal}".PHP_EOL;
echo "Over-parsed vs property register: {$extraTotal}".PHP_EOL;
echo 'Net register gap: '.($missingTotal - $extraTotal).PHP_EOL;
echo 'Ezen 445 - parsed '.count($parsed).' = '.(445 - count($parsed)).PHP_EOL;
echo 'Ezen 445 - property register 442 = '.(445 - 442).PHP_EOL;
