<?php

use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLease;
use App\Models\PmPayment;
use App\Models\PmPropertyTakeonBalance;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = $argv[1] ?? 'C:\\Users\\Matech2\\Downloads\\property_stmt_combined_html_pm.xls';

function parseMoney(string $raw): float
{
    $raw = str_replace(["\xC2\xA0", ',', ' '], '', trim($raw));
    if ($raw === '' || $raw === '-' || strtoupper($raw) === 'N/A') {
        return 0.0;
    }

    return round((float) $raw, 2);
}

function flattenSpreadsheet(string $path): array
{
    $xml = file_get_contents($path);
    $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml) ?? $xml;
    $xml = preg_replace('/\b[a-zA-Z_][\w\-]*:/', '', $xml) ?? $xml;
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if ($doc === false) {
        throw new RuntimeException('Could not parse spreadsheet.');
    }
    $rows = [];
    foreach ($doc->Worksheet as $sheet) {
        $table = $sheet->Table ?? null;
        if ($table === null) {
            continue;
        }
        foreach ($table->Row as $row) {
            $cells = [];
            $index = 1;
            foreach ($row->Cell as $cell) {
                $attrs = $cell->attributes();
                if (isset($attrs['Index'])) {
                    $index = (int) $attrs['Index'];
                }
                $cells[$index] = trim(preg_replace('/\s+/u', ' ', (string) ($cell->Data ?? '')) ?? '');
                $index++;
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }
    }

    return $rows;
}

$rows = flattenSpreadsheet($path);
$landlord = null;
$propertyLabel = null;
$period = null;
$occupancy = [];
$currentUnit = null;
$additions = [];
$deductions = [];
$section = 'occupancy';
$summary = [];

foreach ($rows as $cells) {
    $joined = strtoupper(implode(' | ', array_values($cells)));
    $c1 = trim((string) ($cells[1] ?? ''));
    $c2 = trim((string) ($cells[2] ?? ''));
    $c3 = trim((string) ($cells[3] ?? ''));
    $c5 = trim((string) ($cells[5] ?? ''));
    $c7 = trim((string) ($cells[7] ?? ''));
    $c11 = trim((string) ($cells[11] ?? ''));
    $c14 = trim((string) ($cells[14] ?? ''));
    $c21 = trim((string) ($cells[21] ?? ''));

    if ($c1 === 'LANDLORD' && $c3 !== '') {
        $landlord = $c3;
    }
    if ($c1 === 'PROPERTY' && $c3 !== '') {
        $propertyLabel = $c3;
    }
    if (str_starts_with($c1, 'STATEMENT PERIOD') || str_contains($joined, '30/09/2025')) {
        if (preg_match('/\d{2}\/\d{2}\/\d{4}\s*-\s*\d{2}\/\d{2}\/\d{4}/', $joined, $m)) {
            $period = $m[0];
        } elseif (str_starts_with($c1, 'STATEMENT PERIOD')) {
            $period = $c1;
        }
    }
    if ($c5 === 'ADDITIONS' || $c1 === 'ADDITIONS') {
        $section = 'additions';

        continue;
    }
    if ($c5 === 'DEDUCTIONS' || $c1 === 'DEDUCTIONS' || $c11 === 'DEDUCTIONS') {
        $section = 'deductions';

        continue;
    }
    if (str_contains($joined, 'STATEMENT SUMMARY')) {
        $section = 'summary';

        continue;
    }

    if ($section === 'occupancy' && preg_match('/^HSE\s+/i', $c1) === 1) {
        $currentUnit = strtoupper($c1);
        $tenant = $c2 !== '' ? $c2 : trim((string) ($cells[2] ?? ''));
        $occupancy[] = [
            'unit' => $currentUnit,
            'tenant' => $tenant,
            'monthly' => parseMoney((string) ($cells[7] ?? '')),
            'bf_rent' => parseMoney((string) ($cells[9] ?? '')),
            'bf_garbage' => parseMoney((string) ($cells[12] ?? '')),
            'inv_rent' => parseMoney((string) ($cells[14] ?? '')),
            'inv_garbage' => parseMoney((string) ($cells[16] ?? '')),
            'rec_rent' => parseMoney((string) ($cells[20] ?? '')),
            'rec_garbage' => parseMoney((string) ($cells[22] ?? '')),
            'vacated' => null,
            'vacant' => strcasecmp($tenant, 'VACANT') === 0,
        ];

        continue;
    }
    if ($section === 'occupancy' && $currentUnit !== null && str_starts_with(strtoupper($c2), 'VACATED ON')) {
        $idx = count($occupancy) - 1;
        if ($idx >= 0) {
            $occupancy[$idx]['vacated'] = $c2;
        }

        continue;
    }

    if (($section === 'additions' || $section === 'deductions') && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $c7) === 1) {
        $bucket = $section === 'additions' ? 'additions' : 'deductions';
        ${$bucket}[] = [
            'date' => $c7,
            'memo' => $c11,
            'amount' => parseMoney($c21),
        ];

        continue;
    }

    if ($section === 'summary') {
        $label = strtoupper(trim($c14));
        if ($label !== '' && $c21 !== '') {
            $summary[$label] = $c21;
        }
    }
}

