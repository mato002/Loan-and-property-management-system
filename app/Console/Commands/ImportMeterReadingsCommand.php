<?php

namespace App\Console\Commands;

use App\Models\PmTenant;
use App\Models\PmUnitUtilityCharge;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\WaterBillingService;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ImportMeterReadingsCommand extends Command
{
    protected $signature = 'property:import-meter-readings
        {path? : SpreadsheetML meter reading report (.xls)}
        {--month= : Only import YYYY-MM}
        {--agent-user-id= : Only match this agent\'s properties and tenants}
        {--dry-run : Parse and match without saving}';

    protected $description = 'Import EZEN water and electricity meter readings onto units.';

    /** @var array<string, int> */
    private array $months = [
        'JANUARY' => 1,
        'FEBRUARY' => 2,
        'MARCH' => 3,
        'APRIL' => 4,
        'MAY' => 5,
        'JUNE' => 6,
        'JULY' => 7,
        'AUGUST' => 8,
        'SEPTEMBER' => 9,
        'OCTOBER' => 10,
        'NOVEMBER' => 11,
        'DECEMBER' => 12,
    ];

    public function handle(WaterBillingService $billing): int
    {
        $path = (string) ($this->argument('path') ?: storage_path('passion-legacy/meter-readings/meterreadings.xls'));
        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }

        $onlyMonth = trim((string) $this->option('month'));
        if ($onlyMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $onlyMonth) !== 1) {
            $this->error('Invalid --month. Use YYYY-MM.');

            return self::FAILURE;
        }

        $agentUserId = (int) $this->option('agent-user-id');
        $dryRun = (bool) $this->option('dry-run');
        $rows = $this->parseRows($path);
        $properties = Property::query()->withoutGlobalScopes()
            ->when($agentUserId > 0, fn ($q) => $q->where('agent_user_id', $agentUserId))
            ->get(['id', 'name', 'agent_user_id']);

        $stats = [
            'parsed' => 0,
            'water_saved' => 0,
            'electricity_saved' => 0,
            'skipped_duplicate' => 0,
            'skipped_month' => 0,
            'unmatched' => 0,
        ];
        $unmatched = [];

        $property = null;
        $kind = null;
        $billingMonth = null;

        foreach ($rows as $row) {
            if ($row['kind'] === 'property') {
                $property = $this->matchProperty($row['text'], $properties);
                $kind = null;
                $billingMonth = null;
                if ($property === null) {
                    $unmatched[] = 'Property not found: '.$row['text'];
                }

                continue;
            }

            if ($row['kind'] === 'section') {
                $kind = $row['meter'];
                $billingMonth = $row['month'];

                continue;
            }

            if ($property === null || $kind === null || $billingMonth === null) {
                $stats['unmatched']++;
                $unmatched[] = 'No property/section for '.$row['account'].' '.$row['unit'];

                continue;
            }

            if ($onlyMonth !== '' && $billingMonth !== $onlyMonth) {
                $stats['skipped_month']++;

                continue;
            }

            $stats['parsed']++;
            $unit = $this->matchUnit($property, (string) $row['unit'], (string) $row['account'], $agentUserId);
            if ($unit === null) {
                $stats['unmatched']++;
                $unmatched[] = $property->name.' '.$billingMonth.' '.$row['account'].' '.$row['unit'].' '.$row['tenant'];

                continue;
            }

            $previous = (float) $row['previous'];
            $current = (float) $row['current'];
            $sheetUnits = (float) $row['units'];
            $rate = (float) $row['rate'];
            if ($current < $previous && $sheetUnits > 0 && $current + 0.0005 >= $sheetUnits) {
                $previous = round($current - $sheetUnits, 3);
            }
            $reset = $current + 0.0005 < $previous;

            if ($dryRun) {
                if ($kind === 'electricity') {
                    $stats['electricity_saved']++;
                } else {
                    $stats['water_saved']++;
                }

                continue;
            }

            try {
                if ($kind === 'electricity') {
                    $saved = $this->saveElectricity($unit, $billingMonth, $previous, $current, $sheetUnits, $rate, (float) $row['total']);
                    if ($saved) {
                        $stats['electricity_saved']++;
                    } else {
                        $stats['skipped_duplicate']++;
                    }
                } else {
                    $billing->recordReading([
                        'property_unit_id' => $unit->id,
                        'billing_month' => $billingMonth,
                        'previous_reading' => $previous,
                        'current_reading' => $current,
                        'rate_per_unit' => $rate,
                        'fixed_charge' => 0,
                        'is_meter_reset' => $reset,
                        'notes' => 'Imported from meter reading report',
                    ], null, true);
                    $stats['water_saved']++;
                }
            } catch (ValidationException $e) {
                $message = (string) (collect($e->errors())->flatten()->first() ?: '');
                if (str_contains(strtolower($message), 'already exists')) {
                    $stats['skipped_duplicate']++;
                } else {
                    $stats['unmatched']++;
                    $unmatched[] = $row['account'].' '.$row['unit'].': '.$message;
                }
            }
        }

        $this->info(($dryRun ? 'Dry run. ' : '').'Parsed '.$stats['parsed'].' reading(s).');
        $this->line('Water saved: '.$stats['water_saved']);
        $this->line('Electricity saved: '.$stats['electricity_saved']);
        $this->line('Already on file: '.$stats['skipped_duplicate']);
        $this->line('Outside --month: '.$stats['skipped_month']);
        $this->line('Unmatched: '.$stats['unmatched']);
        foreach (array_slice(array_unique($unmatched), 0, 40) as $line) {
            $this->warn($line);
        }
        if (count(array_unique($unmatched)) > 40) {
            $this->warn('… and '.(count(array_unique($unmatched)) - 40).' more.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseRows(string $path): array
    {
        $dom = new DOMDocument;
        $dom->load($path);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');

        $parsed = [];
        foreach ($xpath->query('//ss:Row') ?: [] as $row) {
            $cells = [];
            foreach ($xpath->query('ss:Cell', $row) ?: [] as $cell) {
                $indexAttr = $cell->attributes?->getNamedItemNS('urn:schemas-microsoft-com:office:spreadsheet', 'Index');
                $index = (int) ($indexAttr?->nodeValue ?: (count($cells) + 1));
                $data = $xpath->query('ss:Data', $cell)?->item(0);
                $cells[$index] = trim((string) ($data?->textContent ?? ''));
            }
            ksort($cells);
            $values = array_values($cells);
            $text = trim(implode(' ', array_filter($values, fn ($v) => $v !== '')));
            if ($text === '' || in_array($text, ['METER READING', 'BY PERIOD'], true) || str_starts_with($values[0] ?? '', 'A/C')) {
                continue;
            }

            $account = strtoupper(trim((string) ($values[0] ?? '')));
            if (str_starts_with($account, 'TNT')) {
                $parsed[] = [
                    'kind' => 'reading',
                    'account' => $account,
                    'unit' => trim((string) ($values[1] ?? '')),
                    'tenant' => trim((string) ($values[2] ?? '')),
                    'previous' => (float) ($values[5] ?? 0),
                    'current' => (float) ($values[6] ?? 0),
                    'units' => (float) ($values[7] ?? 0),
                    'rate' => (float) ($values[8] ?? 0),
                    'total' => (float) ($values[10] ?? 0),
                ];

                continue;
            }

            if (preg_match('/^(WATER|ELECTRICITY)\s*\[([A-Z]+)\/(\d{4})\]$/i', $text, $m) === 1) {
                $monthName = strtoupper($m[2]);
                $monthNum = $this->months[$monthName] ?? 0;
                if ($monthNum > 0) {
                    $parsed[] = [
                        'kind' => 'section',
                        'meter' => strtolower($m[1]) === 'electricity' ? 'electricity' : 'water',
                        'month' => $m[3].'-'.str_pad((string) $monthNum, 2, '0', STR_PAD_LEFT),
                    ];
                }

                continue;
            }

            if (! preg_match('/[A-Z]/i', $text)) {
                continue;
            }

            if (! preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $text) && ! str_contains($text, ',')) {
                $parsed[] = ['kind' => 'property', 'text' => $text];
            }
        }

        return $parsed;
    }

    /**
     * @param  Collection<int, Property>  $properties
     */
    private function matchProperty(string $header, Collection $properties): ?Property
    {
        $needle = '';
        if (preg_match('/\(([^)]+)\)/', $header, $m) === 1) {
            $needle = $this->squash($m[1]);
        } elseif (str_contains($header, ' - ')) {
            $needle = $this->squash(trim((string) substr((string) strrchr($header, '-'), 1)));
        } else {
            $needle = $this->squash($header);
        }
        if ($needle === '') {
            return null;
        }

        $headerSquash = $this->squash($header);
        $hits = $properties->filter(function (Property $property) use ($needle): bool {
            return str_contains($this->squash((string) $property->name), $needle);
        })->values();

        if ($hits->count() > 1) {
            $hits = $hits->filter(function (Property $property) use ($headerSquash, $needle): bool {
                $name = $this->squash((string) $property->name);
                $extra = trim(str_replace($needle, '', $headerSquash));
                foreach (preg_split('/\s+/', $extra) ?: [] as $token) {
                    if (strlen($token) >= 5 && str_contains($name, $token)) {
                        return true;
                    }
                }

                return str_contains($headerSquash, $name) || str_contains($name, $headerSquash);
            })->values();
        }

        if ($hits->isEmpty()) {
            return null;
        }

        return $hits->sortBy(fn (Property $property) => strlen($this->squash((string) $property->name)))->first();
    }

    private function squash(string $value): string
    {
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function matchUnit(Property $property, string $unitNo, string $account, int $agentUserId): ?PropertyUnit
    {
        $units = PropertyUnit::query()->withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->get(['id', 'property_id', 'label']);

        $wanted = $this->normalizeUnit($unitNo);
        $byLabel = $units->first(fn (PropertyUnit $unit) => $this->normalizeUnit((string) $unit->label) === $wanted);
        if ($byLabel) {
            return $byLabel;
        }

        if ($wanted === 'SHOP2') {
            $combinedShop = $units->first(fn (PropertyUnit $unit) => $this->normalizeUnit((string) $unit->label) === 'SHOP12');
            if ($combinedShop) {
                return $combinedShop;
            }
        }

        if ($account === '') {
            return null;
        }

        $tenant = PmTenant::query()->withoutGlobalScopes()
            ->when($agentUserId > 0, fn ($q) => $q->where('agent_user_id', $agentUserId))
            ->where('account_number', $account)
            ->first();
        if (! $tenant) {
            return null;
        }

        $leaseUnits = PropertyUnit::query()->withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->whereIn('id', function ($q) use ($tenant) {
                $q->select('lu.property_unit_id')
                    ->from('pm_lease_unit as lu')
                    ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
                    ->where('l.pm_tenant_id', $tenant->id);
            })
            ->get(['id', 'property_id', 'label']);

        if ($leaseUnits->count() === 1) {
            return $leaseUnits->first();
        }

        return $leaseUnits->first(fn (PropertyUnit $unit) => $this->normalizeUnit((string) $unit->label) === $wanted);
    }

    private function normalizeUnit(string $label): string
    {
        $value = strtoupper(trim($label));
        $value = preg_replace('/\([^)]*\)/', '', $value) ?? $value;
        $value = preg_replace('/[^A-Z0-9]/', '', $value) ?? $value;
        $value = preg_replace('/^(HOUSE|HSE|UNIT|FLAT|APT)/', '', $value) ?? $value;

        return $value;
    }

    private function saveElectricity(PropertyUnit $unit, string $month, float $previous, float $current, float $units, float $rate, float $total): bool
    {
        $exists = PmUnitUtilityCharge::query()->withoutGlobalScopes()
            ->where('property_unit_id', $unit->id)
            ->where('billing_month', $month)
            ->where('charge_type', 'electricity')
            ->exists();
        if ($exists) {
            return false;
        }

        $amount = $total > 0.009 ? round($total, 2) : round($units * $rate, 2);
        PmUnitUtilityCharge::query()->create([
            'property_unit_id' => $unit->id,
            'charge_type' => 'electricity',
            'billing_month' => $month,
            'label' => 'Electricity',
            'units_consumed' => $units,
            'rate_per_unit' => $rate,
            'fixed_charge' => 0,
            'amount' => $amount,
            'notes' => 'Imported meter reading '.$previous.' → '.$current,
            'is_invoiced' => false,
            'pm_invoice_id' => null,
        ]);

        return true;
    }
}
