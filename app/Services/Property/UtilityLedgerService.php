<?php

namespace App\Services\Property;

use App\Models\AccountingJournalBatch;
use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\PmTenantCreditTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UtilityLedgerService
{
    public const ENTRY_INVOICE = 'water_invoice';

    public const ENTRY_CREDIT_NOTE = 'credit_note';

    public const ENTRY_PENALTY = 'penalty';

    public const ENTRY_PENALTY_REVERSAL = 'penalty_reversal';

    public const ENTRY_PAYMENT = 'payment';

    public const ENTRY_PAYMENT_REVERSAL = 'payment_reversal';

    public const ENTRY_INVOICE_CANCELLED = 'invoice_cancelled';

    /**
     * @return array{
     *     opening_balance: float,
     *     closing_balance: float,
     *     total_debit: float,
     *     total_credit: float,
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function buildTenantLedger(int $tenantId, ?string $from = null, ?string $to = null): array
    {
        $fromDate = $from !== null && $from !== '' ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to !== null && $to !== '' ? Carbon::parse($to)->endOfDay() : null;

        $rawEntries = $this->collectTenantEntries($tenantId);

        $openingBalance = 0.0;
        if ($fromDate) {
            $openingBalance = $this->sumEntriesBefore($rawEntries, $fromDate);
        }

        $filtered = $rawEntries->filter(function (array $entry) use ($fromDate, $toDate): bool {
            $at = Carbon::createFromTimestamp((int) $entry['timestamp']);

            if ($fromDate && $at->lt($fromDate)) {
                return false;
            }
            if ($toDate && $at->gt($toDate)) {
                return false;
            }

            return true;
        })->sortBy([
            ['timestamp', 'asc'],
            ['sort_key', 'asc'],
        ])->values();

        $running = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $rows = [];

        if ($fromDate) {
            $rows[] = $this->formatRow([
                'occurred_at' => $fromDate->toDateString(),
                'entry_type' => 'opening_balance',
                'type_label' => 'Opening balance',
                'reference' => 'B/F',
                'description' => 'Balance brought forward',
                'debit' => 0.0,
                'credit' => 0.0,
                'balance_after' => $openingBalance,
                'drilldown' => [],
            ], false);
        }

        foreach ($filtered as $entry) {
            $debit = (float) $entry['debit'];
            $credit = (float) $entry['credit'];
            $running = round($running + $debit - $credit, 2);
            $totalDebit += $debit;
            $totalCredit += $credit;

            $entry['balance_after'] = $running;
            $rows[] = $this->formatRow($entry, true);
        }

        return [
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($running, 2),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectTenantEntries(int $tenantId): Collection
    {
        $entries = collect();

        $invoices = PmInvoice::query()
            ->with('unit.property')
            ->where('pm_tenant_id', $tenantId)
            ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
            ->where('status', '!=', PmInvoice::STATUS_DRAFT)
            ->get();

        foreach ($invoices as $invoice) {
            $isCreditNote = (string) ($invoice->invoice_kind ?? PmInvoice::KIND_INVOICE) === PmInvoice::KIND_CREDIT_NOTE;
            $issueTs = $invoice->issue_date?->startOfDay()->timestamp ?? ($invoice->created_at?->timestamp ?? 0);
            $amount = (float) $invoice->amount;
            $journalBatchId = $this->journalBatchId('pm_invoice', (int) $invoice->id, 'invoice_issued');

            if ($isCreditNote) {
                $entries->push([
                    'timestamp' => $issueTs,
                    'sort_key' => 'c'.$invoice->id,
                    'occurred_at' => $invoice->issue_date?->toDateString() ?? $invoice->created_at?->toDateString(),
                    'entry_type' => self::ENTRY_CREDIT_NOTE,
                    'type_label' => 'Credit note',
                    'reference' => (string) ($invoice->invoice_no ?: 'CN-'.$invoice->id),
                    'description' => $this->invoiceDescription($invoice),
                    'debit' => 0.0,
                    'credit' => $amount,
                    'drilldown' => [
                        'pm_invoice_id' => (int) $invoice->id,
                        'journal_batch_id' => $journalBatchId,
                    ],
                ]);
            } else {
                $entries->push([
                    'timestamp' => $issueTs,
                    'sort_key' => 'i'.$invoice->id,
                    'occurred_at' => $invoice->issue_date?->toDateString() ?? $invoice->created_at?->toDateString(),
                    'entry_type' => self::ENTRY_INVOICE,
                    'type_label' => ucfirst((string) $invoice->invoice_type).' invoice',
                    'reference' => (string) ($invoice->invoice_no ?: 'INV-'.$invoice->id),
                    'description' => $this->invoiceDescription($invoice),
                    'debit' => $amount,
                    'credit' => 0.0,
                    'drilldown' => [
                        'pm_invoice_id' => (int) $invoice->id,
                        'journal_batch_id' => $journalBatchId,
                    ],
                ]);
            }

            if ((string) $invoice->status === PmInvoice::STATUS_CANCELLED && $invoice->cancelled_at) {
                $cancelTs = $invoice->cancelled_at->timestamp;
                $outstanding = max(0.0, $amount - (float) $invoice->amount_paid);
                if ($outstanding > 0) {
                    $entries->push([
                        'timestamp' => $cancelTs,
                        'sort_key' => 'x'.$invoice->id,
                        'occurred_at' => $invoice->cancelled_at->toDateString(),
                        'entry_type' => self::ENTRY_INVOICE_CANCELLED,
                        'type_label' => 'Invoice cancelled',
                        'reference' => (string) ($invoice->invoice_no ?: 'INV-'.$invoice->id),
                        'description' => trim('Cancellation · '.((string) ($invoice->cancelled_reason ?: 'Invoice voided'))),
                        'debit' => 0.0,
                        'credit' => $outstanding,
                        'drilldown' => [
                            'pm_invoice_id' => (int) $invoice->id,
                            'journal_batch_id' => $this->journalBatchId('pm_invoice', (int) $invoice->id, 'invoice_issued_reversal'),
                        ],
                    ]);
                }
            }
        }

        $penalties = PmInvoicePenaltyApplication::query()
            ->with(['invoice.unit.property', 'rule'])
            ->whereHas('invoice', fn ($q) => $q
                ->where('pm_tenant_id', $tenantId)
                ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED]))
            ->get();

        foreach ($penalties as $penalty) {
            $appliedTs = ($penalty->applied_at ?? $penalty->created_at)?->timestamp ?? 0;
            $entries->push([
                'timestamp' => $appliedTs,
                'sort_key' => 'p'.$penalty->id,
                'occurred_at' => ($penalty->applied_at ?? $penalty->created_at)?->toDateString(),
                'entry_type' => self::ENTRY_PENALTY,
                'type_label' => 'Penalty',
                'reference' => ($penalty->invoice?->invoice_no ?: 'INV-'.$penalty->pm_invoice_id).'-PEN',
                'description' => trim('Late fee · '.($penalty->rule?->name ?? 'Water penalty')),
                'debit' => (float) $penalty->amount,
                'credit' => 0.0,
                'drilldown' => [
                    'pm_invoice_id' => (int) $penalty->pm_invoice_id,
                    'pm_invoice_penalty_application_id' => (int) $penalty->id,
                    'journal_batch_id' => $this->journalBatchId('pm_invoice_penalty_application', (int) $penalty->id, 'water_penalty_applied'),
                ],
            ]);

            if ($penalty->reversed_at) {
                $entries->push([
                    'timestamp' => $penalty->reversed_at->timestamp,
                    'sort_key' => 'pr'.$penalty->id,
                    'occurred_at' => $penalty->reversed_at->toDateString(),
                    'entry_type' => self::ENTRY_PENALTY_REVERSAL,
                    'type_label' => 'Penalty reversed',
                    'reference' => ($penalty->invoice?->invoice_no ?: 'INV-'.$penalty->pm_invoice_id).'-PEN-REV',
                    'description' => trim('Penalty reversal · '.((string) ($penalty->reversal_reason ?: 'Reversed'))),
                    'debit' => 0.0,
                    'credit' => (float) $penalty->amount,
                    'drilldown' => [
                        'pm_invoice_id' => (int) $penalty->pm_invoice_id,
                        'pm_invoice_penalty_application_id' => (int) $penalty->id,
                        'journal_batch_id' => $this->journalBatchId('pm_invoice_penalty_application', (int) $penalty->id, 'water_penalty_reversal'),
                    ],
                ]);
            }
        }

        $allocations = PmPaymentAllocation::query()
            ->with(['payment', 'invoice.unit.property'])
            ->whereHas('invoice', fn ($q) => $q
                ->where('pm_tenant_id', $tenantId)
                ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED]))
            ->whereHas('payment', fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->get();

        $creditTxnByPayment = PmTenantCreditTransaction::query()
            ->where('pm_tenant_id', $tenantId)
            ->where('type', PmTenantCreditTransaction::TYPE_CREDIT_APPLIED)
            ->whereNotNull('pm_payment_id')
            ->get()
            ->keyBy('pm_payment_id');

        foreach ($allocations as $allocation) {
            $payment = $allocation->payment;
            if (! $payment) {
                continue;
            }

            $amount = (float) $allocation->amount;
            if ($amount <= 0) {
                continue;
            }

            $paidTs = ($payment->paid_at ?? $payment->created_at)?->timestamp ?? 0;
            $channel = (string) ($payment->channel ?? 'payment');
            $creditTxn = $creditTxnByPayment->get((int) $payment->id);
            $journalBatchId = $this->journalBatchId('pm_payment', (int) $payment->id, 'payment_received');
            if ($channel === 'tenant_credit' && $creditTxn) {
                $journalBatchId = $this->journalBatchId('pm_tenant_credit_transaction', (int) $creditTxn->id, 'tenant_credit_applied') ?? $journalBatchId;
            }

            if (! $allocation->is_reversed) {
                $entries->push([
                    'timestamp' => $paidTs,
                    'sort_key' => 'a'.$allocation->id,
                    'occurred_at' => ($payment->paid_at ?? $payment->created_at)?->toDateString(),
                    'entry_type' => self::ENTRY_PAYMENT,
                    'type_label' => $channel === 'tenant_credit' ? 'Credit applied' : 'Payment',
                    'reference' => (string) ($payment->external_ref ?: 'PAY-'.$payment->id),
                    'description' => $this->allocationDescription($allocation, $payment),
                    'debit' => 0.0,
                    'credit' => $amount,
                    'drilldown' => [
                        'pm_invoice_id' => (int) $allocation->pm_invoice_id,
                        'pm_payment_id' => (int) $payment->id,
                        'pm_payment_allocation_id' => (int) $allocation->id,
                        'pm_tenant_credit_transaction_id' => $creditTxn?->id,
                        'journal_batch_id' => $journalBatchId,
                    ],
                ]);
            } else {
                $reverseTs = ($allocation->reversed_at ?? $payment->updated_at ?? $payment->created_at)?->timestamp ?? $paidTs;
                $entries->push([
                    'timestamp' => $reverseTs,
                    'sort_key' => 'ar'.$allocation->id,
                    'occurred_at' => ($allocation->reversed_at ?? $payment->updated_at)?->toDateString(),
                    'entry_type' => self::ENTRY_PAYMENT_REVERSAL,
                    'type_label' => 'Payment reversed',
                    'reference' => (string) ($payment->external_ref ?: 'PAY-'.$payment->id),
                    'description' => trim('Allocation reversal · '.((string) ($allocation->reversal_reason ?: 'Payment reversed'))),
                    'debit' => $amount,
                    'credit' => 0.0,
                    'drilldown' => [
                        'pm_invoice_id' => (int) $allocation->pm_invoice_id,
                        'pm_payment_id' => (int) $payment->id,
                        'pm_payment_allocation_id' => (int) $allocation->id,
                        'journal_batch_id' => $journalBatchId,
                    ],
                ]);
            }
        }

        return $entries;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function sumEntriesBefore(Collection $entries, Carbon $before): float
    {
        $balance = 0.0;
        foreach ($entries as $entry) {
            $at = Carbon::createFromTimestamp((int) $entry['timestamp']);
            if ($at->lt($before)) {
                $balance += (float) $entry['debit'] - (float) $entry['credit'];
            }
        }

        return round($balance, 2);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function formatRow(array $entry, bool $includeMovement): array
    {
        return [
            'date' => (string) ($entry['occurred_at'] ?? '—'),
            'entry_type' => (string) ($entry['entry_type'] ?? ''),
            'type_label' => (string) ($entry['type_label'] ?? ''),
            'reference' => (string) ($entry['reference'] ?? '—'),
            'description' => (string) ($entry['description'] ?? ''),
            'debit' => $includeMovement ? (float) ($entry['debit'] ?? 0) : 0.0,
            'credit' => $includeMovement ? (float) ($entry['credit'] ?? 0) : 0.0,
            'debit_display' => $includeMovement && (float) ($entry['debit'] ?? 0) > 0
                ? PropertyMoney::kes((float) $entry['debit'])
                : '—',
            'credit_display' => $includeMovement && (float) ($entry['credit'] ?? 0) > 0
                ? PropertyMoney::kes((float) $entry['credit'])
                : '—',
            'balance_after' => (float) ($entry['balance_after'] ?? 0),
            'balance_display' => PropertyMoney::kes((float) ($entry['balance_after'] ?? 0)),
            'drilldown' => (array) ($entry['drilldown'] ?? []),
        ];
    }

    private function invoiceDescription(PmInvoice $invoice): string
    {
        $unitLabel = trim(($invoice->unit?->property?->name ?? '').' / '.($invoice->unit?->label ?? ''), ' /');
        $period = $invoice->billing_period ? 'Period '.$invoice->billing_period : '';
        $bits = array_filter([
            strtoupper((string) $invoice->invoice_type),
            $period !== '' ? $period : null,
            $unitLabel !== '' ? $unitLabel : null,
            $invoice->description ? (string) $invoice->description : null,
        ]);

        return implode(' · ', $bits) ?: 'Utility charge';
    }

    private function allocationDescription(PmPaymentAllocation $allocation, PmPayment $payment): string
    {
        $invoiceNo = $allocation->invoice?->invoice_no ?: ('INV-'.$allocation->pm_invoice_id);
        $channel = strtoupper((string) ($payment->channel ?? 'PAYMENT'));

        return $channel.' · Allocated to '.$invoiceNo;
    }

    private function journalBatchId(string $sourceType, int $sourceId, string $eventType): ?int
    {
        $id = AccountingJournalBatch::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('event_type', $eventType)
            ->whereIn('status', [AccountingJournalBatch::STATUS_POSTED, AccountingJournalBatch::STATUS_REVERSED])
            ->orderByDesc('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function tenantSummaries(?string $q = null, ?int $propertyId = null, int $perPage = 30): LengthAwarePaginator
    {
        $query = PmTenant::query()
            ->select([
                'pm_tenants.id',
                'pm_tenants.name',
                'pm_tenants.phone',
            ])
            ->selectSub(
                PmInvoice::query()
                    ->liveBalances()
                    ->selectRaw('COALESCE(SUM(amount - amount_paid), 0)')
                    ->whereColumn('pm_tenant_id', 'pm_tenants.id')
                    ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
                    ->whereColumn('amount_paid', '<', 'amount'),
                'utility_balance'
            )
            ->selectSub(
                PmInvoice::query()
                    ->liveBalances()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('pm_tenant_id', 'pm_tenants.id')
                    ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
                    ->whereColumn('amount_paid', '<', 'amount'),
                'open_invoices'
            )
            ->whereExists(function ($sub) use ($propertyId) {
                $sub->select(DB::raw(1))
                    ->from('pm_invoices')
                    ->whereColumn('pm_invoices.pm_tenant_id', 'pm_tenants.id')
                    ->whereIn('pm_invoices.invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
                    ->whereNull('pm_invoices.deleted_at')
                    ->where('pm_invoices.status', '!=', PmInvoice::STATUS_CANCELLED)
                    ->when($propertyId, fn ($q) => $q->where('pm_invoices.property_unit_id', '>', 0)
                        ->whereExists(fn ($u) => $u->select(DB::raw(1))
                            ->from('property_units')
                            ->whereColumn('property_units.id', 'pm_invoices.property_unit_id')
                            ->where('property_units.property_id', $propertyId)));
            });

        if ($q !== null && trim($q) !== '') {
            $term = '%'.trim($q).'%';
            $query->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('utility_balance')
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (PmTenant $tenant) => [
                'tenant_id' => (int) $tenant->id,
                'name' => (string) $tenant->name,
                'phone' => (string) ($tenant->phone ?? '—'),
                'utility_balance' => round((float) ($tenant->utility_balance ?? 0), 2),
                'utility_balance_display' => PropertyMoney::kes((float) ($tenant->utility_balance ?? 0)),
                'open_invoices' => (int) ($tenant->open_invoices ?? 0),
            ]);
    }

    public function currentBalanceForTenant(int $tenantId): float
    {
        return round((float) PmInvoice::query()
            ->liveBalances()
            ->where('pm_tenant_id', $tenantId)
            ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
            ->selectRaw('COALESCE(SUM(GREATEST(amount - amount_paid, 0)), 0) as bal')
            ->value('bal'), 2);
    }
}