$code = null;
if (preg_match('/\[([A-Z0-9]+)\]/', (string) $propertyLabel, $m) === 1) {
    $code = strtoupper($m[1]);
}

$property = Property::query()->withoutGlobalScopes()->where('code', $code)->first();
echo "EZEN landlord: {$landlord}".PHP_EOL;
echo "EZEN property: {$propertyLabel}".PHP_EOL;
echo "Period: {$period}".PHP_EOL;
echo "ERP property: ".($property ? '#'.$property->id.' '.$property->code.' '.$property->name : 'NOT FOUND').PHP_EOL;
echo PHP_EOL;

$current = [];
foreach ($occupancy as $row) {
    if ($row['vacated'] || $row['vacant']) {
        continue;
    }
    $current[$row['unit']] = $row;
}

echo "EZEN occupancy rows: ".count($occupancy)." (current occupants ".count($current).", vacant/former ". (count($occupancy) - count($current)).")".PHP_EOL;
echo "EZEN occupied units claimed: 15, vacant: 1".PHP_EOL;

echo PHP_EOL."=== EZEN occupancy slices (period check) ===".PHP_EOL;
foreach ($occupancy as $row) {
    $months = $row['monthly'] > 0.009 ? round($row['inv_rent'] / $row['monthly'], 1) : 0;
    echo sprintf(
        "%-8s %-32s rent %6s x ~%4s mo  inv %9s rec %9s %s".PHP_EOL,
        $row['unit'],
        mb_substr((string) $row['tenant'], 0, 32),
        number_format($row['monthly'], 0),
        $months,
        number_format($row['inv_rent'], 0),
        number_format($row['rec_rent'], 0),
        $row['vacant'] ? 'VACANT' : ($row['vacated'] ?: 'current')
    );
}

if ($property === null) {
    exit(1);
}

$units = PropertyUnit::query()->withoutGlobalScopes()->where('property_id', $property->id)->orderBy('label')->get();
$leases = PmLease::query()
    ->withoutGlobalScopes()
    ->where('status', 'active')
    ->whereHas('units', fn ($q) => $q->where('property_units.property_id', $property->id))
    ->with(['pmTenant', 'units'])
    ->get();

$erpByUnit = [];
foreach ($units as $unit) {
    $lease = $leases->first(fn ($l) => $l->units->contains('id', $unit->id));
    $erpByUnit[strtoupper(trim((string) $unit->label))] = [
        'unit' => $unit,
        'lease' => $lease,
        'tenant' => $lease?->pmTenant,
    ];
}

echo PHP_EOL."=== Current occupant / rent ===".PHP_EOL;
$mismatch = 0;
$ok = 0;
$allUnits = array_unique(array_merge(array_keys($current), array_keys($erpByUnit)));
sort($allUnits, SORT_NATURAL);
foreach ($allUnits as $label) {
    $ezen = $current[$label] ?? null;
    $erp = $erpByUnit[$label] ?? null;
    $ezenName = $ezen['tenant'] ?? ($ezen && $ezen['vacant'] ? 'VACANT' : '—');
    $erpName = $erp['tenant']->name ?? (($erp['unit']->status ?? '') === 'vacant' ? 'VACANT' : '—');
    $ezenRent = $ezen['monthly'] ?? null;
    $erpRent = $erp['lease']->monthly_rent ?? $erp['unit']->rent_amount ?? null;
    $nameOk = $ezen && $erp['tenant'] && (stripos((string) $erpName, strtok((string) $ezenName, ' ')) !== false || stripos((string) $ezenName, strtok((string) $erpName, ' ')) !== false);
    if (($ezen['vacant'] ?? false) && ($erp['unit']->status ?? '') === 'vacant') {
        $nameOk = true;
    }
    $rentOk = $ezenRent !== null && $erpRent !== null && abs((float) $ezenRent - (float) $erpRent) < 1;
    $flag = ($nameOk && $rentOk) ? 'OK' : 'GAP';
    if ($flag === 'GAP') {
        $mismatch++;
    } else {
        $ok++;
    }
    echo sprintf(
        "%-8s %-4s EZEN %-28s %8s | ERP %-28s %8s %s%s",
        $label,
        $flag,
        mb_substr((string) $ezenName, 0, 28),
        $ezenRent !== null ? number_format((float) $ezenRent, 0) : '—',
        mb_substr((string) $erpName, 0, 28),
        $erpRent !== null ? number_format((float) $erpRent, 0) : '—',
        PHP_EOL,
        ''
    );
}

