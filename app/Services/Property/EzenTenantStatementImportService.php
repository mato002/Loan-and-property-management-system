<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Import missing charge lines (late-payment DBNs, rent deposits, and opening
 * balances) from an EZEN
 * Tenant/Resident Statement of Account SpreadsheetML (.xls) export.
 */
final class EzenTenantStatementImportService
{
    /**
     * @return array{
     *     tenant: ?string,
     *     account: ?string,
     *     unit: ?string,
     *     property: ?string,
     *     charges_parsed: int,
     *     imported: int,
     *     skipped_existing: int,
     *     payments_parsed: int,
     *     payments_imported: int,
     *     payments_skipped_existing: int,
     *     skipped_unmatched: int,
     *     payments_reallocated: float,
     *     warnings: list<string>,
     *     errors: list<string>
     * }
     */
    public function importFromPath(string $path, int $agentUserId, ?User $actor = null, bool $dryRun = false, bool $postGl = false): array
    {
        $summary = [
            'tenant' => null,
            'account' => null,
            'unit' => null,
            'property' => null,
            'charges_parsed' => 0,
            'imported' => 0,
            'skipped_existing' => 0,
            'payments_parsed' => 0,
            'payments_imported' => 0,
            'payments_skipped_existing' => 0,
            'skipped_unmatched' => 0,
            'payments_reallocated' => 0.0,
            'warnings' => [],
            'errors' => [],
        ];

        if (! is_readable($path)) {
            $summary['errors'][] = 'File not readable: '.$path;

            return $summary;
        }

        $parsed = $this->parseSpreadsheet($path);
        $summary['tenant'] = $parsed['tenant'];
        $summary['account'] = $parsed['account'];
        $summary['unit'] = $parsed['unit'];
        $summary['property'] = $parsed['property'];
        $summary['charges_parsed'] = count($parsed['charges']);
        $summary['payments_parsed'] = count($parsed['payments']);

        $tenant = $this->resolveTenant($parsed, $agentUserId);
        if ($tenant === null) {
            $summary['skipped_unmatched'] = count($parsed['charges']) + count($parsed['payments']);
            $summary['errors'][] = 'Could not match tenant '
                .($parsed['account'] ?: $parsed['tenant'] ?: '(unknown)')
                .' / unit '.($parsed['unit'] ?: '?');

            return $summary;
        }

        if ($parsed['charges'] === [] && $parsed['payments'] === []) {
            $summary['warnings'][] = 'No supported charge or payment lines found in the statement.';

            return $summary;
        }

        $lease = PmLease::query()
            ->where('pm_tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first()
            ?? PmLease::query()->where('pm_tenant_id', $tenant->id)->orderByDesc('id')->first();

        if ($lease === null) {
            $summary['skipped_unmatched'] = count($parsed['charges']);
            $summary['errors'][] = 'Tenant #'.$tenant->id.' has no lease.';

            return $summary;
        }

        $unit = $lease->units()->first();
        if ($unit === null) {
            $summary['skipped_unmatched'] = count($parsed['charges']);
            $summary['errors'][] = 'Lease #'.$lease->id.' has no unit.';

            return $summary;
        }

        foreach ($parsed['charges'] as $row) {
            if ($this->findExistingCharge($row['txn_no']) !== null) {
                $summary['skipped_existing']++;

                continue;
            }

            if ($this->findExistingPeriodCharge((int) $tenant->id, $row['type'], $row['period']) !== null) {
                $summary['skipped_existing']++;

                continue;
            }

            if ($dryRun) {
                $summary['imported']++;

                continue;
            }

            try {
                $this->createChargeInvoice($tenant, $lease, $unit, $row, $actor, $postGl);
                $summary['imported']++;
            } catch (QueryException $e) {
                if ($this->findExistingCharge($row['txn_no']) !== null) {
                    $summary['skipped_existing']++;

                    continue;
                }
                $summary['errors'][] = $row['txn_no'].': '.$e->getMessage();
            } catch (\Throwable $e) {
                $summary['errors'][] = $row['txn_no'].': '.$e->getMessage();
            }
        }

        foreach ($parsed['payments'] as $row) {
            $existingPayment = $this->findExistingPayment(
                (int) $tenant->id,
                $row['txn_no'],
                $row['external_ref'],
            );
            $paymentAmount = (float) $row['amount'];
            $externalRef = $row['external_ref'];
            $isAmountRepair = false;
            if ($existingPayment !== null) {
                $recordedAmount = (float) PmPayment::query()
                    ->withoutGlobalScopes()
                    ->where('pm_tenant_id', $tenant->id)
                    ->where(function ($query) use ($row): void {
                        $query->whereIn('external_ref', [
                            $row['external_ref'],
                            $row['external_ref'].'-STMT-ADJ',
                        ]);
                        if (Schema::hasColumn('pm_payments', 'meta')) {
                            $query->orWhere('meta->ezen_receipt_no', $row['txn_no']);
                        }
                    })
                    ->sum('amount');
                $difference = round($paymentAmount - $recordedAmount, 2);
                // SpreadsheetML displays whole shillings in many exports while
                // imported register rows retain cents. Do not "repair" display
                // rounding; only restore a materially short receipt.
                if ($difference <= 1.00) {
                    if ($difference < -1.00) {
                        $summary['warnings'][] = $row['txn_no'].' is '
                            .number_format(abs($difference), 2)
                            .' lower than payments already recorded; existing payments were not reduced.';
                    }
                    $summary['payments_skipped_existing']++;

                    continue;
                }
                $paymentAmount = $difference;
                $externalRef .= '-STMT-ADJ';
                $isAmountRepair = true;
            }

            if ($dryRun) {
                $summary['payments_imported']++;

                continue;
            }

            try {
                app(PropertyPaymentSettlementService::class)->recordAdvancePayment([
                    'pm_tenant_id' => $tenant->id,
                    'channel' => 'ezen_import',
                    'amount' => $paymentAmount,
                    'external_ref' => $externalRef,
                    'paid_at' => $row['date'],
                    'meta' => [
                        'source' => $isAmountRepair
                            ? 'ezen_tenant_statement_amount_repair'
                            : 'ezen_tenant_statement',
                        'ezen_receipt_no' => $row['txn_no'],
                        'ezen_ref_no' => $row['bank_ref'],
                        'particulars' => $row['memo'],
                        'statement_amount' => $row['amount'],
                        'property_code' => $parsed['property'],
                        'unit_label' => $parsed['unit'],
                    ],
                ], $actor);
                $summary['payments_imported']++;
            } catch (QueryException $e) {
                if ($this->findExistingPayment((int) $tenant->id, $row['txn_no'], $row['external_ref']) !== null) {
                    $summary['payments_skipped_existing']++;

                    continue;
                }
                $summary['errors'][] = $row['txn_no'].': '.$e->getMessage();
            } catch (\Throwable $e) {
                $summary['errors'][] = $row['txn_no'].': '.$e->getMessage();
            }
        }

        if (! $dryRun && $summary['imported'] > 0) {
            $summary['payments_reallocated'] = $this->allocateLeftoverPayments((int) $tenant->id);
        }

        return $summary;
    }

    /**
     * @return array{
     *     tenant: ?string,
     *     account: ?string,
     *     unit: ?string,
     *     property: ?string,
     *     charges: list<array{txn_no:string,date:string,memo:string,period:?string,amount:float,type:string}>,
     *     payments: list<array{txn_no:string,date:string,memo:string,amount:float,bank_ref:string,external_ref:string}>
     * }
     */
    public function parseSpreadsheet(string $path): array
    {
        $xml = file_get_contents($path);
        if ($xml === false || $xml === '') {
            throw new RuntimeException('Empty statement file.');
        }

        $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml) ?? $xml;
        $xml = preg_replace('/\b[a-zA-Z_][\w\-]*:/', '', $xml) ?? $xml;
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            throw new RuntimeException('Could not parse SpreadsheetML statement.');
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

        $tenant = null;
        $account = null;
        $unit = null;
        $property = null;
        $charges = [];
        $payments = [];
        $colDate = 1;
        $colTxn = 2;
        $colDetails = 3;
        $colCharges = null;
        $colPayments = null;
        $inLedger = false;

        foreach ($rows as $cells) {
            $values = array_values($cells);
            $joined = strtoupper(implode(' | ', $values));

            if ($account === null) {
                foreach ($values as $value) {
                    if (preg_match('/^TNT\d+$/i', $value) === 1) {
                        $account = strtoupper($value);
                        break;
                    }
                }
            }

            if ($tenant === null) {
                foreach ($cells as $idx => $value) {
                    if (preg_match('/^\[([A-Z0-9]+)\]\s*(.+)$/i', $value) !== 1) {
                        continue;
                    }
                    $property = trim($value);
                    $nameCandidate = null;
                    $unitCandidate = null;
                    foreach ($cells as $otherIdx => $otherVal) {
                        $otherVal = trim((string) $otherVal);
                        if ($otherIdx === $idx || $otherVal === '') {
                            continue;
                        }
                        if (preg_match('/^TNT\d+$/i', $otherVal) === 1) {
                            continue;
                        }
                        if (str_contains(strtoupper($otherVal), 'STATEMENT')) {
                            continue;
                        }
                        if ($otherIdx < $idx && $nameCandidate === null) {
                            $nameCandidate = $otherVal;
                        }
                        if ($otherIdx > $idx && $unitCandidate === null) {
                            $unitCandidate = $otherVal;
                        }
                    }
                    $tenant = $nameCandidate;
                    $unit = $unitCandidate;
                    break;
                }
            }

            if (! $inLedger && isset($cells[1]) && strtoupper($cells[1]) === 'DATE' && str_contains($joined, 'TXN')) {
                $inLedger = true;
                foreach ($cells as $idx => $label) {
                    $upper = strtoupper(trim($label));
                    if ($upper === 'DATE') {
                        $colDate = $idx;
                    } elseif (str_contains($upper, 'TXN')) {
                        $colTxn = $idx;
                    } elseif ($upper === 'DETAILS') {
                        $colDetails = $idx;
                    } elseif (str_contains($upper, 'CHARGE')) {
                        $colCharges = $idx;
                    } elseif (str_contains($upper, 'PAYMENT')) {
                        $colPayments = $idx;
                    }
                }

                continue;
            }

            if (! $inLedger || $colCharges === null) {
                continue;
            }

            $txn = strtoupper(trim((string) ($cells[$colTxn] ?? '')));
            $details = trim((string) ($cells[$colDetails] ?? ''));
            $chargeDate = $this->parseDate((string) ($cells[$colDate] ?? '')) ?? now()->toDateString();
            $paymentAmount = $colPayments !== null
                ? $this->parseMoney((string) ($cells[$colPayments] ?? ''))
                : null;
            $isReceipt = preg_match('/^RC[\w-]+$/i', $txn) === 1;
            $isOpeningCredit = preg_match('/^\s*opening\s+balance\s*$/i', $details) === 1
                && ($txn === '' || $txn === '-');
            if ($paymentAmount !== null && $paymentAmount > 0.009 && ($isReceipt || $isOpeningCredit)) {
                if ($isOpeningCredit) {
                    $txn = 'OB-CREDIT-'.($account ?: 'TENANT').'-'.substr($chargeDate, 0, 4);
                }
                $bankRef = '';
                if (preg_match('/\bREF\s*NO\s*:\s*([^\/,\s]+)/i', $details, $refMatch) === 1) {
                    $bankRef = strtoupper(trim($refMatch[1]));
                }
                $payments[] = [
                    'txn_no' => $txn,
                    'date' => $chargeDate,
                    'memo' => $details,
                    'amount' => $paymentAmount,
                    'bank_ref' => $bankRef,
                    'external_ref' => $bankRef !== '' && $bankRef !== 'CASH'
                        ? $bankRef
                        : 'EZEN-'.$txn,
                ];
            }

            $chargeRaw = $this->parseMoney((string) ($cells[$colCharges] ?? ''));
            if ($chargeRaw === null || $chargeRaw <= 0.009) {
                continue;
            }

            $isLatePayment = str_starts_with($txn, 'DBN-')
                || preg_match('/late\s+payment\s+charge/i', $details) === 1;
            $isDebitNote = preg_match('/^DN[\w-]+$/i', $txn) === 1;
            $isReceiptReversal = str_starts_with($txn, 'RVS-');
            $isDeposit = preg_match(
                '/^\s*(?:rent|water|electricity|garbage)\s+deposit\s*$/i',
                $details,
            ) === 1;
            $isOpeningBalance = preg_match('/^\s*opening\s+balance\s*$/i', $details) === 1;
            $standardType = $this->standardChargeType($details);
            $isStandardInvoice = preg_match('/^INV[\w-]+$/i', $txn) === 1 && $standardType !== null;
            if (! $isLatePayment
                && ! $isDebitNote
                && ! $isReceiptReversal
                && ! $isDeposit
                && ! $isOpeningBalance
                && ! $isStandardInvoice) {
                continue;
            }

            if ($isOpeningBalance && ($txn === '' || $txn === '-')) {
                $txn = 'OB-'.($account ?: 'TENANT').'-'.substr($chargeDate, 0, 4);
            }
            if ($txn === '' || $txn === '-') {
                continue;
            }

            $type = $isLatePayment
                ? PmInvoice::TYPE_LATE_PAYMENT
                : (($isStandardInvoice ? $standardType : null) ?? PmInvoice::TYPE_SERVICE);

            $charges[] = [
                'txn_no' => $txn,
                'date' => $chargeDate,
                'memo' => $details !== '' ? $details : 'Late payment charge',
                'period' => $this->billingPeriodFromMemo($details),
                'amount' => $chargeRaw,
                'type' => $type,
            ];
        }

        return [
            'tenant' => $tenant,
            'account' => $account,
            'unit' => $unit,
            'property' => $property,
            'charges' => $charges,
            'payments' => $payments,
        ];
    }

