<?php

namespace App\Services\Property;

use App\Models\PmEzenReceiptRegister;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the tenant statement ledger from invoices, payments, occupancy history,
 * and EZEN receipt-register rows that were imported but never posted as payments.
 */
final class TenantStatementLedgerService
{
    /**
     * @return array{
     *     invoices: Collection<int, PmInvoice>,
     *     payments: Collection<int, PmPayment>,
     *     registerReceipts: Collection<int, PmEzenReceiptRegister>,
     *     entries: Collection<int, array<string, mixed>>,
     *     openingBalance: float,
     *     openingArrears: float,
     *     unpostedReceiptTotal: float
     * }
     */
    public function build(PmTenant $tenant, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        $tenant->loadMissing(['leases.units.property']);
        $leases = $tenant->leases ?? collect();

        $invoices = $this->invoicesForStatement($tenant, $leases, $fromDate, $toDate);
        $openingArrears = app(CarryForwardConsolidationService::class)->tenantOpeningArrearsInDue($tenant);
        $openingArrearsAsOf = $tenant->opening_arrears_as_of
            ? Carbon::parse((string) $tenant->opening_arrears_as_of)->startOfDay()
            : null;
        $hasRentalInvoiceReplay = $invoices->contains(
            fn (PmInvoice $invoice): bool => $this->isEzenRentalInvoiceReplay($invoice)
        );
        $hasStatementImport = $invoices->contains(
            fn (PmInvoice $invoice): bool => $this->isEzenTenantStatementInvoice($invoice)
        );
        $hasEzenInvoiceHistory = $hasRentalInvoiceReplay || $hasStatementImport
            || $invoices->contains(fn (PmInvoice $invoice): bool => str_starts_with(trim((string) $invoice->description), '[EZEN INV'));

        // Residual B/F kept beside a rent-only EZEN listing replay (late fees / DBNs not imported).
        // A full tenant-statement import already has the real charge lines — do not hide them
        // and do not keep the take-on snapshot as a second debit.
        // A full rent-invoice history already is the charge list. Keep those lines
        // (a vacated tenant's Jan–Aug rent) and drop the take-on snapshot so it is
        // not a second debit. A thin replay still sits beside residual B/F, but only
        // invoices already inside the snapshot date are hidden.
        $replayReplacesOpeningArrears = $hasRentalInvoiceReplay
            && ! $hasStatementImport
            && app(CarryForwardConsolidationService::class)->tenantEzenInvoicesReplaceOpeningArrears($tenant);
        $residualBfWithEzenRentHistory = $openingArrears > 0.009
            && $hasRentalInvoiceReplay
            && ! $hasStatementImport
            && ! $replayReplacesOpeningArrears;
        if ($residualBfWithEzenRentHistory) {
            $invoices = $invoices
                ->reject(function (PmInvoice $invoice) use ($openingArrearsAsOf): bool {
                    if (! $this->isEzenRentalInvoiceReplay($invoice)) {
                        return false;
                    }
                    if ($openingArrearsAsOf === null || $invoice->issue_date === null) {
                        return true;
                    }

                    return $invoice->issue_date->copy()->startOfDay()->lt($openingArrearsAsOf);
                })
                ->values();
        }

        // Snapshot B/F already nets historical receipts — suppress register + receipt-import
        // payments only in pure snapshot mode (no EZEN invoice history).
        $snapshotBfMode = $openingArrears > 0.009 && ! $hasEzenInvoiceHistory;
        $suppressRegisterCredits = $hasEzenInvoiceHistory || $openingArrears > 0.009;

        $payments = $this->paymentsForStatement($tenant, $invoices, $fromDate, $toDate);
        if ($residualBfWithEzenRentHistory) {
            $payments = $payments
                ->reject(function (PmPayment $payment): bool {
                    return $this->isEzenRentReceiptImportPayment($payment)
                        || $this->isEzenRentalInvoiceImportPayment($payment);
                })
                ->values();
        } elseif ($snapshotBfMode) {
            $payments = $payments
                ->reject(fn (PmPayment $payment): bool => $this->isEzenRentReceiptImportPayment($payment))
                ->values();
        } elseif ($hasEzenInvoiceHistory) {
            $payments = $this->dedupeInvoiceImportsAgainstReceiptImports($payments);
        }

        // Applying wallet credit creates a second completed payment (channel
        // tenant_credit). The cash was already credited on the source payment,
        // so counting this line again leaves a fake CR after the invoice is paid.
        $payments = $payments
            ->reject(fn (PmPayment $payment): bool => $this->isWalletReallocationPayment($payment))
            ->values();

        $registerReceipts = $suppressRegisterCredits
            ? collect()
            : $this->unpostedReceiptsForStatement($tenant, $leases, $payments, $fromDate, $toDate);

        $openingInvoices = 0.0;
        $openingPayments = 0.0;
        $openingRegister = 0.0;

        if ($fromDate) {
            $openingInvoices = (float) $this->invoiceBaseQuery($tenant, $leases)
                ->whereDate('issue_date', '<', $fromDate->toDateString())
                ->sum('amount');

            $openingPaymentIds = $this->paymentBaseQuery($tenant, collect())
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->whereDate('paid_at', '<', $fromDate->toDateString())
                ->pluck('id');
            $priorInvoices = $this->invoiceBaseQuery($tenant, $leases)
                ->whereDate('issue_date', '<', $fromDate->toDateString())
                ->pluck('id');
            $openingPaymentIds = $openingPaymentIds
                ->merge(
                    $this->paymentsAllocatedToInvoices($priorInvoices)
                        ->where('status', PmPayment::STATUS_COMPLETED)
                        ->whereDate('paid_at', '<', $fromDate->toDateString())
                        ->pluck('id')
                )
                ->unique()
                ->values();

            $openingPaymentsQuery = PmPayment::query()
                ->whereIn('id', $openingPaymentIds)
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->where(function ($query): void {
                    $query->whereNull('channel')
                        ->orWhere('channel', '!=', 'tenant_credit');
                });
            if ($snapshotBfMode) {
                $openingPaymentsQuery->where(function ($query): void {
                    $query->whereNull('meta->source')
                        ->orWhere('meta->source', '!=', 'ezen_rent_receipt_import');
                });
            }
            $openingPayments = (float) $openingPaymentsQuery->sum('amount');

            if (! $suppressRegisterCredits) {
                $registerOpeningQuery = $this->receiptRegisterBaseQuery($tenant, $leases);
                if ($registerOpeningQuery !== null) {
                    $openingRegister = (float) $registerOpeningQuery
                        ->where(function ($query) use ($openingPaymentIds): void {
                            $query->whereNull('pm_payment_id')
                                ->orWhereNotIn('pm_payment_id', $openingPaymentIds->all() ?: [0]);
                        })
                        ->whereDate('txn_date', '<', $fromDate->toDateString())
                        ->sum('amount');
                }
            }

            if ($openingArrears > 0 && ($openingArrearsAsOf === null || $openingArrearsAsOf->lt($fromDate))) {
                $openingInvoices += $openingArrears;
            }
        }

        $openingBalance = $openingInvoices - $openingPayments - $openingRegister;

        $entries = collect();

        foreach ($invoices as $invoice) {
            $label = $invoice->invoice_no ?: 'INV-'.$invoice->id;
            $unitLabel = trim(($invoice->unit?->property?->name ?? '—').' / '.($invoice->unit?->label ?? '—'));
            $typeLabel = $invoice->invoice_type
                ? strtoupper((string) $invoice->invoice_type)
                : 'CHARGE';
            if ((string) $invoice->invoice_type === PmInvoice::TYPE_LATE_PAYMENT) {
                $typeLabel = 'LATE PAYMENT';
            }
            $memo = trim((string) ($invoice->description ?? ''));
            if (preg_match('/^\[EZEN [^\]]+\]\s*(.+?)(?:\s*·\s*|$)/u', $memo, $m) === 1) {
                $memo = trim($m[1]);
            }
            $desc = $typeLabel;
            if ($memo !== '') {
                $desc .= ' · '.$memo;
            }
            if ($unitLabel !== '— / —') {
                $desc .= ' · '.$unitLabel;
            }

            $allocated = $invoice->allocatedAmount();
            $openBalance = max(0.0, round((float) $invoice->amount - $allocated, 2));
            $entries->push([
                'date' => $invoice->issue_date?->toDateString(),
                'timestamp' => $invoice->issue_date?->startOfDay()?->timestamp ?? 0,
                'type' => 'Invoice',
                'ref' => $label,
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => $label,
                'charge_label' => $memo !== '' ? $memo : $typeLabel,
                'is_rent' => (string) $invoice->invoice_type === PmInvoice::TYPE_RENT,
                'description' => $desc,
                'debit' => (float) $invoice->amount,
                'credit' => 0.0,
                'payment_id' => null,
                'status' => match (true) {
                    (string) $invoice->status === PmInvoice::STATUS_CANCELLED => 'Cancelled',
                    $openBalance > 0.009 && $allocated > 0.009 => 'Partial',
                    $openBalance > 0.009 => 'Issued',
                    default => 'Paid',
                },
            ]);
        }

        if ($openingArrears > 0 && ! $hasStatementImport && ! $replayReplacesOpeningArrears) {
            $entryDate = $openingArrearsAsOf?->toDateString() ?? $tenant->created_at?->toDateString() ?? now()->toDateString();
            $entryTs = $openingArrearsAsOf?->timestamp ?? ($tenant->created_at?->timestamp ?? now()->timestamp);
            $inRange = (! $fromDate || $entryTs >= $fromDate->timestamp) && (! $toDate || $entryTs <= $toDate->timestamp);
            if ($inRange) {
                $items = collect((array) ($tenant->opening_arrears_items ?? []))
                    ->filter(fn ($item): bool => is_array($item) && (float) ($item['amount'] ?? 0) > 0)
                    ->map(function (array $item): string {
                        $customLabel = trim((string) ($item['label'] ?? ''));
                        $label = $customLabel !== ''
                            ? $customLabel
                            : ucfirst(str_replace('_', ' ', (string) ($item['type'] ?? 'Other')));
                        $period = (string) ($item['period'] ?? '');
                        $ref = trim((string) ($item['reference'] ?? ''));
                        $bits = [$label, $period !== '' ? "({$period})" : null, PropertyMoney::kes((float) ($item['amount'] ?? 0))];
                        if ($ref !== '') {
                            $bits[] = '['.$ref.']';
                        }

                        return implode(' ', array_values(array_filter($bits, fn ($v): bool => (string) $v !== '')));
                    });
                $partsText = $items->isEmpty() ? '' : ' Breakdown: '.$items->implode(' · ');
                $entries->push([
                    'date' => $entryDate,
                    'timestamp' => $entryTs,
                    'type' => 'Opening arrears',
                    'ref' => 'B/F-'.$tenant->id,
                    'invoice_id' => 0,
                    'invoice_no' => 'B/F-'.$tenant->id,
                    'charge_label' => 'Opening arrears',
                    'is_rent' => false,
                    'description' => trim((string) (($tenant->opening_arrears_notes ?: 'Brought-forward debt captured at tenant onboarding.').$partsText)),
                    'debit' => $openingArrears,
                    'credit' => 0.0,
                    'payment_id' => null,
                    'status' => '—',
                ]);
            }
        }

        foreach ($payments as $payment) {
            $label = $payment->external_ref ?: 'PAY-'.$payment->id;
            $allocTo = $payment->allocations->pluck('invoice.invoice_no')->filter()->implode(', ');
            $desc = strtoupper((string) $payment->channel);
            if ($allocTo !== '') {
                $desc .= ' · Alloc: '.$allocTo;
            }
            $desc .= ' · '.ucfirst((string) $payment->status);

            $isCompleted = $payment->status === PmPayment::STATUS_COMPLETED;
            $credit = $isCompleted ? (float) $payment->amount : 0.0;

            $entries->push([
                'date' => $payment->paid_at?->toDateString(),
                'timestamp' => $payment->paid_at?->timestamp ?? 0,
                'type' => 'Payment',
                'ref' => $label,
                'description' => $desc,
                'debit' => 0.0,
                'credit' => $credit,
                'payment_id' => $isCompleted ? $payment->id : null,
                'status' => ucfirst((string) $payment->status),
            ]);
        }

        $unpostedReceiptTotal = 0.0;
        foreach ($registerReceipts as $receipt) {
            $amount = (float) $receipt->amount;
            $unpostedReceiptTotal += $amount;
            $txnDate = $receipt->txn_date ?? $receipt->banking_date;
            $method = $receipt->displayPaymentMethod();
            $unitBit = trim((string) ($receipt->property_code ?? '').' / '.(string) ($receipt->unit_label ?? ''));
            $desc = 'EZEN receipt';
            if ($method !== '—') {
                $desc .= ' · '.$method;
            }
            if ($unitBit !== '/') {
                $desc .= ' · '.$unitBit;
            }
            $particulars = trim((string) ($receipt->particulars ?? ''));
            if ($particulars !== '') {
                $desc .= ' · '.$particulars;
            }

            $entries->push([
                'date' => $txnDate?->toDateString(),
                'timestamp' => $txnDate?->startOfDay()?->timestamp ?? 0,
                'type' => 'Receipt',
                'ref' => $receipt->ezen_receipt_no ?: ($receipt->ref_no ?: 'EZEN-'.$receipt->id),
                'description' => $desc,
                'debit' => 0.0,
                'credit' => $amount,
                'payment_id' => $receipt->pm_payment_id ? (int) $receipt->pm_payment_id : null,
                'status' => 'Imported',
            ]);
        }

        return [
            'invoices' => $invoices,
            'payments' => $payments,
            'registerReceipts' => $registerReceipts,
            'entries' => $entries
                ->sortBy([
                    ['timestamp', 'asc'],
                    ['type', 'asc'],
                    ['ref', 'asc'],
                ])
                ->values(),
            'openingBalance' => $openingBalance,
            'openingArrears' => $openingArrears,
            'unpostedReceiptTotal' => round($unpostedReceiptTotal, 2),
        ];
    }

