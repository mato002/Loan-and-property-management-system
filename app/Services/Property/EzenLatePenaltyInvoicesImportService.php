<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Import penalty-invoice register CSVs without fabricating receipt records. */
final class EzenLatePenaltyInvoicesImportService
{
    private const REQUIRED_HEADERS = [
        'property_code',
        'unit_no',
        'tenant_account',
        'tenant_name',
        'period',
        'source_invoice_no',
        'issue_date',
        'invoice_amount',
        'source_paid_amount',
    ];

    /**
     * @return array{parsed:int, imported:int, skipped_existing:int, skipped_unmatched:int, source_paid_rows:int, source_paid_total:float, warnings:list<string>, errors:list<string>}
     */
    public function importFromPath(
        string $path,
        int $agentUserId,
        ?User $actor = null,
        bool $dryRun = false,
        bool $postGl = false,
        ?string $propertyCodeFilter = null,
        ?int $limit = null,
    ): array {
        $summary = [
            'parsed' => 0,
            'imported' => 0,
            'skipped_existing' => 0,
            'skipped_unmatched' => 0,
            'source_paid_rows' => 0,
            'source_paid_total' => 0.0,
            'warnings' => [],
            'errors' => [],
        ];

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open late penalty CSV.');
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '');
            if (! is_array($headers)) {
                throw new RuntimeException('The late penalty CSV is empty.');
            }
            $headers = array_map(static fn ($header): string => strtolower(trim((string) $header)), $headers);
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
            }
            $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
            if ($missing !== []) {
                throw new RuntimeException('Missing CSV columns: '.implode(', ', $missing).'.');
            }

            $propertyFilter = strtoupper(trim((string) $propertyCodeFilter));
            $rowNumber = 1;
            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;
                if ($values === [null] || $values === []) {
                    continue;
                }
                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = trim((string) ($values[$index] ?? ''));
                }
                if (implode('', $row) === '') {
                    continue;
                }
                $summary['parsed']++;
                if ($limit !== null && $limit > 0 && $summary['parsed'] > $limit) {
                    $summary['parsed']--;
                    break;
                }
                if ($propertyFilter !== '' && strtoupper($row['property_code']) !== $propertyFilter) {
                    continue;
                }

                try {
                    $this->validateRow($row);
                    $resolved = $this->resolveRow($row, $agentUserId);
                    if ($resolved === null) {
                        $summary['skipped_unmatched']++;
                        $summary['warnings'][] = 'Row '.$rowNumber.' '.$row['source_invoice_no'].': property, unit, tenant account, or lease did not match; skipped.';
                        continue;
                    }

                    $existing = $this->findExistingInvoice($row['source_invoice_no']);
                    if ($existing !== null) {
                        $summary['skipped_existing']++;
                        continue;
                    }

                    $sourcePaid = $this->money($row['source_paid_amount']);
                    if ($sourcePaid > 0.009) {
                        $summary['source_paid_rows']++;
                        $summary['source_paid_total'] = round($summary['source_paid_total'] + $sourcePaid, 2);
                    }

                    if (! $dryRun) {
                        $this->createInvoice($row, $resolved, $actor, $postGl);
                    }
                    $summary['imported']++;
                } catch (\Throwable $exception) {
                    $summary['errors'][] = 'Row '.$rowNumber.' '.$row['source_invoice_no'].': '.$exception->getMessage();
                }
            }
        } finally {
            fclose($handle);
        }

        if ($summary['source_paid_rows'] > 0) {
            $summary['warnings'][] = 'The source Paid column totals KES '.number_format($summary['source_paid_total'], 2)
                .' across '.$summary['source_paid_rows'].' rows. These values are stored for reference only; no payments were created.';
        }

        return $summary;
    }

    /** @param array<string, string> $row */
    private function validateRow(array $row): void
    {
        if (! preg_match('/^[A-Z0-9][A-Z0-9-]{2,39}$/i', $row['property_code'])) {
            throw new RuntimeException('Invalid property code.');
        }
        if ($row['unit_no'] === '' || $row['tenant_account'] === '' || $row['tenant_name'] === '') {
            throw new RuntimeException('Unit and tenant identity fields are required.');
        }
        if (! preg_match('/^[A-Z0-9][A-Z0-9-]{1,59}$/i', $row['source_invoice_no'])) {
            throw new RuntimeException('Invalid source invoice number.');
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $row['period'])) {
            throw new RuntimeException('Period must use YYYY-MM format.');
        }
        $issueDate = Carbon::createFromFormat('!Y-m-d', $row['issue_date']);
        if ($issueDate === false || $issueDate->format('Y-m-d') !== $row['issue_date']) {
            throw new RuntimeException('Issue date must use a valid YYYY-MM-DD date.');
        }
        $amount = $this->money($row['invoice_amount']);
        $paid = $this->money($row['source_paid_amount']);
        if ($amount <= 0) {
            throw new RuntimeException('Invoice amount must be greater than zero.');
        }
        if ($paid < 0 || $paid - $amount > 0.009) {
            throw new RuntimeException('Source paid amount must be between zero and the invoice amount.');
        }
    }

    /**
     * @param array<string, string> $row
     * @return array{property:Property,unit:PropertyUnit,tenant:PmTenant,lease:PmLease}|null
     */
    private function resolveRow(array $row, int $agentUserId): ?array
    {
        $properties = app(PassionPropertyCodeResolver::class)->resolveMany($row['property_code'])
            ->filter(fn (Property $property): bool => ! Schema::hasColumn('properties', 'agent_user_id')
                || (int) $property->agent_user_id === $agentUserId)
            ->values();
        if ($properties->count() !== 1) {
            return null;
        }
        $property = $properties->first();

        $units = PropertyUnit::query()->withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->whereRaw('LOWER(TRIM(label)) = ?', [mb_strtolower(trim($row['unit_no']))])
            ->get();
        if ($units->count() !== 1) {
            return null;
        }
        $unit = $units->first();

        $tenants = PmTenant::query()->withoutGlobalScopes()
            ->where('account_number', strtoupper(trim($row['tenant_account'])))
            ->when(Schema::hasColumn('pm_tenants', 'agent_user_id'), fn ($query) => $query->where(function ($q) use ($agentUserId): void {
                $q->where('agent_user_id', $agentUserId)->orWhereNull('agent_user_id');
            }))
            ->get();
        if ($tenants->count() !== 1) {
            return null;
        }
        $tenant = $tenants->first();
        if (! $this->namesMatch($row['tenant_name'], (string) $tenant->name)) {
            return null;
        }

        $leases = PmLease::query()->withoutGlobalScopes()
            ->where('pm_tenant_id', $tenant->id)
            ->whereHas('units', fn ($query) => $query->where('property_units.id', $unit->id))
            ->with('units')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
        $issueDate = $row['issue_date'];
        $lease = $leases->first(function (PmLease $candidate) use ($issueDate): bool {
            return ($candidate->start_date === null || $candidate->start_date->toDateString() <= $issueDate)
                && ($candidate->end_date === null || $candidate->end_date->toDateString() >= $issueDate);
        }) ?? $leases->first(fn (PmLease $candidate): bool => $candidate->status === PmLease::STATUS_ACTIVE);

        return $lease ? compact('property', 'unit', 'tenant', 'lease') : null;
    }

    /** @param array<string, string> $row */
    private function findExistingInvoice(string $sourceInvoiceNo): ?PmInvoice
    {
        return PmInvoice::query()->withoutGlobalScopes()
            ->where(function ($query) use ($sourceInvoiceNo): void {
                $query->where('carry_forward_origin->source', 'late_penalty_register_import')
                    ->where('carry_forward_origin->source_invoice_no', strtoupper($sourceInvoiceNo));
            })
            ->first();
    }

    /**
     * @param array<string, string> $row
     * @param array{property:Property,unit:PropertyUnit,tenant:PmTenant,lease:PmLease} $resolved
     */
    private function createInvoice(array $row, array $resolved, ?User $actor, bool $postGl): void
    {
        DB::transaction(function () use ($row, $resolved, $actor, $postGl): void {
            $sourceNo = strtoupper($row['source_invoice_no']);
            if ($this->findExistingInvoice($sourceNo) !== null) {
                return;
            }

            $amount = $this->money($row['invoice_amount']);
            $invoice = PmInvoice::query()->create([
                'pm_lease_id' => $resolved['lease']->id,
                'property_unit_id' => $resolved['unit']->id,
                'pm_tenant_id' => $resolved['tenant']->id,
                'agent_user_id' => $resolved['property']->agent_user_id,
                'invoice_no' => PmInvoice::nextInvoiceNumber(),
                'issue_date' => $row['issue_date'],
                'due_date' => $row['issue_date'],
                'amount' => $amount,
                'amount_paid' => 0,
                'subtotal_amount' => $amount,
                'total_amount' => $amount,
                'status' => PmInvoice::STATUS_SENT,
                'sent_at' => Carbon::parse($row['issue_date'])->startOfDay(),
                'invoice_type' => PmInvoice::TYPE_LATE_PAYMENT,
                'billing_period' => $row['period'],
                'description' => '[Late penalty '.$sourceNo.'] '.$row['period'].' · '.$resolved['tenant']->name.' · '.$resolved['unit']->label,
                'carry_forward_origin' => [
                    'source' => 'late_penalty_register_import',
                    'source_invoice_no' => $sourceNo,
                    'source_paid_amount' => $this->money($row['source_paid_amount']),
                    'source_tenant_account' => strtoupper($row['tenant_account']),
                    'source_property_code' => strtoupper($row['property_code']),
                    'source_unit_no' => $row['unit_no'],
                ],
            ]);

            if ($postGl) {
                PropertyAccountingPostingService::postInvoiceIssued($invoice->fresh(), $actor);
            }
        });
    }

    private function namesMatch(string $source, string $system): bool
    {
        $normalize = static function (string $value): string {
            $value = strtoupper(preg_replace('/[^A-Z0-9]+/i', ' ', trim($value)) ?? '');
            return trim(preg_replace('/\s+/', ' ', $value) ?? '');
        };
        $source = $normalize($source);
        $system = $normalize($system);
        if ($source === '' || $system === '') {
            return false;
        }
        return $source === $system || str_contains($source, $system) || str_contains($system, $source);
    }

    private function money(string $value): float
    {
        $value = str_replace([',', ' ', 'KES'], '', strtoupper(trim($value)));
        if ($value === '' || ! is_numeric($value)) {
            throw new RuntimeException('Invalid amount.');
        }
        return round((float) $value, 2);
    }
}
