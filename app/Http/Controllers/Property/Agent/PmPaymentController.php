<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Support\TabularExport;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyMoney;
use App\Services\Property\PropertyPaymentReversalApprovalService;
use App\Services\Property\PropertyPaymentSettlementService;
use App\Services\Property\TenantCreditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmPaymentController extends Controller
{
    public function payments(Request $request): View|StreamedResponse
    {
        [$rangeMonths, $rangeEndYm, $rangeFrom, $rangeTo, $receivedRangeLabel] = $this->resolvePaymentReceivedRange($request);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'reversal_status' => strtolower(trim((string) $request->query('reversal_status', ''))),
            'channel' => strtolower(trim((string) $request->query('channel', ''))),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'range_months' => (string) $rangeMonths,
            'range_end' => $rangeEndYm,
            'sort' => strtolower(trim((string) $request->query('sort', 'paid_at'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'desc'))),
        ];
        if ($rangeMonths > 0 && ($filters['from'] === '' || $filters['to'] === '')) {
            $filters['from'] = $rangeFrom->toDateString();
            $filters['to'] = $rangeTo->toDateString();
        }
        $perPage = min(200, max(10, (int) $request->integer('per_page', 30)));

        $baseQuery = $this->applyPaymentListFilters(
            PmPayment::query()->with(['tenant.user', 'allocations.invoice.tenant.user']),
            $filters
        );
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $baseQuery->where(function ($builder) use ($q) {
                $builder->where('external_ref', 'like', '%'.$q.'%')
                    ->orWhere('channel', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%')
                    ->orWhere('id', $q)
                    ->orWhereHas('tenant', fn ($tq) => $tq
                        ->where('name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%'));
            });
        }
        $sortMap = [
            'paid_at' => 'paid_at',
            'created_at' => 'created_at',
            'amount' => 'amount',
            'status' => 'status',
            'id' => 'id',
        ];
        $sortBy = $sortMap[$filters['sort']] ?? 'paid_at';
        $dir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'desc';
        $baseQuery->orderBy($sortBy, $dir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $baseQuery)->limit(5000)->get();
            return TabularExport::stream(
                'property-payments-'.now()->format('Ymd_His'),
                ['Ref', 'Source', 'Channel', 'Amount', 'Received at', 'Payer phone / ref', 'Allocated to', 'Status'],
                function () use ($rows) {
                    foreach ($rows as $p) {
                        $allocatedTo = $p->allocations->pluck('invoice.invoice_no')->filter()->implode(', ');
                        if ($allocatedTo === '' && $p->tenant) {
                            $allocatedTo = $p->tenant->name;
                        }
                        $source = (string) data_get($p->meta, 'source', 'manual');
                        $provider = (string) data_get($p->meta, 'provider', '');
                        $sourceLabel = match ($source) {
                            'equity_api' => 'Equity API',
                            'sms_ingest' => 'SMS Forwarder'.($provider !== '' ? ' ('.strtoupper($provider).')' : ''),
                            default => 'Manual / Legacy',
                        };
                        yield [
                            'PAY-'.$p->id,
                            $sourceLabel,
                            $this->channelLabel($p->channel),
                            number_format((float) $p->amount, 2, '.', ''),
                            $p->paid_at?->format('Y-m-d H:i:s') ?? '',
                            $this->payerPhoneOrRef($p, ''),
                            $allocatedTo,
                            ucfirst((string) $p->status),
                        ];
                    }
                },
                $export
            );
        }

        $payments = (clone $baseQuery)->paginate($perPage)->withQueryString();

        $pageCollection = $payments->getCollection();

        $summaryQuery = $this->applyPaymentListFilters(PmPayment::query(), array_merge($filters, [
            'status' => '',
            'reversal_status' => '',
            'q' => '',
        ]));

        $completed = PmPayment::STATUS_COMPLETED;
        $pending = PmPayment::STATUS_PENDING;
        $failed = PmPayment::STATUS_FAILED;

        $summaryRow = (clone $summaryQuery)
            ->selectRaw(
                'COUNT(*) as payment_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_count,
                COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as collected_amount,
                COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(amount), 0) as gross_amount,
                COUNT(DISTINCT CASE WHEN status = ? AND pm_tenant_id IS NOT NULL THEN pm_tenant_id END) as tenant_count',
                [$completed, $pending, $failed, $completed, $pending, $completed]
            )
            ->first();

        $collectedAmount = (float) ($summaryRow->collected_amount ?? 0);
        $completedCount = (int) ($summaryRow->completed_count ?? 0);
        $paymentCount = (int) ($summaryRow->payment_count ?? 0);
        $tenantCount = (int) ($summaryRow->tenant_count ?? 0);
        $avgPayment = $completedCount > 0 ? $collectedAmount / $completedCount : 0.0;

        $statsPrimary = [
            [
                'label' => 'Collected',
                'value' => PropertyMoney::kes($collectedAmount),
                'hint' => 'Completed payments · '.$receivedRangeLabel,
                'emphasis' => true,
            ],
            [
                'label' => 'Completed payments',
                'value' => (string) $completedCount,
                'hint' => $receivedRangeLabel,
            ],
            [
                'label' => 'Tenants paid',
                'value' => (string) $tenantCount,
                'hint' => 'Distinct tenants with completed payment',
            ],
            [
                'label' => 'Avg payment',
                'value' => PropertyMoney::kes($avgPayment),
                'hint' => 'Per completed receipt',
            ],
            [
                'label' => 'Pending',
                'value' => PropertyMoney::kes((float) ($summaryRow->pending_amount ?? 0)),
                'hint' => (string) ((int) ($summaryRow->pending_count ?? 0)).' awaiting settlement',
            ],
            [
                'label' => 'Failed',
                'value' => (string) ((int) ($summaryRow->failed_count ?? 0)),
                'hint' => $receivedRangeLabel,
            ],
            [
                'label' => 'All receipts',
                'value' => (string) $paymentCount,
                'hint' => 'Any status · '.$receivedRangeLabel,
            ],
            [
                'label' => 'Gross inflow',
                'value' => PropertyMoney::kes((float) ($summaryRow->gross_amount ?? 0)),
                'hint' => 'Sum of all payment amounts in period',
            ],
        ];

        $statsTable = [
            [
                'label' => 'Rows in table',
                'value' => (string) $payments->total(),
                'hint' => 'After status / channel / search filters',
            ],
            [
                'label' => 'On this page',
                'value' => (string) $pageCollection->count(),
                'hint' => 'Pending: '.$pageCollection->where('status', $pending)->count()
                    .' · Failed: '.$pageCollection->where('status', $failed)->count(),
            ],
        ];

        $rows = $pageCollection->map(function (PmPayment $p) {
            $allocatedTo = $p->allocations->pluck('invoice.invoice_no')->filter()->implode(', ');
            $canSettle = (bool) (auth()->user()?->hasPmPermission('payments.settle'));

            // If we have no explicit invoice numbers but the payment is linked to a tenant,
            // fall back to the tenant name so the "Allocated to" column is not blank.
            if ($allocatedTo === '' && $p->tenant) {
                $allocatedTo = $p->tenant->name;
            }

            $source = $this->sourceBadge($p);
            $actions = new HtmlString(view('property.agent.partials.payment_row_actions', [
                'payment' => $p,
                'canSettle' => $canSettle,
            ])->render());

            $statusLabel = ucfirst((string) $p->status);
            if (! blank($p->reversal_status)) {
                $statusLabel .= ' / Reversal '.ucfirst((string) $p->reversal_status);
            }

            return [
                new HtmlString('<label class="inline-flex items-center" data-row-ignore-click><input type="checkbox" name="ids[]" value="'.$p->id.'" form="property-payments-bulk-form" class="property-bulk-row-checkbox h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"><span class="sr-only">Select</span></label>'),
                'PAY-'.$p->id,
                $source,
                $this->channelLabel($p->channel),
                number_format((float) $p->amount, 2),
                $p->paid_at?->format('Y-m-d H:i') ?? '—',
                $this->payerPhoneOrRef($p),
                $allocatedTo !== '' ? $allocatedTo : '—',
                $statusLabel,
                $actions,
            ];
        })->all();

        return property_view('property.agent.revenue.payments', [
            // Legacy workspace view still reads `$stats`; v2 uses statsPrimary/statsTable in the above slot.
            'stats' => $statsPrimary,
            'statsPrimary' => $statsPrimary,
            'statsTable' => $statsTable,
            'receivedRangeLabel' => $receivedRangeLabel,
            'columns' => ['Select', 'Ref', 'Source', 'Channel', 'Amount', 'Received at', 'Payer phone / ref', 'Allocated to', 'Status', 'Actions'],
            'tableRows' => $rows,
            'paginator' => $payments,
            'perPage' => $perPage,
            'filters' => $filters,
            'openInvoices' => PmInvoice::query()
                ->with('tenant')
                ->whereColumn('amount_paid', '<', 'amount')
                ->orderBy('due_date')
                ->get(),
            // Only show tenants that actually have an open invoice (this screen posts against invoices).
            'tenants' => PmTenant::query()
                ->whereHas('invoices', function ($q) {
                    $q->whereColumn('amount_paid', '<', 'amount');
                })
                ->orderBy('name')
                ->get(),
            'tenantsForAdvance' => PmTenant::query()->orderBy('name')->get(['id', 'name']),
            'advanceCreditsEnabled' => app(TenantCreditService::class)->isEnabled(),
        ]);
    }

    private function channelLabel(?string $channel): string
    {
        $key = strtolower((string) $channel);

        return match ($key) {
            'mpesa' => 'M-Pesa',
            'bank' => 'Bank',
            'cash' => 'Cash',
            'card' => 'Card',
            'cheque' => 'Cheque',
            'equity_paybill' => 'Equity Paybill',
            'mpesa_sms_ingest' => 'M-Pesa (SMS Forwarder)',
            'mpesa_stk' => 'M-Pesa (STK Push)',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    private function sourceBadge(PmPayment $payment): HtmlString
    {
        $source = (string) data_get($payment->meta, 'source', 'manual');
        $provider = (string) data_get($payment->meta, 'provider', '');

        return match ($source) {
            'equity_api' => new HtmlString('<span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">Equity API</span>'),
            'sms_ingest' => new HtmlString('<span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">SMS Forwarder'.($provider !== '' ? ' ('.e(strtoupper($provider)).')' : '').'</span>'),
            default => new HtmlString('<span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Manual / Legacy</span>'),
        };
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'pm_invoice_id' => ['required', 'exists:pm_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'channel' => ['required', 'in:mpesa,bank,cash,card,cheque'],
            'external_ref' => ['nullable', 'string', 'max:128'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $invoice = PmInvoice::query()->findOrFail($data['pm_invoice_id']);
        if ((int) $invoice->pm_tenant_id !== (int) $data['pm_tenant_id']) {
            return back()->withErrors(['pm_invoice_id' => 'Invoice does not belong to this tenant.'])->withInput();
        }

        $remaining = (float) $invoice->amount - (float) $invoice->amount_paid;
        if ((float) $data['amount'] > $remaining + 0.0001) {
            return back()->withErrors(['amount' => 'Amount exceeds open balance on invoice.'])->withInput();
        }

        if ($data['channel'] !== 'cash' && blank($data['external_ref'] ?? null)) {
            return back()->withErrors(['external_ref' => 'Reference is required for non-cash payments.'])->withInput();
        }

        $agentUserId = null;
        if (Schema::hasColumn('pm_payments', 'agent_user_id')) {
            $agentUserId = (int) ($invoice->agent_user_id ?? 0);
            if ($agentUserId <= 0) {
                $invoice->loadMissing('unit.property');
                $agentUserId = (int) ($invoice->unit?->property?->agent_user_id ?? 0);
            }
        }

        app(PropertyPaymentSettlementService::class)->recordPaymentToInvoice(
            $invoice,
            (float) $data['amount'],
            (string) $data['channel'],
            $data['external_ref'] ?? null,
            $data['paid_at'] ?? now(),
            $request->user(),
            null,
            $agentUserId > 0 ? $agentUserId : null,
        );

        return back()->with('success', 'Payment recorded and allocated.');
    }

    /**
     * Record a tenant payment with no invoice required (prepay / advance rent).
     * Applies to any open invoices first, then holds the remainder as tenant credit.
     */
    public function storeAdvance(Request $request): RedirectResponse
    {
        $creditService = app(TenantCreditService::class);
        if (! $creditService->isEnabled()) {
            return back()
                ->withErrors(['advance' => 'Tenant advance credits are not available. Run database migrations (pm_tenant_credit tables) first.'])
                ->withInput();
        }

        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'channel' => ['required', 'in:mpesa,bank,cash,card,cheque'],
            'external_ref' => ['nullable', 'string', 'max:128'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['channel'] !== 'cash' && blank($data['external_ref'] ?? null)) {
            return back()->withErrors(['external_ref' => 'Reference is required for non-cash payments.'])->withInput();
        }

        $paymentId = null;

        $payment = app(PropertyPaymentSettlementService::class)->recordAdvancePayment([
            'pm_tenant_id' => $data['pm_tenant_id'],
            'channel' => $data['channel'],
            'amount' => $data['amount'],
            'external_ref' => $data['external_ref'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'meta' => [
                'source' => 'manual',
                'payment_kind' => 'advance',
                'notes' => $data['notes'] ?? null,
            ],
        ], $request->user());

        $paymentId = (int) $payment->id;

        $payment = PmPayment::query()->with('allocations')->find($paymentId);
        $allocated = round((float) $payment?->allocations->sum('amount'), 2);
        $credit = round((float) data_get($payment?->meta, 'tenant_credit_created', 0), 2);
        if ($credit <= 0 && $payment) {
            $credit = max(0.0, round((float) $payment->amount - $allocated, 2));
        }

        $parts = ['Advance payment recorded.'];
        if ($allocated > 0) {
            $parts[] = PropertyMoney::kes($allocated).' applied to open invoice(s).';
        }
        if ($credit > 0) {
            $parts[] = PropertyMoney::kes($credit).' held as tenant advance credit.';
        }

        $message = implode(' ', $parts);

        if ($request->input('return_to') === 'credit_ledger') {
            return redirect()
                ->route('property.tenants.credit.ledger', ['tenant' => $data['pm_tenant_id']])
                ->with('success', $message);
        }

        if ($request->input('return_to') === 'tenant_credits') {
            return redirect()
                ->route('property.revenue.tenant_credits')
                ->with('success', $message);
        }

        return back()->with('success', $message);
    }

    public function settle(Request $request, PmPayment $payment): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:completed,failed'],
        ]);

        if ($payment->status !== PmPayment::STATUS_PENDING) {
            return back()->withErrors(['payment' => 'Only pending payments can be settled.']);
        }

        DB::transaction(function () use ($data, $payment, $request) {
            $payment->refresh();
            if ($payment->status !== PmPayment::STATUS_PENDING) {
                return;
            }

            if ($data['decision'] === 'failed') {
                app(PropertyPaymentSettlementService::class)
                    ->fail($payment, $payment->external_ref, 'Marked failed by agent', 'manual_settle');

                return;
            }

            app(PropertyPaymentSettlementService::class)->complete(
                $payment,
                $payment->external_ref,
                now(),
                'Settled manually by agent',
                'manual_settle',
            );
        });

        return back()->with('success', 'Payment settlement updated.');
    }

    public function requestReversal(Request $request, PmPayment $payment): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'utility_override_request_id' => ['nullable', 'integer'],
        ]);

        try {
            app(PropertyPaymentReversalApprovalService::class)
                ->request(
                    $payment,
                    (int) $request->user()->id,
                    (string) $data['reason'],
                    (int) ($data['utility_override_request_id'] ?? 0) ?: null,
                );
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        } catch (\App\Exceptions\Property\UtilityPeriodClosedException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Reversal request submitted for checker approval.');
    }

    public function approveReversal(Request $request, PmPayment $payment): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            app(PropertyPaymentReversalApprovalService::class)
                ->approve($payment, (int) $request->user()->id, (string) ($data['reason'] ?? ''));
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Payment reversal approved and posted.');
    }

    public function showReceipt(Request $request, PmPayment $payment): View
    {
        abort_unless($payment->status === PmPayment::STATUS_COMPLETED, 404);

        $payment->loadMissing(['tenant', 'allocations.invoice']);
        $allocatedTotal = round((float) $payment->allocations->sum('amount'), 2);
        $creditCreated = round((float) data_get($payment->meta, 'tenant_credit_created', 0), 2);
        if ($creditCreated <= 0) {
            $creditCreated = max(0.0, round((float) $payment->amount - $allocatedTotal, 2));
        }

        return property_view('property.agent.revenue.payment_receipt', [
            'payment' => $payment,
            'allocatedTotal' => $allocatedTotal,
            'creditCreated' => $creditCreated,
        ]);
    }

    public function downloadReceipt(Request $request, PmPayment $payment)
    {
        abort_unless($payment->status === PmPayment::STATUS_COMPLETED, 404);

        $payment->loadMissing(['tenant', 'allocations.invoice']);

        $html = view('property.agent.revenue.payment_receipt_download', [
            'payment' => $payment,
        ])->render();

        $fileName = 'receipt-RCP-PAY-'.$payment->id.'.html';

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $fileName, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: int, 1: string, 2: Carbon, 3: Carbon, 4: string}
     */
    private function resolvePaymentReceivedRange(Request $request): array
    {
        $allowed = [0, 1, 2, 3, 6, 12];
        $rangeMonths = (int) $request->query('range_months', 1);
        if (! in_array($rangeMonths, $allowed, true)) {
            $rangeMonths = 1;
        }

        $rangeEndYm = trim((string) $request->query('range_end', now()->format('Y-m')));
        if (preg_match('/^\d{4}\-\d{2}$/', $rangeEndYm) !== 1) {
            $rangeEndYm = now()->format('Y-m');
        }

        $rangeTo = Carbon::createFromFormat('Y-m', $rangeEndYm)->endOfMonth()->startOfDay();
        $rangeFrom = $rangeTo->copy()->subMonths(max(0, $rangeMonths - 1))->startOfMonth()->startOfDay();

        $receivedRangeLabel = match ($rangeMonths) {
            0 => 'All dates',
            1 => 'Received '.$rangeFrom->format('M Y'),
            default => 'Received '.$rangeFrom->format('M Y').' – '.$rangeTo->format('M Y').' ('.$rangeMonths.' mo)',
        };

        return [$rangeMonths, $rangeEndYm, $rangeFrom, $rangeTo, $receivedRangeLabel];
    }

    /**
     * Payer phone from ingest/meta, else payment reference, else allocated tenant phone.
     */
    private function payerPhoneOrRef(PmPayment $payment, string $empty = '—'): string
    {
        $metaPhone = trim((string) (data_get($payment->meta, 'payer_phone') ?? data_get($payment->meta, 'phone') ?? ''));
        if ($metaPhone !== '') {
            return $metaPhone;
        }

        $externalRef = trim((string) ($payment->external_ref ?? ''));
        if ($externalRef !== '') {
            return $externalRef;
        }

        $tenant = $payment->tenant;
        if (! $tenant && $payment->relationLoaded('allocations')) {
            $tenant = $payment->allocations->first()?->invoice?->tenant;
        }

        $tenantPhone = trim((string) ($tenant?->phone ?? $tenant?->user?->phone ?? ''));
        if ($tenantPhone !== '') {
            return $tenantPhone;
        }

        return $empty;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyPaymentListFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        if (($filters['status'] ?? '') !== '' && in_array($filters['status'], [
            PmPayment::STATUS_PENDING,
            PmPayment::STATUS_COMPLETED,
            PmPayment::STATUS_FAILED,
        ], true)) {
            $query->where('status', $filters['status']);
        }
        if (($filters['reversal_status'] ?? '') !== '' && in_array($filters['reversal_status'], [
            PmPayment::REVERSAL_STATUS_PENDING,
            PmPayment::REVERSAL_STATUS_APPROVED,
            PmPayment::REVERSAL_STATUS_REJECTED,
            PmPayment::REVERSAL_STATUS_REVERSED,
        ], true)) {
            $query->where('reversal_status', $filters['reversal_status']);
        }
        if (($filters['channel'] ?? '') !== '') {
            $query->where('channel', $filters['channel']);
        }

        $from = (string) ($filters['from'] ?? '');
        $to = (string) ($filters['to'] ?? '');
        if ($from !== '') {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $inner) use ($from) {
                $inner->whereDate('paid_at', '>=', $from)
                    ->orWhere(function (\Illuminate\Database\Eloquent\Builder $pending) use ($from) {
                        $pending->whereNull('paid_at')->whereDate('created_at', '>=', $from);
                    });
            });
        }
        if ($to !== '') {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $inner) use ($to) {
                $inner->whereDate('paid_at', '<=', $to)
                    ->orWhere(function (\Illuminate\Database\Eloquent\Builder $pending) use ($to) {
                        $pending->whereNull('paid_at')->whereDate('created_at', '<=', $to);
                    });
            });
        }

        return $query;
    }
}
