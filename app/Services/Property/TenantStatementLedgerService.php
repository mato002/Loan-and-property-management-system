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
        $hasEzenInvoiceHistory = $invoices->contains(function (PmInvoice $invoice): bool {
            $origin = $invoice->carry_forward_origin;
            if (is_array($origin) && ($origin['source'] ?? '') === 'ezen_rental_invoice_import') {
                return true;
            }

            return str_starts_with(trim((string) $invoice->description), '[EZEN INV');
        });
        // Snapshot B/F and EZEN receipt credits are mutually exclusive: the B/F already nets
        // historical receipts. Full EZEN invoice replay retires B/F and owns the receipt trail.
        $suppressEzenReceiptCredits = $hasEzenInvoiceHistory || $openingArrears > 0.009;

        $payments = $this->paymentsForStatement($tenant, $invoices, $fromDate, $toDate);
        if ($suppressEzenReceiptCredits) {
            $payments = $payments
                ->reject(fn (PmPayment $payment): bool => $this->isEzenRentReceiptImportPayment($payment))
                ->values();
        }

        $registerReceipts = $suppressEzenReceiptCredits
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
                ->where('status', PmPayment::STATUS_COMPLETED);
            if ($suppressEzenReceiptCredits) {
                $openingPaymentsQuery->where(function ($query): void {
                    $query->whereNull('meta->source')
                        ->orWhere('meta->source', '!=', 'ezen_rent_receipt_import');
                });
            }
            $openingPayments = (float) $openingPaymentsQuery->sum('amount');

            if (! $suppressEzenReceiptCredits) {
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

            $entries->push([
                'date' => $invoice->issue_date?->toDateString(),
                'timestamp' => $invoice->issue_date?->startOfDay()?->timestamp ?? 0,
                'type' => 'Invoice',
                'ref' => $label,
                'description' => ($invoice->invoice_type ? strtoupper((string) $invoice->invoice_type) : 'CHARGE').($unitLabel !== '— / —' ? ' · '.$unitLabel : ''),
                'debit' => (float) $invoice->amount,
                'credit' => 0.0,
                'payment_id' => null,
                'status' => 'Issued',
            ]);
        }

        if ($openingArrears > 0) {
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
                ])
                ->values(),
            'openingBalance' => $openingBalance,
            'openingArrears' => $openingArrears,
            'unpostedReceiptTotal' => round($unpostedReceiptTotal, 2),
        ];
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
                    $query->orWhere(function ($inner) use ($unitIds, $lease): void {
                        $inner->whereIn('property_unit_id', $unitIds);
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

    private function isEzenRentReceiptImportPayment(PmPayment $payment): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];

        return ($meta['source'] ?? '') === 'ezen_rent_receipt_import';
    }
}