    /**
     * Split each statement receipt across the charges it settles.
     * Older open invoices are paid first. Money left over settles later invoices.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<int, list<array{invoice_no:string, label:string, amount:float, is_rent:bool}>>
     */
    public function applicationsByPayment(Collection $entries): array
    {
        /** @var list<array{invoice_no:string, label:string, remaining:float, is_rent:bool}> $open */
        $open = [];
        /** @var list<array{payment_id:?int, remaining:float}> $pools */
        $pools = [];
        /** @var array<int, list<array{invoice_no:string, label:string, amount:float, is_rent:bool}>> $applied */
        $applied = [];

        $add = function (?int $paymentId, string $invoiceNo, string $label, bool $isRent, float $amount) use (&$applied): void {
            if ($paymentId === null || $paymentId <= 0 || $amount <= 0.009) {
                return;
            }
            $rows = $applied[$paymentId] ?? [];
            $last = array_key_last($rows);
            if ($last !== null && $rows[$last]['invoice_no'] === $invoiceNo) {
                $rows[$last]['amount'] = round($rows[$last]['amount'] + $amount, 2);
                $applied[$paymentId] = $rows;

                return;
            }
            $rows[] = [
                'invoice_no' => $invoiceNo,
                'label' => $label,
                'amount' => round($amount, 2),
                'is_rent' => $isRent,
            ];
            $applied[$paymentId] = $rows;
        };

        foreach ($entries as $entry) {
            $debit = round((float) ($entry['debit'] ?? 0), 2);
            $credit = round((float) ($entry['credit'] ?? 0), 2);
            $type = (string) ($entry['type'] ?? '');

            if ($debit > 0.009 && in_array($type, ['Invoice', 'Opening arrears'], true)) {
                $invoiceNo = (string) ($entry['invoice_no'] ?? $entry['ref'] ?? 'Charge');
                $label = (string) ($entry['charge_label'] ?? 'Charge');
                $isRent = (bool) ($entry['is_rent'] ?? false);
                $need = $debit;
                foreach ($pools as $index => $pool) {
                    if ($need <= 0.009) {
                        break;
                    }
                    $available = round((float) $pool['remaining'], 2);
                    if ($available <= 0.009) {
                        continue;
                    }
                    $take = round(min($available, $need), 2);
                    $pools[$index]['remaining'] = round($available - $take, 2);
                    $need = round($need - $take, 2);
                    $add($pool['payment_id'], $invoiceNo, $label, $isRent, $take);
                }
                if ($need > 0.009) {
                    $open[] = [
                        'invoice_no' => $invoiceNo,
                        'label' => $label,
                        'remaining' => $need,
                        'is_rent' => $isRent,
                    ];
                }

                continue;
            }

            if ($credit <= 0.009 || ! in_array($type, ['Payment', 'Receipt'], true)) {
                continue;
            }

            $paymentId = $type === 'Payment' ? (int) ($entry['payment_id'] ?? 0) : 0;
            $remaining = $credit;
            foreach ($open as $index => $charge) {
                if ($remaining <= 0.009) {
                    break;
                }
                $due = round((float) $charge['remaining'], 2);
                if ($due <= 0.009) {
                    continue;
                }
                $take = round(min($remaining, $due), 2);
                $open[$index]['remaining'] = round($due - $take, 2);
                $remaining = round($remaining - $take, 2);
                $add($paymentId > 0 ? $paymentId : null, (string) $charge['invoice_no'], (string) $charge['label'], (bool) $charge['is_rent'], $take);
            }
            if ($remaining > 0.009) {
                $pools[] = [
                    'payment_id' => $paymentId > 0 ? $paymentId : null,
                    'remaining' => $remaining,
                ];
            }
        }

        return $applied;
    }