$unitIds = $units->pluck('id');
$from = '2025-09-30';
$to = '2026-09-30';
$invoices = PmInvoice::query()->withoutGlobalScopes()
    ->whereIn('property_unit_id', $unitIds)
    ->whereNull('deleted_at')
    ->whereNotIn('status', ['cancelled', 'draft'])
    ->whereDate('issue_date', '>=', $from)
    ->whereDate('issue_date', '<=', $to)
    ->get();

$erpInvRent = (float) $invoices->where('invoice_type', 'rent')->sum('amount');
$erpInvGarb = (float) $invoices->where('invoice_type', 'garbage')->sum('amount');
$erpPaidRent = (float) $invoices->where('invoice_type', 'rent')->sum('amount_paid');
$erpPaidGarb = (float) $invoices->where('invoice_type', 'garbage')->sum('amount_paid');
$ezenInvRent = array_sum(array_column($occupancy, 'inv_rent'));
$ezenInvGarb = array_sum(array_column($occupancy, 'inv_garbage'));
$ezenRecRent = array_sum(array_column($occupancy, 'rec_rent'));
$ezenRecGarb = array_sum(array_column($occupancy, 'rec_garbage'));

echo PHP_EOL."=== Year rollup 30/09/2025-30/09/2026 ===".PHP_EOL;
echo sprintf("Invoiced rent     EZEN %s  ERP %s  delta %s".PHP_EOL, number_format($ezenInvRent, 2), number_format($erpInvRent, 2), number_format($ezenInvRent - $erpInvRent, 2));
echo sprintf("Invoiced garbage  EZEN %s  ERP %s  delta %s".PHP_EOL, number_format($ezenInvGarb, 2), number_format($erpInvGarb, 2), number_format($ezenInvGarb - $erpInvGarb, 2));
echo sprintf("Received rent     EZEN %s  ERP paid-on-inv %s  delta %s".PHP_EOL, number_format($ezenRecRent, 2), number_format($erpPaidRent, 2), number_format($ezenRecRent - $erpPaidRent, 2));
echo sprintf("Received garbage  EZEN %s  ERP paid-on-inv %s  delta %s".PHP_EOL, number_format($ezenRecGarb, 2), number_format($erpPaidGarb, 2), number_format($ezenRecGarb - $erpPaidGarb, 2));

$invByMonth = $invoices->groupBy(fn ($i) => substr((string) $i->issue_date, 0, 7))->map->count();
echo "ERP invoice months: ".$invByMonth->keys()->sort()->implode(', ').PHP_EOL;

$addSum = array_sum(array_column($additions, 'amount'));
$dedSum = array_sum(array_column($deductions, 'amount'));
echo PHP_EOL."=== Landlord statement extras ===".PHP_EOL;
echo "Additions lines: ".count($additions)." total ".number_format($addSum, 2).PHP_EOL;
echo "Deductions lines: ".count($deductions)." total ".number_format($dedSum, 2).PHP_EOL;
foreach ($summary as $k => $v) {
    echo "  {$k}: {$v}".PHP_EOL;
}

$credits = (float) PmLandlordLedgerEntry::query()->withoutGlobalScopes()->where('property_id', $property->id)->where('direction', 'credit')->sum('amount');
$debits = (float) PmLandlordLedgerEntry::query()->withoutGlobalScopes()->where('property_id', $property->id)->where('direction', 'debit')->sum('amount');
$takeon = Schema::hasTable('pm_property_takeon_balances')
    ? PmPropertyTakeonBalance::query()->where('property_id', $property->id)->get()
    : collect();
echo PHP_EOL."=== ERP landlord ledger ===".PHP_EOL;
echo "Credits: ".number_format($credits, 2)."  Debits: ".number_format($debits, 2)."  Net credit: ".number_format($credits - $debits, 2).PHP_EOL;
echo "Take-on rows: ".$takeon->count().PHP_EOL;
foreach ($takeon as $row) {
    echo "  take-on ".$row->balance_date." ".$row->balance.PHP_EOL;
}

echo PHP_EOL."=== Sample additions ===".PHP_EOL;
foreach (array_slice($additions, 0, 8) as $row) {
    echo "  {$row['date']} {$row['memo']} ".number_format($row['amount'], 2).PHP_EOL;
}
echo PHP_EOL."=== Sample deductions ===".PHP_EOL;
foreach (array_slice($deductions, 0, 8) as $row) {
    echo "  {$row['date']} {$row['memo']} ".number_format($row['amount'], 2).PHP_EOL;
}

echo PHP_EOL."Occupant matches: {$ok}  gaps: {$mismatch}".PHP_EOL;
