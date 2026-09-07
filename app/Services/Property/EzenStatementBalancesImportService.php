<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EzenStatementBalancesImportService
{
    public function __construct(
        private readonly PassionPropertyCodeResolver $codeResolver,
        private readonly CarryForwardConsolidationService $carryForward,
    ) {}

    /**
     * @return array{
     *     parsed:int,
     *     leases_updated:int,
     *     skipped:int,
     *     invoices_created:int,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importTenantBalancesFromPath(
        string $path,
        int $agentUserId,
        bool $dryRun = false,
        bool $syncInvoices = false,
    ): array {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open file: '.$path);
        }

        $header = fgetcsv($handle);
        if (! is_array($header) || $header === []) {
            fclose($handle);
            throw new RuntimeException('CSV has no header row.');
        }

        $map = $this->headerMap($header);
        foreach (['property_code', 'unit_label'] as $required) {
            if (! isset($map[$required])) {
                fclose($handle);
                throw new RuntimeException('CSV missing required column: '.$required);
            }
        }

        $summary = [
            'parsed' => 0,
            'leases_updated' => 0,
            'skipped' => 0,
            'invoices_created' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        $rowNum = 1;
        while (($cols = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->isEmptyRow($cols)) {
                continue;
            }

            $row = $this->rowFromMap($map, $cols);
            $summary['parsed']++;

            try {
                $result = $this->applyTenantRow($row, $agentUserId, $dryRun, $syncInvoices, $rowNum);
                $summary['leases_updated'] += $result['updated'] ? 1 : 0;
                $summary['skipped'] += $result['updated'] ? 0 : 1;
                $summary['invoices_created'] += $result['invoices_created'];
                $summary['warnings'] = array_merge($summary['warnings'], $result['warnings']);
            } catch (RuntimeException $e) {
                $summary['errors'][] = 'Row '.$rowNum.': '.$e->getMessage();
            }
        }

        fclose($handle);

        return $summary;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{updated:bool, invoices_created:int, warnings:list<string>}
     */
    private function applyTenantRow(array $row, int $agentUserId, bool $dryRun, bool $syncInvoices, int $rowNum): array
    {
        $warnings = [];
        $propertyCode = strtoupper(trim((string) ($row['property_code'] ?? '')));
        $unitLabel = trim((string) ($row['unit_label'] ?? ''));
        if ($propertyCode === '' || $unitLabel === '') {
            throw new RuntimeException('property_code and unit_label are required.');
        }

        $property = $this->resolveProperty($propertyCode, $agentUserId);
        if ($property === null) {
            throw new RuntimeException('property '.$propertyCode.' not found for this agent.');
        }

        $unit = $this->resolveUnit($property, $unitLabel);
        if ($unit === null) {
            throw new RuntimeException('unit '.$unitLabel.' not found on '.$propertyCode.'.');
        }

        $lease = $this->resolveLease($unit);
        if ($lease === null) {
            throw new RuntimeException('no lease on unit '.$unitLabel.' ('.$propertyCode.').');
        }

        $asOf = trim((string) ($row['as_of'] ?? '')) ?: now()->toDateString();
        $rent = $this->money($row['rent_bf'] ?? 0);
        $garbage = $this->money($row['garbage_bf'] ?? 0);
        $water = $this->money($row['water_bf'] ?? 0);
        $lines = $this->positiveLines($rent, $garbage, $water, $asOf);
        $arrearsTotal = round(array_sum(array_map(static fn (array $line) => (float) $line['amount'], $lines)), 2);
        $creditTotal = round(max(0.0, -1 * min(0.0, $rent)) + max(0.0, -1 * min(0.0, $garbage)) + max(0.0, -1 * min(0.0, $water)), 2);

        if ($creditTotal > 0.009) {
            $warnings[] = 'Row '.$rowNum.' '.$unitLabel.': EZEN credit B/F KES '.number_format($creditTotal, 2)
                .' (not posted as wallet credit — arrears lines only).';
        }

        $tenantName = trim((string) ($row['tenant_name'] ?? ''));
        $leaseTenant = $lease->pmTenant;
        if ($tenantName !== '' && $leaseTenant && ! $this->namesLooselyMatch($tenantName, (string) $leaseTenant->name)) {
            $warnings[] = 'Row '.$rowNum.' '.$unitLabel.': statement tenant "'.$tenantName
                .'" vs system "'.$leaseTenant->name.'" — applied to the lease on this unit.';
        }

        if ($dryRun) {
            return ['updated' => $lines !== [] || $creditTotal > 0.009, 'invoices_created' => 0, 'warnings' => $warnings];
        }

        $note = trim((string) ($row['notes'] ?? ''));
        $leasePayload = [
            'opening_arrears' => $lines,
            'opening_arrears_manual_total' => $arrearsTotal,
            'opening_arrears_as_of_date' => $asOf,
            'opening_arrears_note' => $note !== ''
                ? $note
                : 'Imported from EZEN property account statement B/F '.$asOf,
        ];
        $lease->fill(array_filter(
            $leasePayload,
            static fn ($value, $key) => Schema::hasColumn('pm_leases', $key),
            ARRAY_FILTER_USE_BOTH
        ));
        $lease->save();

        $tenant = PmTenant::query()->withoutGlobalScopes()->find($lease->pm_tenant_id);
        if ($tenant) {
            $tenantPayload = [
                'opening_arrears_amount' => $arrearsTotal,
                'opening_arrears_as_of' => $asOf,
                'opening_arrears_status' => $arrearsTotal > 0.009 ? 'pending' : 'none',
            ];
            $tenant->fill(array_filter(
                $tenantPayload,
                static fn ($value, $key) => Schema::hasColumn('pm_tenants', $key),
                ARRAY_FILTER_USE_BOTH
            ));
            $tenant->save();
        }

        $invoicesCreated = 0;
        if ($syncInvoices && $lines !== []) {
            $sync = $this->carryForward->syncLease($lease->fresh());
            $invoicesCreated = (int) ($sync['invoices_created'] ?? 0);
        }

        return ['updated' => true, 'invoices_created' => $invoicesCreated, 'warnings' => $warnings];
    }

    private function resolveProperty(string $code, int $agentUserId): ?Property
    {
        $matches = $this->codeResolver->resolveMany($code);
        if ($matches->isEmpty()) {
            return null;
        }

        $scoped = $matches->first(function (Property $property) use ($agentUserId): bool {
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return true;
            }

            return (int) $property->agent_user_id === $agentUserId;
        });

        return $scoped ?? $matches->first();
    }

    private function resolveUnit(Property $property, string $label): ?PropertyUnit
    {
        $wanted = $this->normalizeLabel($label);
        $units = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->get(['id', 'label']);

        foreach ($units as $unit) {
            if ($this->normalizeLabel((string) $unit->label) === $wanted) {
                return $unit;
            }
        }

        return null;
    }

    private function resolveLease(PropertyUnit $unit): ?PmLease
    {
        $query = PmLease::query()
            ->withoutGlobalScopes()
            ->whereHas('units', fn ($q) => $q->where('property_units.id', $unit->id))
            ->with('pmTenant:id,name')
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('id');

        return $query->first();
    }

    /**
     * @return list<array{charge_type:string,specific_charge:string,period:?string,amount:string}>
     */
    private function positiveLines(float $rent, float $garbage, float $water, string $asOf): array
    {
        $period = substr($asOf, 0, 7);
        $lines = [];
        foreach ([
            ['rent', 'Rent B/F', $rent],
            ['garbage', 'Garbage B/F', $garbage],
            ['water', 'Water B/F', $water],
        ] as [$type, $label, $amount]) {
            if ($amount <= 0.009) {
                continue;
            }
            $lines[] = [
                'charge_type' => $type,
                'specific_charge' => $label,
                'period' => $period,
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }

        return $lines;
    }

    private function normalizeLabel(string $label): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', trim($label)));
    }

    private function namesLooselyMatch(string $a, string $b): bool
    {
        $na = preg_replace('/[^A-Z]/', '', strtoupper($a)) ?? '';
        $nb = preg_replace('/[^A-Z]/', '', strtoupper($b)) ?? '';
        if ($na === '' || $nb === '') {
            return true;
        }

        return str_contains($na, $nb) || str_contains($nb, $na) || similar_text($na, $nb) / max(strlen($na), strlen($nb)) >= 0.55;
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $raw = str_replace([',', ' '], '', (string) $value);

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function headerMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $key = strtolower(trim((string) $name));
            $key = str_replace([' ', '-'], '_', $key);
            $map[$key] = (int) $index;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string|null>  $cols
     * @return array<string, string>
     */
    private function rowFromMap(array $map, array $cols): array
    {
        $row = [];
        foreach ($map as $key => $index) {
            $row[$key] = trim((string) ($cols[$index] ?? ''));
        }

        return $row;
    }

    /**
     * @param  list<string|null>  $cols
     */
    private function isEmptyRow(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }
}