    /**
     * @param  array{tenant:?string,account:?string,unit:?string,property:?string}  $parsed
     */
    private function resolveTenant(array $parsed, int $agentUserId): ?PmTenant
    {
        $query = PmTenant::query()->withoutGlobalScopes();
        $chooseCandidate = function ($candidates) use ($parsed, $agentUserId): ?PmTenant {
            if ($candidates->isEmpty()) {
                return null;
            }

            if (Schema::hasColumn('pm_tenants', 'agent_user_id') && $agentUserId > 0) {
                $owned = $candidates
                    ->filter(fn (PmTenant $tenant): bool => (int) $tenant->agent_user_id === $agentUserId)
                    ->values();
                if ($owned->count() === 1) {
                    return $owned->first();
                }
                if ($owned->isNotEmpty()) {
                    $candidates = $owned;
                }
            }

            if ($candidates->count() === 1) {
                return $candidates->first();
            }

            $unitLabel = strtoupper(trim((string) ($parsed['unit'] ?? '')));
            if ($unitLabel !== '') {
                $unitMatches = $candidates
                    ->filter(function (PmTenant $tenant) use ($unitLabel): bool {
                        return $tenant->leases()->whereHas('units', function ($q) use ($unitLabel): void {
                            $q->whereRaw('UPPER(label) = ?', [$unitLabel]);
                        })->exists();
                    })
                    ->values();
                if ($unitMatches->count() === 1) {
                    return $unitMatches->first();
                }
            }

            return null;
        };

        if (! empty($parsed['account'])) {
            $byAccount = $chooseCandidate(
                (clone $query)->where('account_number', $parsed['account'])->get()
            );
            if ($byAccount) {
                return $byAccount;
            }
        }

        $name = trim((string) ($parsed['tenant'] ?? ''));
        if ($name === '') {
            return null;
        }

        $exact = $chooseCandidate(
            (clone $query)->whereRaw('UPPER(name) = ?', [strtoupper($name)])->get()
        );
        if ($exact) {
            return $exact;
        }

        // EZEN name may include a middle name the ERP shortened.
        $tokens = preg_split('/\s+/', strtoupper($name)) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => strlen($t) >= 3));
        if (count($tokens) >= 2) {
            $candidates = (clone $query)
                ->where(function ($q) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $q->where('name', 'like', '%'.$token.'%');
                    }
                })
                ->limit(5)
                ->get();
            $matched = $chooseCandidate($candidates);
            if ($matched) {
                return $matched;
            }
        }

        return null;
    }

    private function findExistingCharge(string $txnNo): ?PmInvoice
    {
        return PmInvoice::query()
            ->withoutGlobalScopes()
            ->where(function ($query) use ($txnNo): void {
                $query->where('description', 'like', '[EZEN '.$txnNo.']%')
                    ->orWhere('description', 'like', '%[EZEN '.$txnNo.']%');
                if (Schema::hasColumn('pm_invoices', 'carry_forward_origin')) {
                    $query->orWhere('carry_forward_origin->ezen_invoice_no', $txnNo);
                }
            })
            ->first();
    }

    private function findExistingPeriodCharge(int $tenantId, string $type, ?string $period): ?PmInvoice
    {
        if ($period === null || $period === '') {
            return null;
        }

        if (! in_array($type, [
            PmInvoice::TYPE_RENT,
            PmInvoice::TYPE_GARBAGE,
            PmInvoice::TYPE_WATER,
            PmInvoice::TYPE_ELECTRICITY,
        ], true)) {
            return null;
        }

        return PmInvoice::query()
            ->withoutGlobalScopes()
            ->where('pm_tenant_id', $tenantId)
            ->where('invoice_type', $type)
            ->where('billing_period', $period)
            ->first();
    }

    private function standardChargeType(string $details): ?string
    {
        if (preg_match('/deposit/i', $details) === 1) {
            return null;
        }
        if (preg_match('/^\s*lease\s+fee\b/i', $details) === 1) {
            return PmInvoice::TYPE_SERVICE;
        }
        if (preg_match('/^\s*rent\b/i', $details) === 1) {
            return PmInvoice::TYPE_RENT;
        }
        if (preg_match('/^\s*garbage\b/i', $details) === 1) {
            return PmInvoice::TYPE_GARBAGE;
        }
        if (preg_match('/^\s*water\b/i', $details) === 1) {
            return PmInvoice::TYPE_WATER;
        }
        if (preg_match('/^\s*electricity\b/i', $details) === 1) {
            return PmInvoice::TYPE_ELECTRICITY;
        }

        return null;
    }

    private function findExistingPayment(int $tenantId, string $receiptNo, string $externalRef): ?PmPayment
    {
        return PmPayment::query()
            ->withoutGlobalScopes()
            ->where('pm_tenant_id', $tenantId)
            ->where(function ($query) use ($receiptNo, $externalRef): void {
                $query->where('external_ref', $externalRef);
                if (Schema::hasColumn('pm_payments', 'meta')) {
                    $query->orWhere('meta->ezen_receipt_no', $receiptNo);
                }
            })
            ->first();
    }

    /**
     * @param  array{txn_no:string,date:string,memo:string,period:?string,amount:float,type:string}  $row
     */
    private function createChargeInvoice(
        PmTenant $tenant,
        PmLease $lease,
        $unit,
        array $row,
        ?User $actor,
        bool $postGl,
    ): PmInvoice {
        return DB::transaction(function () use ($tenant, $lease, $unit, $row, $actor, $postGl): PmInvoice {
            $amount = round((float) $row['amount'], 2);
            $description = '[EZEN '.$row['txn_no'].'] '.$row['memo'].' · '.$tenant->name.' · '.$unit->label;

            $invoice = PmInvoice::query()->create([
                'pm_lease_id' => $lease->id,
                'property_unit_id' => $unit->id,
                'pm_tenant_id' => $tenant->id,
                'agent_user_id' => $unit->property?->agent_user_id ?? $tenant->agent_user_id,
                'invoice_no' => PmInvoice::nextInvoiceNumber(),
                'issue_date' => $row['date'],
                'due_date' => $row['date'],
                'amount' => $amount,
                'amount_paid' => 0,
                'subtotal_amount' => $amount,
                'total_amount' => $amount,
                'status' => PmInvoice::STATUS_SENT,
                'sent_at' => Carbon::parse($row['date'])->startOfDay(),
                'invoice_type' => $row['type'],
                'billing_period' => $row['period'],
                'description' => $description,
                'carry_forward_origin' => [
                    'source' => 'ezen_tenant_statement_dbn',
                    'ezen_invoice_no' => $row['txn_no'],
                    'memo' => $row['memo'],
                ],
            ]);

            $invoice->ensureDefaultRentLineItem($amount, $row['memo']);

            if ($postGl) {
                PropertyAccountingPostingService::postInvoiceIssued($invoice->fresh(), $actor);
            }

            return $invoice->fresh();
        });
    }

    private function allocateLeftoverPayments(int $tenantId): float
    {
        $settlement = app(PropertyPaymentSettlementService::class);
        $total = 0.0;
        $payments = PmPayment::query()
            ->where('pm_tenant_id', $tenantId)
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->orderBy('paid_at')
            ->get();

        foreach ($payments as $payment) {
            $before = round((float) $payment->allocations()
                ->where(function ($q): void {
                    $q->whereNull('is_reversed')->orWhere('is_reversed', false);
                })
                ->sum('amount'), 2);
            $left = $settlement->allocatePaymentToOpenInvoices($payment);
            $used = round(max(0, (float) $payment->amount - $before - $left), 2);
            $total += $used;
        }

        return round($total, 2);
    }

    private function parseMoney(string $raw): ?float
    {
        $raw = html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5);
        $raw = preg_replace('/[\x{00A0}\x{202F}\x{2007}\x{2060}\s]+/u', '', $raw) ?? $raw;
        $raw = str_replace([',', '−', '–'], ['', '-', '-'], $raw);
        if ($raw === '' || $raw === '-' || strcasecmp($raw, 'n/a') === 0) {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function billingPeriodFromMemo(string $memo): ?string
    {
        static $months = [
            'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04',
            'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08',
            'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12',
            'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'jun' => '06', 'jul' => '07', 'aug' => '08', 'sep' => '09', 'sept' => '09',
            'oct' => '10', 'nov' => '11', 'dec' => '12',
        ];

        if (preg_match('/\b([A-Za-z]+)\s*\/\s*(\d{4})\b/', $memo, $m) === 1) {
            $mon = $months[strtolower($m[1])] ?? null;
            if ($mon) {
                return $m[2].'-'.$mon;
            }
        }
        if (preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s*\/?\s*(\d{4})\b/i', $memo, $m) === 1) {
            $mon = $months[strtolower($m[1])] ?? null;
            if ($mon) {
                return $m[2].'-'.$mon;
            }
        }

        return null;
    }
}