    /**
     * Same figure as the tenant statement closing balance with no date filter.
     * Positive is debt. Negative is extra money on the account.
     */
    public function closingBalance(PmTenant $tenant): float
    {
        $ledger = $this->build($tenant, null, null);
        $balance = (float) ($ledger['openingBalance'] ?? 0);
        foreach ($ledger['entries'] as $entry) {
            $balance += (float) ($entry['debit'] ?? 0) - (float) ($entry['credit'] ?? 0);
        }

        return round($balance, 2);
    }

    /**
     * @param  Collection<int, \App\Models\PmLease>  $leases
     * @return \Illuminate\Database\Eloquent\Builder<PmInvoice>
     */
    private function invoiceBaseQuery(PmTenant $tenant, Collection $leases)
    {
        return PmInvoice::query()
            ->billableAr()
            ->where(function ($query) use ($tenant, $leases): void {
                $query->where('pm_tenant_id', $tenant->id);
                foreach ($leases as $lease) {
                    $unitIds = $lease->units->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
                    if ($unitIds === []) {
                        continue;
                    }
                    // Same unit can be re-let after a tenant vacates. Only pick up
                    // invoices that are still hers (or unassigned), not the next occupant's.
                    $query->orWhere(function ($inner) use ($unitIds, $lease, $tenant): void {
                        $inner->whereIn('property_unit_id', $unitIds)
                            ->where(function ($owner) use ($tenant): void {
                                $owner->where('pm_tenant_id', $tenant->id)
                                    ->orWhereNull('pm_tenant_id');
                            });
                        if ($lease->start_date) {
                            $inner->whereDate('issue_date', '>=', $lease->start_date->toDateString());
                        }
                        if ($lease->end_date) {
                            $inner->whereDate('issue_date', '<=', $lease->end_date->toDateString());
                        }
                    });
                }
            });
    }

