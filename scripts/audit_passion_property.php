<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PmLease;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\PassionLegacyLeasesRegisterParser;
use App\Services\Property\PassionLegacyRegisterParser;
use App\Services\Property\PassionLegacyRegisterPdfTextExtractor;
use App\Services\Property\PassionLegacyUnitRegisterParser;
use App\Services\Property\PassionLegacyTextNormalizer;
use App\Services\Property\PassionPropertyCodeResolver;

$agentId = (int) ($argv[1] ?? 1);
$filter = $argv[2] ?? null;

$base = storage_path('passion-legacy');
$extractor = app(PassionLegacyRegisterPdfTextExtractor::class);
$unitParser = app(PassionLegacyUnitRegisterParser::class);
$leasesParser = app(PassionLegacyLeasesRegisterParser::class);
$propertyParser = app(PassionLegacyRegisterParser::class);
$codeResolver = app(PassionPropertyCodeResolver::class);

$propertyRegister = $propertyParser->parse($extractor->extract($base.'/property_register.txt'));
$unitRecords = $unitParser->parse($extractor->extract($base.'/property_unit_register.txt'));
$leaseRecords = $leasesParser->parse($extractor->extract($base.'/leases.txt'));

    $expectedByCode = [];
    foreach ($unitRecords as $record) {
        $property = $codeResolver->resolveByNameViaRegister($record['property_name'], $propertyRegister);
        if (! $property) {
            continue;
        }
        $code = $codeResolver->normalizeCode((string) $property->code);
        $expectedByCode[$code][] = [
            'label' => PassionLegacyTextNormalizer::normalizeUnitLabel((string) $record['unit_label']),
            'status' => (string) $record['status'],
            'rent' => (float) ($record['market_rent'] ?? $record['rent_amount'] ?? 0),
            'tenant_hint' => trim((string) ($record['tenant_name'] ?? '')),
            'register_name' => trim((string) $record['property_name']),
        ];
    }

/** @var array<string, list<array<string, mixed>>> $leasesByCode */
$leasesByCode = [];
foreach ($leaseRecords as $record) {
    $leasesByCode[(string) $record['property_code']][] = [
        'label' => PassionLegacyTextNormalizer::normalizeUnitLabel((string) ($record['unit_label'] ?? '')),
        'tenant' => trim((string) ($record['tenant_name'] ?? '')),
        'account' => (string) ($record['account_number'] ?? ''),
    ];
}

$properties = Property::query()
    ->withoutGlobalScopes()
    ->where('agent_user_id', $agentId)
    ->orderBy('name')
    ->get();

$issueCount = 0;

foreach ($properties as $property) {
    if ($filter !== null && stripos($property->name, $filter) === false && stripos((string) $property->code, $filter) === false) {
        continue;
    }

    $code = $codeResolver->normalizeCode((string) $property->code);
    $expected = $expectedByCode[$code] ?? null;
    $registerName = null;
    foreach ($propertyRegister as $row) {
        if ($codeResolver->normalizeCode((string) $row['code']) === $code) {
            $registerName = trim((string) $row['name']);
            break;
        }
    }
    if ($registerName === null && $expected !== null) {
        $registerName = $expected[0]['register_name'] ?? null;
    }

    $dbUnits = PropertyUnit::query()
        ->withoutGlobalScopes()
        ->where('property_id', $property->id)
        ->with(['leases' => fn ($q) => $q->where('pm_leases.status', PmLease::STATUS_ACTIVE)->with('pmTenant:id,name')])
        ->orderBy('label')
        ->get();

    $expectedLeases = $leasesByCode[$codeResolver->normalizeCode((string) $property->code)] ?? [];

    $issues = [];
    $expCount = $expected ? count($expected) : 0;
    if ($expCount !== $dbUnits->count()) {
        $issues[] = "unit count: expected {$expCount}, db {$dbUnits->count()}";
    }

    if ($expected) {
        $expectedLabels = collect($expected)->pluck('label')->all();
        $dbLabels = $dbUnits->pluck('label')->map(fn ($l) => PassionLegacyTextNormalizer::normalizeUnitLabel($l))->all();
        $missing = [];
        foreach ($expectedLabels as $expectedLabel) {
            if (! $dbUnits->contains(fn ($u) => PassionLegacyTextNormalizer::registerUnitLabelMatch($expectedLabel, $u->label))) {
                $missing[] = $expectedLabel;
            }
        }
        $extra = [];
        foreach ($dbLabels as $dbLabel) {
            if (! collect($expectedLabels)->contains(fn ($expectedLabel) => PassionLegacyTextNormalizer::registerUnitLabelMatch($expectedLabel, $dbLabel))) {
                $extra[] = $dbLabel;
            }
        }
        if ($missing !== []) {
            $issues[] = 'missing units: '.implode(', ', $missing);
        }
        if ($extra !== []) {
            $issues[] = 'extra units: '.implode(', ', $extra);
        }

        foreach ($expected as $exp) {
            $unit = $dbUnits->first(fn ($u) => PassionLegacyTextNormalizer::registerUnitLabelMatch($exp['label'], $u->label));
            if (! $unit) {
                continue;
            }
            if ($unit->status !== $exp['status']) {
                $issues[] = "{$exp['label']} status expected {$exp['status']}, got {$unit->status}";
            }
            $lease = $unit->leases->first();
            $tenant = (string) ($lease?->pmTenant?->name ?? '');
            if ($exp['status'] === 'occupied' && $tenant === '' && trim((string) ($exp['tenant_hint'] ?? '')) !== '') {
                $issues[] = "{$exp['label']} occupied but no active lease";
            }
            if ($exp['status'] === 'vacant' && $tenant !== '') {
                $issues[] = "{$exp['label']} vacant but tenant linked: {$tenant}";
            }
        }
    }

    foreach ($expectedLeases as $expLease) {
        $unit = $dbUnits->first(fn ($u) => PassionLegacyTextNormalizer::registerUnitLabelMatch($u->label, $expLease['label']));
        if (! $unit) {
            $issues[] = "lease register {$expLease['label']} ({$expLease['tenant']}) — unit missing";
            continue;
        }
        $tenant = (string) ($unit->leases->first()?->pmTenant?->name ?? '');
        if ($tenant === '') {
            $issues[] = "lease register {$expLease['label']} ({$expLease['tenant']}) — no active lease on unit";
        }
    }

    if ($issues === []) {
        continue;
    }

    $issueCount += count($issues);
    echo str_repeat('=', 72).PHP_EOL;
    echo "{$property->name} [{$property->code}]".PHP_EOL;
    if ($registerName) {
        echo "Register: {$registerName}".PHP_EOL;
    }
    foreach ($issues as $issue) {
        echo "  - {$issue}".PHP_EOL;
    }
}

echo PHP_EOL."Total issue lines: {$issueCount}".PHP_EOL;
