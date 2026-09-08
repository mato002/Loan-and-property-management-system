<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EzenRentalInvoicesImportService
{
    public function __construct(
        private readonly EzenRentalInvoiceScheduleParser $parser,
        private readonly PassionPropertyCodeResolver $codeResolver,
        private readonly PropertyPaymentSettlementService $payments,
    ) {}

    /**
     * @return array{
     *     parsed:int,
     *     imported:int,
     *     skipped_existing:int,
     *     skipped_deposit:int,
     *     skipped_unmatched:int,
     *     payments_posted:int,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromPath(
        string $path,
        int $agentUserId,
        ?User $actor = null,
        bool $dryRun = false,
        bool $includeDeposits = false,
        bool $postGl = false,
        ?string $propertyCodeFilter = null,
        ?int $limit = null,
    ): array {
        $rows = $this->parser->parsePath($path);
        if ($propertyCodeFilter !== null && trim($propertyCodeFilter) !== '') {
            $filter = strtoupper(trim($propertyCodeFilter));
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => strtoupper((string) ($row['property_code'] ?? '')) === $filter,
            ));
        }
        if ($limit !== null && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $summary = [
            'parsed' => count($rows),
            'imported' => 0,
            'skipped_existing' => 0,
            'skipped_deposit' => 0,
            'skipped_unmatched' => 0,
            'payments_posted' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        $unitsByProperty = $this->unitsByPropertyCode($agentUserId);

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            try {
                $result = $this->importRow(
                    $row,
                    $agentUserId,
                    $actor,
                    $dryRun,
                    $includeDeposits,
                    $postGl,
                    $unitsByProperty,
                    $rowNum,
                );
                $summary['imported'] += $result['imported'] ? 1 : 0;
                $summary['skipped_existing'] += $result['skipped_existing'] ? 1 : 0;
                $summary['skipped_deposit'] += $result['skipped_deposit'] ? 1 : 0;
                $summary['skipped_unmatched'] += $result['skipped_unmatched'] ? 1 : 0;
                $summary['payments_posted'] += $result['payments_posted'];
                $summary['warnings'] = array_merge($summary['warnings'], $result['warnings']);
            } catch (RuntimeException $e) {
                $summary['errors'][] = 'Row '.$rowNum.' '.$row['ezen_invoice_no'].': '.$e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Collection<int, PropertyUnit>>  $unitsByProperty
     * @return array{
     *     imported:bool,
     *     skipped_existing:bool,
     *     skipped_deposit:bool,
     *     skipped_unmatched:bool,
     *     payments_posted:int,
     *     warnings:list<string>
     * }
     */
    private function importRow(
        array $row,
        int $agentUserId,
        ?User $actor,
        bool $dryRun,
        bool $includeDeposits,
        bool $postGl,
        array $unitsByProperty,
        int $rowNum,
    ): array {
        $warnings = [];
        $memo = trim((string) ($row['memo'] ?? ''));
        $invoiceType = $this->invoiceTypeFromMemo($memo);
        if ($invoiceType === null) {
            if ($this->isDepositMemo($memo) && ! $includeDeposits) {
                return [
                    'imported' => false,
                    'skipped_existing' => false,
                    'skipped_deposit' => true,
                    'skipped_unmatched' => false,
                    'payments_posted' => 0,
                    'warnings' => $warnings,
                ];
            }

            throw new RuntimeException('Unsupported memo: '.$memo);
        }

        $amount = round((float) ($row['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        $ezenNo = strtoupper(trim((string) ($row['ezen_invoice_no'] ?? '')));
        if ($ezenNo === '') {
            throw new RuntimeException('Missing EZEN invoice number.');
        }

        if ($this->findExistingInvoice($ezenNo) !== null) {
            return [
                'imported' => false,
                'skipped_existing' => true,
                'skipped_deposit' => false,
                'skipped_unmatched' => false,
                'payments_posted' => 0,
                'warnings' => $warnings,
            ];
        }

        $propertyCode = strtoupper(trim((string) ($row['property_code'] ?? '')));
        $property = $this->resolveProperty($propertyCode, $agentUserId);
        if ($property === null) {
            throw new RuntimeException('Property '.$propertyCode.' not found.');
        }

        $units = $unitsByProperty[$propertyCode] ?? collect();
        $resolved = $this->resolveUnitAndTenant($property, $units, (string) ($row['header_tail'] ?? ''));
        if ($resolved === null) {
            $warnings[] = 'Row '.$rowNum.' '.$ezenNo.': could not match unit/tenant on '.$propertyCode.' — skipped.';

            return [
                'imported' => false,
                'skipped_existing' => false,
                'skipped_deposit' => false,
                'skipped_unmatched' => true,
                'payments_posted' => 0,
                'warnings' => $warnings,
            ];
        }

        ['unit' => $unit, 'tenant_name' => $tenantName, 'lease' => $lease] = $resolved;
        if (! $this->namesLooselyMatch($tenantName, (string) $lease->pmTenant?->name)) {
            $warnings[] = 'Row '.$rowNum.' '.$ezenNo.': tenant "'.$tenantName.'" vs system "'
                .$lease->pmTenant?->name.'" — applied to lease on '.$unit->label.'.';
        }

        if ($dryRun) {
            $paid = round((float) ($row['paid'] ?? 0), 2);

            return [
                'imported' => true,
                'skipped_existing' => false,
                'skipped_deposit' => false,
                'skipped_unmatched' => false,
                'payments_posted' => $paid > 0.009 ? 1 : 0,
                'warnings' => $warnings,
            ];
        }

        $issueDate = (string) ($row['issue_date'] ?? now()->toDateString());
        $billingPeriod = $this->billingPeriodFromMemo($memo, $issueDate);
        $dueDate = $issueDate;
        $description = '[EZEN '.$ezenNo.'] '.$memo.' · '.trim($tenantName).' · '.$unit->label;
        $paid = round((float) ($row['paid'] ?? 0), 2);

        $paymentsPosted = 0;
        DB::transaction(function () use (
            $lease,
            $unit,
            $amount,
            $issueDate,
            $dueDate,
            $billingPeriod,
            $invoiceType,
            $description,
            $ezenNo,
            $memo,
            $paid,
            $postGl,
            $actor,
            &$paymentsPosted,
        ): void {
            $invoiceNo = PmInvoice::nextInvoiceNumber();
            $invoice = PmInvoice::query()->create([
                'pm_lease_id' => $lease->id,
                'property_unit_id' => $unit->id,
                'pm_tenant_id' => $lease->pm_tenant_id,
                'agent_user_id' => $unit->property?->agent_user_id,
                'invoice_no' => $invoiceNo,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'amount' => $amount,
                'amount_paid' => 0,
                'subtotal_amount' => $amount,
                'total_amount' => $amount,
                'status' => PmInvoice::STATUS_SENT,
                'sent_at' => Carbon::parse($issueDate)->startOfDay(),
                'invoice_type' => $invoiceType,
                'billing_period' => $billingPeriod,
                'description' => $description,
                'carry_forward_origin' => [
                    'source' => 'ezen_rental_invoice_import',
                    'ezen_invoice_no' => $ezenNo,
                    'memo' => $memo,
                ],
            ]);

            if ($invoiceType === PmInvoice::TYPE_RENT) {
                $invoice->ensureDefaultRentLineItem($amount);
            }

            if ($postGl) {
                PropertyAccountingPostingService::postInvoiceIssued($invoice->fresh(), $actor);
            }

            if ($paid > 0.009) {
                $this->payments->recordPaymentToInvoice(
                    $invoice->fresh(),
                    min($paid, $amount),
                    'ezen_import',
                    'EZEN-'.$ezenNo,
                    Carbon::parse($issueDate)->startOfDay(),
                    $actor,
                    [
                        'source' => 'ezen_rental_invoice_import',
                        'ezen_invoice_no' => $ezenNo,
                    ],
                    $unit->property?->agent_user_id ? (int) $unit->property->agent_user_id : null,
                    $postGl,
                );
                $paymentsPosted = 1;
            }

            $invoice->refresh();
            $invoice->syncAmountPaidFromAllocations();
            $invoice->refreshComputedStatus();
        });

        return [
            'imported' => true,
            'skipped_existing' => false,
            'skipped_deposit' => false,
            'skipped_unmatched' => false,
            'payments_posted' => $paymentsPosted,
            'warnings' => $warnings,
        ];
    }

    private function findExistingInvoice(string $ezenNo): ?PmInvoice
    {
        return PmInvoice::query()
            ->withoutGlobalScopes()
            ->where(function ($query) use ($ezenNo): void {
                $query->where('description', 'like', '[EZEN '.$ezenNo.']%');
                if (Schema::hasColumn('pm_invoices', 'carry_forward_origin')) {
                    $query->orWhere('carry_forward_origin->ezen_invoice_no', $ezenNo);
                }
            })
            ->first();
    }

    private function resolveProperty(string $code, int $agentUserId): ?Property
    {
        $matches = $this->codeResolver->resolveMany($code);
        if ($matches->isEmpty()) {
            return null;
        }

        return $matches->first(function (Property $property) use ($agentUserId): bool {
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return true;
            }

            return (int) $property->agent_user_id === $agentUserId;
        });
    }

    /**
     * @param  Collection<int, PropertyUnit>  $units
     * @return array{unit: PropertyUnit, tenant_name: string, lease: PmLease}|null
     */
    private function resolveUnitAndTenant(Property $property, Collection $units, string $headerTail): ?array
    {
        $haystack = strtoupper(preg_replace('/\s+/', ' ', $headerTail) ?? '');
        if ($haystack === '') {
            return null;
        }

        $propertyName = strtoupper(preg_replace('/\s+/', ' ', (string) $property->name) ?? '');
        if ($propertyName !== '' && str_starts_with($haystack, $propertyName)) {
            $haystack = trim(substr($haystack, strlen($propertyName)));
        }

        /** @var PropertyUnit|null $matchedUnit */
        $matchedUnit = null;
        $matchedPos = null;
        foreach ($units->sortByDesc(fn (PropertyUnit $unit): int => strlen((string) $unit->label)) as $unit) {
            $label = strtoupper(trim((string) $unit->label));
            if ($label === '') {
                continue;
            }
            $pos = strpos($haystack, $label);
            if ($pos === false) {
                continue;
            }
            if ($matchedPos === null || $pos < $matchedPos) {
                $matchedUnit = $unit;
                $matchedPos = $pos;
            }
        }

        if (! $matchedUnit instanceof PropertyUnit) {
            return null;
        }

        $label = strtoupper(trim((string) $matchedUnit->label));
        $pos = strpos($haystack, $label);
        $tenantName = trim(substr($haystack, $pos + strlen($label)));
        $tenantName = preg_replace('/\s+/', ' ', $tenantName) ?? $tenantName;
        if ($tenantName === '') {
            return null;
        }

        $lease = PmLease::query()
            ->withoutGlobalScopes()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->where('property_units.id', $matchedUnit->id))
            ->with('pmTenant')
            ->orderByDesc('start_date')
            ->first();

        if ($lease === null) {
            $lease = PmLease::query()
                ->withoutGlobalScopes()
                ->whereHas('units', fn ($q) => $q->where('property_units.id', $matchedUnit->id))
                ->with('pmTenant')
                ->orderByDesc('start_date')
                ->first();
        }

        if ($lease === null) {
            return null;
        }

        return [
            'unit' => $matchedUnit,
            'tenant_name' => $tenantName,
            'lease' => $lease,
        ];
    }

    /**
     * @return array<string, Collection<int, PropertyUnit>>
     */
    private function unitsByPropertyCode(int $agentUserId): array
    {
        $query = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->with(['property' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereHas('property', function ($propertyQuery) use ($agentUserId): void {
                if (Schema::hasColumn('properties', 'agent_user_id')) {
                    $propertyQuery->where('agent_user_id', $agentUserId);
                }
            });

        $map = [];
        foreach ($query->get() as $unit) {
            $code = strtoupper(trim((string) $unit->property?->code));
            if ($code === '') {
                continue;
            }
            $map[$code] ??= collect();
            $map[$code]->push($unit);
        }

        return $map;
    }

    private function invoiceTypeFromMemo(string $memo): ?string
    {
        $upper = strtoupper($memo);
        if (str_contains($upper, 'RENT DEPOSIT') || str_contains($upper, 'WATER DEPOSIT') || str_contains($upper, 'ELECTRICITY DEPOSIT')) {
            return null;
        }
        if (str_starts_with($upper, 'RENT FOR')) {
            return PmInvoice::TYPE_RENT;
        }
        if (str_contains($upper, 'GARBAGE')) {
            return PmInvoice::TYPE_GARBAGE;
        }
        if (str_contains($upper, 'WATER')) {
            return PmInvoice::TYPE_WATER;
        }
        if (str_contains($upper, 'ELECTRICITY')) {
            return PmInvoice::TYPE_ELECTRICITY;
        }
        if (str_contains($upper, 'LEASE FEE')) {
            return PmInvoice::TYPE_SERVICE;
        }

        return PmInvoice::TYPE_SERVICE;
    }

    private function isDepositMemo(string $memo): bool
    {
        $upper = strtoupper($memo);

        return str_contains($upper, 'RENT DEPOSIT')
            || str_contains($upper, 'WATER DEPOSIT')
            || str_contains($upper, 'ELECTRICITY DEPOSIT');
    }

    private function billingPeriodFromMemo(string $memo, string $issueDate): ?string
    {
        if (preg_match('/([A-Za-z]{3})\/(\d{4})/', $memo, $match) === 1) {
            try {
                return Carbon::parse('01 '.$match[1].' '.$match[2])->format('Y-m');
            } catch (\Throwable) {
                // fall through
            }
        }

        return substr($issueDate, 0, 7);
    }

    private function namesLooselyMatch(string $a, string $b): bool
    {
        $a = $this->nameKey($a);
        $b = $this->nameKey($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }
        $max = max(strlen($a), strlen($b));

        return $max > 8 && (similar_text($a, $b) / $max) >= 0.88;
    }

    private function nameKey(string $name): string
    {
        $n = strtoupper($name);
        $n = str_replace(["'", '`'], '', $n);
        $n = preg_replace('/[^A-Z0-9]+/', ' ', $n) ?? '';
        $n = preg_replace('/\b(OCCP|OCCUPIED|OCC)\b/', ' ', $n) ?? '';
        $n = preg_replace('/\s+/', ' ', trim($n)) ?? '';

        return trim($n);
    }
}