    /**
     * @param  Collection<int, \App\Models\PmLease>  $leases
     * @return Collection<int, PmInvoice>
     */
    private function invoicesForStatement(PmTenant $tenant, Collection $leases, ?Carbon $fromDate, ?Carbon $toDate): Collection
    {
        return $this->invoiceBaseQuery($tenant, $leases)
            ->with(['unit.property'])
            ->when($fromDate, fn ($q) => $q->whereDate('issue_date', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('issue_date', '<=', $toDate->toDateString()))
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, PmInvoice>  $invoices
     * @return \Illuminate\Database\Eloquent\Builder<PmPayment>
     */
    private function paymentBaseQuery(PmTenant $tenant, Collection $invoices)
    {
        $invoiceIds = $invoices->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();

        return PmPayment::query()
            ->with(['allocations.invoice'])
            ->where(function ($query) use ($tenant, $invoiceIds): void {
                $query->where('pm_tenant_id', $tenant->id);
                if ($invoiceIds !== []) {
                    $query->orWhereHas('allocations', fn ($alloc) => $alloc->whereIn('pm_invoice_id', $invoiceIds));
                }
            });
    }

    /**
     * @param  Collection<int, int>  $invoiceIds
     * @return \Illuminate\Database\Eloquent\Builder<PmPayment>
     */
    private function paymentsAllocatedToInvoices(Collection $invoiceIds)
    {
        $ids = $invoiceIds->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($ids === []) {
            return PmPayment::query()->whereRaw('1 = 0');
        }

        return PmPayment::query()->whereHas('allocations', fn ($alloc) => $alloc->whereIn('pm_invoice_id', $ids));
    }

    /**
     * @param  Collection<int, PmInvoice>  $invoices
     * @return Collection<int, PmPayment>
     */
    private function paymentsForStatement(PmTenant $tenant, Collection $invoices, ?Carbon $fromDate, ?Carbon $toDate): Collection
    {
        return $this->paymentBaseQuery($tenant, $invoices)
            ->when($fromDate, fn ($q) => $q->whereDate('paid_at', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('paid_at', '<=', $toDate->toDateString()))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, \App\Models\PmLease>  $leases
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|null
     */
    private function receiptRegisterBaseQuery(PmTenant $tenant, Collection $leases)
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            return null;
        }

        $accountCandidates = $this->tntAccountCandidates((string) ($tenant->account_number ?? ''));
        $phoneDigits = $this->phoneDigits((string) ($tenant->phone ?? ''));
        $unitPairs = [];
        foreach ($leases as $lease) {
            foreach ($lease->units as $unit) {
                $code = strtoupper(trim((string) ($unit->property?->code ?? '')));
                $label = strtoupper(trim((string) ($unit->label ?? '')));
                if ($code === '' || $label === '') {
                    continue;
                }
                $unitPairs[] = [
                    'code' => $code,
                    'label' => $label,
                    'start' => $lease->start_date?->toDateString(),
                    'end' => $lease->end_date?->toDateString(),
                ];
            }
        }

        return PmEzenReceiptRegister::query()
            ->where(function ($query) use ($tenant, $accountCandidates, $phoneDigits, $unitPairs): void {
                $query->where('pm_tenant_id', $tenant->id);
                if ($accountCandidates !== []) {
                    $query->orWhereIn('tnt_account', $accountCandidates);
                }
                if ($phoneDigits !== '') {
                    $query->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(COALESCE(phone,''), ' ', ''), '-', ''), '+', '') LIKE ?",
                        ['%'.$phoneDigits]
                    );
                }
                foreach ($unitPairs as $pair) {
                    $query->orWhere(function ($inner) use ($pair): void {
                        $inner->whereRaw('UPPER(TRIM(property_code)) = ?', [$pair['code']])
                            ->whereRaw('UPPER(TRIM(unit_label)) = ?', [$pair['label']]);
                        if ($pair['start']) {
                            $inner->whereDate('txn_date', '>=', $pair['start']);
                        }
                        if ($pair['end']) {
                            $inner->whereDate('txn_date', '<=', $pair['end']);
                        }
                    });
                }
            });
    }

    /**
     * @param  Collection<int, \App\Models\PmLease>  $leases
     * @param  Collection<int, PmPayment>  $payments
     * @return Collection<int, PmEzenReceiptRegister>
     */
    private function unpostedReceiptsForStatement(
        PmTenant $tenant,
        Collection $leases,
        Collection $payments,
        ?Carbon $fromDate,
        ?Carbon $toDate,
    ): Collection {
        $query = $this->receiptRegisterBaseQuery($tenant, $leases);
        if ($query === null) {
            return collect();
        }

        $paymentIds = $payments->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $paymentRefs = $payments
            ->pluck('external_ref')
            ->filter()
            ->map(fn ($ref) => strtoupper(trim((string) $ref)))
            ->filter()
            ->values()
            ->all();

        $receipts = $query
            ->when($fromDate, function ($q) use ($fromDate): void {
                $q->where(function ($inner) use ($fromDate): void {
                    $inner->whereDate('txn_date', '>=', $fromDate->toDateString())
                        ->orWhereDate('banking_date', '>=', $fromDate->toDateString());
                });
            })
            ->when($toDate, function ($q) use ($toDate): void {
                $q->where(function ($inner) use ($toDate): void {
                    $inner->whereDate('txn_date', '<=', $toDate->toDateString())
                        ->orWhereDate('banking_date', '<=', $toDate->toDateString());
                });
            })
            ->orderBy('txn_date')
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();

        return $receipts->filter(function (PmEzenReceiptRegister $receipt) use ($paymentIds, $paymentRefs): bool {
            if ((int) ($receipt->pm_payment_id ?? 0) > 0 && in_array((int) $receipt->pm_payment_id, $paymentIds, true)) {
                return false;
            }
            $receiptNo = strtoupper(trim((string) ($receipt->ezen_receipt_no ?? '')));
            $refNo = strtoupper(trim((string) ($receipt->ref_no ?? '')));
            if ($receiptNo !== '' && in_array($receiptNo, $paymentRefs, true)) {
                return false;
            }
            if ($refNo !== '' && $refNo !== 'CASH' && in_array($refNo, $paymentRefs, true)) {
                return false;
            }

            return (float) $receipt->amount > 0.009;
        })->values();
    }

    /**
     * @return list<string>
     */
    private function tntAccountCandidates(string $account): array
    {
        $account = strtoupper(trim($account));
        if ($account === '') {
            return [];
        }
        $candidates = [$account];
        if (preg_match('/^TNT0*(\d+)$/', $account, $match) === 1) {
            $number = (int) $match[1];
            $candidates[] = 'TNT'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $candidates[] = 'TNT'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            $candidates[] = 'TNT'.$number;
        }

        return array_values(array_unique($candidates));
    }

    private function phoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) >= 9) {
            return substr($digits, -9);
        }

        return $digits;
    }

    private function isWalletReallocationPayment(PmPayment $payment): bool
    {
        return (string) $payment->channel === 'tenant_credit';
    }

    private function isEzenRentReceiptImportPayment(PmPayment $payment): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];

        return ($meta['source'] ?? '') === 'ezen_rent_receipt_import';
    }

    private function isEzenRentalInvoiceImportPayment(PmPayment $payment): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];

        return ($meta['source'] ?? '') === 'ezen_rental_invoice_import';
    }

    private function isEzenRentalInvoiceReplay(PmInvoice $invoice): bool
    {
        $origin = $invoice->carry_forward_origin;

        return is_array($origin) && ($origin['source'] ?? '') === 'ezen_rental_invoice_import';
    }

    private function isEzenTenantStatementInvoice(PmInvoice $invoice): bool
    {
        $origin = $invoice->carry_forward_origin;
        $source = is_array($origin) ? (string) ($origin['source'] ?? '') : '';
        if ($source === 'ezen_tenant_statement_dbn') {
            return true;
        }
        if ($source === 'ezen_rental_invoice_import') {
            return false;
        }

        $description = trim((string) $invoice->description);

        return str_starts_with($description, '[EZEN ');
    }

    /**
     * @param  Collection<int, PmPayment>  $payments
     * @return Collection<int, PmPayment>
     */
    private function dedupeInvoiceImportsAgainstReceiptImports(Collection $payments): Collection
    {
        $receiptRefs = $payments
            ->filter(fn (PmPayment $payment): bool => $this->isEzenRentReceiptImportPayment($payment))
            ->flatMap(fn (PmPayment $payment): array => $this->paymentRefKeys($payment))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($receiptRefs === []) {
            return $payments->values();
        }

        $refSet = array_fill_keys($receiptRefs, true);

        return $payments
            ->reject(function (PmPayment $payment) use ($refSet): bool {
                if (! $this->isEzenRentalInvoiceImportPayment($payment)) {
                    return false;
                }
                foreach ($this->paymentRefKeys($payment) as $key) {
                    if ($key !== '' && isset($refSet[$key])) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @return list<string>
     */
    private function paymentRefKeys(PmPayment $payment): array
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $keys = [
            strtoupper(trim((string) ($payment->external_ref ?? ''))),
            strtoupper(trim((string) ($meta['mpesa_ref'] ?? ''))),
            strtoupper(trim((string) ($meta['ezen_ref_no'] ?? ''))),
        ];
        $receiptNo = strtoupper(trim((string) ($meta['ezen_receipt_no'] ?? '')));
        if ($receiptNo !== '') {
            $keys[] = $receiptNo;
            $keys[] = 'EZEN-'.$receiptNo;
        }

        return array_values(array_unique(array_filter($keys, fn (string $k): bool => $k !== '' && $k !== 'CASH')));
    }
}
