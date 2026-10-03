<?php

namespace App\Services\Property;

use App\Models\PmEzenLandlordInvoice;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EzenLandlordInvoicesImportService
{
    /** @var array<string, string> EZEN code variants → Passion property codes */
    private const CODE_ALIASES = [
        'F00037A' => 'E00037A',
        'F00043A' => 'E00043A',
        'D00037A' => 'E00037A',
        'A20039A' => 'A00039A',
    ];

    public function __construct(
        private readonly PassionPropertyCodeResolver $codes,
        private readonly PropertyTrustAccountingService $trust,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     parsed:int,
     *     created:int,
     *     updated:int,
     *     unchanged:int,
     *     unmatched_property:int,
     *     fees_posted:int,
     *     fees_skipped:int,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importRows(
        array $rows,
        int $agentUserId,
        bool $dryRun = false,
        bool $postFees = false,
        ?int $actorId = null,
    ): array {
        $summary = [
            'parsed' => count($rows),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'unmatched_property' => 0,
            'fees_posted' => 0,
            'fees_skipped' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        $run = function () use ($rows, $agentUserId, $dryRun, $postFees, $actorId, &$summary): void {
            foreach ($rows as $index => $raw) {
                $rowNum = $index + 1;
                try {
                    $normalized = $this->normalizeRow($raw);
                    if ($normalized === null) {
                        $summary['warnings'][] = 'Row '.$rowNum.': skipped (missing invoice number or amount).';

                        continue;
                    }

                    $property = $this->resolveProperty($normalized['property_code'], $normalized['property_name'], $agentUserId);
                    if ($property === null) {
                        $summary['unmatched_property']++;
                        $summary['warnings'][] = 'Row '.$rowNum.' '.$normalized['ezen_invoice_no']
                            .': no property for '
                            .($normalized['property_code'] ?: $normalized['property_name'] ?: 'unknown');
                    } else {
                        $normalized['property_id'] = (int) $property->id;
                        $normalized['property_code'] = (string) $property->code;
                        if ($normalized['property_name'] === '') {
                            $normalized['property_name'] = (string) $property->name;
                        }
                        $normalized['landlord_user_id'] = $this->primaryLandlordId((int) $property->id);
                    }

                    if ($dryRun) {
                        $summary['created']++;
                        if ($postFees && $property !== null && $normalized['period_month'] && $normalized['landlord_user_id']) {
                            $summary['fees_posted']++;
                        }

                        continue;
                    }

                    $result = $this->upsertInvoice($agentUserId, $normalized);
                    $summary[$result]++;

                    if (
                        $postFees
                        && $property !== null
                        && filled($normalized['period_month'])
                        && (int) ($normalized['landlord_user_id'] ?? 0) > 0
                        && (float) $normalized['total_amount'] > 0.009
                    ) {
                        $posted = $this->maybePostFee(
                            $property,
                            (int) $normalized['landlord_user_id'],
                            (string) $normalized['period_month'],
                            (float) $normalized['total_amount'],
                            $agentUserId,
                            $actorId,
                        );
                        if ($posted) {
                            $summary['fees_posted']++;
                        } else {
                            $summary['fees_skipped']++;
                        }
                    }
                } catch (\Throwable $e) {
                    $summary['errors'][] = 'Row '.$rowNum.': '.$e->getMessage();
                }
            }
        };

        if ($dryRun) {
            $run();
        } else {
            if (! Schema::hasTable('pm_ezen_landlord_invoices')) {
                throw new RuntimeException('Run migrations first (pm_ezen_landlord_invoices is missing).');
            }
            DB::transaction($run);
        }

        return $summary;
    }

    /**
     * @return array{
     *     parsed:int,
     *     created:int,
     *     updated:int,
     *     unchanged:int,
     *     unmatched_property:int,
     *     fees_posted:int,
     *     fees_skipped:int,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromJsonPath(
        string $path,
        int $agentUserId,
        bool $dryRun = false,
        bool $postFees = false,
        ?int $actorId = null,
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException('File not found: '.$path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('JSON must be an array of landlord invoice rows.');
        }

        $rows = array_is_list($decoded) ? $decoded : ($decoded['rows'] ?? []);
        if (! is_array($rows)) {
            throw new RuntimeException('JSON rows must be a list.');
        }

        return $this->importRows($rows, $agentUserId, $dryRun, $postFees, $actorId);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $raw): ?array
    {
        $invoiceNo = strtoupper(trim((string) ($raw['ezen_invoice_no'] ?? $raw['invoice_no'] ?? '')));
        $amount = round((float) ($raw['total_amount'] ?? $raw['amount'] ?? 0), 2);
        if ($invoiceNo === '' || $amount <= 0) {
            return null;
        }

        $propertyCode = $this->aliasCode(strtoupper(trim((string) ($raw['property_code'] ?? ''))));
        $propertyName = trim((string) ($raw['property_name'] ?? ''));
        $particulars = trim((string) ($raw['particulars'] ?? ''));
        if ($propertyCode === '' && $particulars !== '') {
            if (preg_match('/\[([A-Z]\d{5}[A-Z]?)\]/i', $particulars, $m)) {
                $propertyCode = $this->aliasCode(strtoupper($m[1]));
            }
        }
        if ($propertyCode === '' && isset($raw['property'])) {
            $propertyField = trim((string) $raw['property']);
            if (preg_match('/^([A-Z]\d{5}[A-Z]?)\s*[~\-]/s*(.+)$/i', $propertyField, $m)) {
                $propertyCode = $this->aliasCode(strtoupper($m[1]));
                $propertyName = $propertyName !== '' ? $propertyName : trim($m[2]);
            }
        }

        $periodLabel = trim((string) ($raw['period_label'] ?? ''));
        if ($periodLabel === '' && $particulars !== '') {
            if (preg_match('/\b([A-Z]{3,9})\s*\/\s*(20\d{2})\b/i', $particulars, $m)) {
                $periodLabel = strtoupper($m[1]).'/'.$m[2];
            }
        }

        $invoiceDate = $this->parseDate($raw['invoice_date'] ?? $raw['date'] ?? null);
        $dueDate = $this->parseDate($raw['due_date'] ?? null) ?: $invoiceDate;
        $periodMonth = $this->periodMonth($periodLabel, $invoiceDate);
        $paid = round((float) ($raw['total_paid'] ?? 0), 2);
        $due = array_key_exists('amount_due', $raw)
            ? round((float) $raw['amount_due'], 2)
            : round(max(0, $amount - $paid), 2);

        $sourceKey = strtoupper(implode('|', [
            $invoiceNo,
            $propertyCode !== '' ? $propertyCode : 'NOPROP',
            $periodMonth ?: ($invoiceDate ?: 'nodate'),
        ]));

        return [
            'source_key' => $sourceKey,
            'ezen_invoice_no' => $invoiceNo,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'property_code' => $propertyCode,
            'property_name' => $propertyName,
            'period_label' => $periodLabel !== '' ? $periodLabel : null,
            'period_month' => $periodMonth,
            'particulars' => $particulars !== ''
                ? $particulars
                : trim('Management fees for '.($propertyName !== '' ? $propertyName.' ' : '').($propertyCode !== '' ? '['.$propertyCode.'] ' : '').$periodLabel),
            'total_amount' => $amount,
            'total_paid' => $paid,
            'amount_due' => $due,
            'listing_status' => strtolower(trim((string) ($raw['listing_status'] ?? 'open'))) ?: 'open',
            'property_id' => null,
            'landlord_user_id' => null,
            'link_status' => 'imported',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertInvoice(int $agentUserId, array $row): string
    {
        $payload = [
            'agent_user_id' => $agentUserId,
            'source_key' => (string) $row['source_key'],
            'ezen_invoice_no' => (string) $row['ezen_invoice_no'],
            'invoice_date' => $row['invoice_date'],
            'due_date' => $row['due_date'],
            'property_code' => $row['property_code'] !== '' ? $row['property_code'] : null,
            'property_name' => $row['property_name'] !== '' ? $row['property_name'] : null,
            'period_label' => $row['period_label'],
            'period_month' => $row['period_month'],
            'particulars' => $row['particulars'],
            'total_amount' => $row['total_amount'],
            'total_paid' => $row['total_paid'],
            'amount_due' => $row['amount_due'],
            'listing_status' => $row['listing_status'],
            'property_id' => $row['property_id'],
            'landlord_user_id' => $row['landlord_user_id'],
            'link_status' => $row['property_id'] ? 'linked' : 'unmatched_property',
        ];

        $existing = PmEzenLandlordInvoice::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->where('source_key', $payload['source_key'])
            ->first();

        if (! $existing) {
            PmEzenLandlordInvoice::query()->create($payload);

            return 'created';
        }

        $changed = false;
        foreach ($payload as $key => $value) {
            if ($key === 'agent_user_id' || $key === 'source_key') {
                continue;
            }
            $current = $existing->{$key};
            if ($current instanceof \DateTimeInterface) {
                $current = $current->format('Y-m-d');
            }
            if ((string) $current !== (string) ($value ?? '')) {
                $changed = true;
                break;
            }
        }

        if (! $changed) {
            return 'unchanged';
        }

        $existing->fill($payload);
        $existing->save();

        return 'updated';
    }

    private function resolveProperty(string $code, string $name, int $agentUserId): ?Property
    {
        if ($code !== '') {
            $exact = Property::query()
                ->withoutGlobalScopes()
                ->where('agent_user_id', $agentUserId)
                ->where('code', $code)
                ->first();
            if ($exact) {
                return $exact;
            }

            $resolved = $this->codes->resolveOne($code);
            if ($resolved && (int) $resolved->agent_user_id === $agentUserId) {
                return $resolved;
            }
        }

        if ($name !== '') {
            $byName = $this->codes->resolveByName($name);
            if ($byName && (int) $byName->agent_user_id === $agentUserId) {
                return $byName;
            }
        }

        return null;
    }

    private function primaryLandlordId(int $propertyId): ?int
    {
        $link = DB::table('property_landlord')
            ->where('property_id', $propertyId)
            ->orderByDesc('ownership_percent')
            ->orderBy('user_id')
            ->first();

        return $link ? (int) $link->user_id : null;
    }

    private function maybePostFee(
        Property $property,
        int $landlordId,
        string $periodMonth,
        float $feeAmount,
        int $agentUserId,
        ?int $actorId,
    ): bool {
        if ($this->trust->periodManagementFeePosted((int) $property->id, $landlordId, $periodMonth)) {
            return false;
        }

        $this->trust->postPeriodManagementFee(
            (int) $property->id,
            $landlordId,
            $periodMonth,
            $feeAmount,
            $agentUserId,
            $actorId,
            (string) $property->name,
        );

        return true;
    }

    private function aliasCode(string $code): string
    {
        return self::CODE_ALIASES[$code] ?? $code;
    }

    private function parseDate(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function periodMonth(?string $periodLabel, ?string $invoiceDate): ?string
    {
        $label = strtoupper(trim((string) $periodLabel));
        if (preg_match('/^([A-Z]{3,9})\s*\/\s*(20\d{2})$/', $label, $m)) {
            $monthMap = [
                'JAN' => 1, 'JANUARY' => 1,
                'FEB' => 2, 'FEBRUARY' => 2,
                'MAR' => 3, 'MARCH' => 3,
                'APR' => 4, 'APRIL' => 4,
                'MAY' => 5,
                'JUN' => 6, 'JUNE' => 6,
                'JUL' => 7, 'JULY' => 7,
                'AUG' => 8, 'AUGUST' => 8,
                'SEP' => 9, 'SEPT' => 9, 'SEPTEMBER' => 9,
                'OCT' => 10, 'OCTOBER' => 10,
                'NOV' => 11, 'NOVEMBER' => 11,
                'DEC' => 12, 'DECEMBER' => 12,
            ];
            $month = $monthMap[$m[1]] ?? null;
            if ($month !== null) {
                return sprintf('%04d-%02d', (int) $m[2], $month);
            }
        }

        if ($invoiceDate) {
            return substr($invoiceDate, 0, 7);
        }

        return null;
    }
}
