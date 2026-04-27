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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmPaymentController extends Controller
{
    public function payments(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'channel' => strtolower(trim((string) $request->query('channel', ''))),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => strtolower(trim((string) $request->query('sort', 'paid_at'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'desc'))),
        ];
        $perPage = min(200, max(10, (int) $request->integer('per_page', 30)));

        $baseQuery = PmPayment::query()->with(['tenant', 'allocations.invoice']);
        if ($filters['status'] !== '' && in_array($filters['status'], [
            PmPayment::STATUS_PENDING,
            PmPayment::STATUS_COMPLETED,
            PmPayment::STATUS_FAILED,
        ], true)) {
            $baseQuery->where('status', $filters['status']);
        }
        if ($filters['channel'] !== '') {
            $baseQuery->where('channel', $filters['channel']);
        }
        if ($filters['from'] !== '') {
            $baseQuery->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $baseQuery->whereDate('created_at', '<=', $filters['to']);
        }
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
                ['Ref', 'Source', 'Channel', 'Amount', 'Received at', 'Payer ref', 'Allocated to', 'Status'],
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
                            (string) ($p->external_ref ?? ''),
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

        $mtdCompleted = (float) PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');

        $stats = [
            ['label' => 'Completed (MTD)', 'value' => PropertyMoney::kes($mtdCompleted), 'hint' => 'All payments'],
            ['label' => 'All-time count', 'value' => (string) $payments->total(), 'hint' => 'Across pages'],
            ['label' => 'Pending (this page)', 'value' => (string) $pageCollection->where('status', PmPayment::STATUS_PENDING)->count(), 'hint' => ''],
            ['label' => 'Failed (this page)', 'value' => (string) $pageCollection->where('status', PmPayment::STATUS_FAILED)->count(), 'hint' => ''],
        ];

        $rows = $pageCollection->map(function (PmPayment $p) {
            $allocatedTo = $p->allocations->pluck('invoice.invoice_no')->filter()->implode(', ');

            // If we have no explicit invoice numbers but the payment is linked to a tenant,
            // fall back to the tenant name so the "Allocated to" column is not blank.
            if ($allocatedTo === '' && $p->tenant) {
                $allocatedTo = $p->tenant->name;
            }

            $source = $this->sourceBadge($p);
            $receiptUrl = route('property.payments.receipt.show', ['payment' => $p->id], false);
            $actions = new HtmlString(
                '<div class="relative inline-block text-left">'.
                '<details>'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                '<div class="absolute right-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                '<a href="'.$receiptUrl.'" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">View</a>'.
                '</div>'.
                '</details>'.
                '</div>'
            );
            if ($p->status === PmPayment::STATUS_PENDING) {
                $completeUrl = route('property.payments.settle', $p);
                $failUrl = route('property.payments.settle', $p);
                $actions = new HtmlString(
                    '<div class="relative inline-block text-left">'.
                    '<details>'.
                    '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                    '<div class="absolute right-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                    '<form method="post" action="'.$completeUrl.'" class="block">'.
                    csrf_field().
                    method_field('PATCH').
                    '<input type="hidden" name="decision" value="completed">'.
                    '<button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Mark complete</button>'.
                    '</form>'.
                    '<form method="post" action="'.$failUrl.'" class="block">'.
                    csrf_field().
                    method_field('PATCH').
                    '<input type="hidden" name="decision" value="failed">'.
                    '<button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-red-700 hover:bg-rose-50">Mark failed</button>'.
                    '</form>'.
                    '<a href="'.$receiptUrl.'" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">View</a>'.
                    '</div>'.
                    '</details>'.
                    '</div>'
                );
            }

            return [
                new HtmlString('<label class="inline-flex items-center"><input type="checkbox" name="ids[]" value="'.$p->id.'" form="property-payments-bulk-form" class="rounded border-slate-300"><span class="sr-only">Select</span></label>'),
                'PAY-'.$p->id,
                $source,
                $this->channelLabel($p->channel),
                number_format((float) $p->amount, 2),
                $p->paid_at?->format('Y-m-d H:i') ?? '—',
                $p->external_ref ?? '—',
                $allocatedTo !== '' ? $allocatedTo : '—',
                ucfirst($p->status),
                $actions,
            ];
        })->all();

        return view('property.agent.revenue.payments', [
            'stats' => $stats,
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

        DB::transaction(function () use ($data, $invoice, $request) {
            $payment = PmPayment::query()->create([
                'pm_tenant_id' => $data['pm_tenant_id'],
                'channel' => $data['channel'],
                'amount' => $data['amount'],
                'external_ref' => $data['external_ref'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'status' => PmPayment::STATUS_COMPLETED,
                'meta' => null,
            ]);

            PmPaymentAllocation::query()->create([
                'pm_payment_id' => $payment->id,
                'pm_invoice_id' => $invoice->id,
                'amount' => $data['amount'],
            ]);

            $invoice->amount_paid = (float) $invoice->amount_paid + (float) $data['amount'];
            $invoice->save();
            $invoice->refreshComputedStatus();

            $payment->load('allocations.invoice.unit');
            PropertyAccountingPostingService::postPaymentReceived($payment, $request->user());
        });

        return back()->with('success', 'Payment recorded and allocated.');
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
                $payment->update([
                    'status' => PmPayment::STATUS_FAILED,
                    'paid_at' => null,
                ]);

                return;
            }

            $payment->update([
                'status' => PmPayment::STATUS_COMPLETED,
                'paid_at' => now(),
            ]);

            $remaining = (float) $payment->amount;
            $openInvoices = PmInvoice::query()
                ->where('pm_tenant_id', $payment->pm_tenant_id)
                ->whereColumn('amount_paid', '<', 'amount')
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($openInvoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $invoiceRemaining = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
                if ($invoiceRemaining <= 0) {
                    continue;
                }

                $allocation = min($remaining, $invoiceRemaining);
                PmPaymentAllocation::query()->create([
                    'pm_payment_id' => $payment->id,
                    'pm_invoice_id' => $invoice->id,
                    'amount' => $allocation,
                ]);

                $invoice->amount_paid = (float) $invoice->amount_paid + $allocation;
                $invoice->save();
                $invoice->refreshComputedStatus();
                $remaining -= $allocation;
            }

            $payment->load('allocations.invoice.unit');
            PropertyAccountingPostingService::postPaymentReceived($payment, $request->user());
        });

        return back()->with('success', 'Payment settlement updated.');
    }

    public function showReceipt(Request $request, PmPayment $payment): View
    {
        abort_unless($payment->status === PmPayment::STATUS_COMPLETED, 404);

        $payment->loadMissing(['tenant', 'allocations.invoice']);

        return view('property.agent.revenue.payment_receipt', [
            'payment' => $payment,
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
}
