<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PmPaymentController extends Controller
{
    public function payments(): View
    {
        $payments = PmPayment::query()
            ->with(['tenant', 'allocations.invoice'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $mtdCompleted = (float) PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');

        $stats = [
            ['label' => 'Completed (MTD)', 'value' => PropertyMoney::kes($mtdCompleted), 'hint' => 'All payments'],
            ['label' => 'All-time count', 'value' => (string) $payments->count(), 'hint' => 'Rows shown'],
            ['label' => 'Pending', 'value' => (string) $payments->where('status', PmPayment::STATUS_PENDING)->count(), 'hint' => ''],
            ['label' => 'Failed', 'value' => (string) $payments->where('status', PmPayment::STATUS_FAILED)->count(), 'hint' => ''],
        ];

        $rows = $payments->map(function (PmPayment $p) {
            $allocatedTo = $p->allocations->pluck('invoice.invoice_no')->filter()->implode(', ');
            $actions = '—';
            if ($p->status === PmPayment::STATUS_PENDING) {
                $completeUrl = route('property.payments.settle', $p);
                $failUrl = route('property.payments.settle', $p);
                $actions = new HtmlString(
                    '<form method="post" action="'.$completeUrl.'" class="inline-flex items-center gap-2">'.
                    csrf_field().
                    method_field('PATCH').
                    '<input type="hidden" name="decision" value="completed">'.
                    '<button type="submit" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Mark complete</button>'.
                    '</form> '.
                    '<form method="post" action="'.$failUrl.'" class="inline-flex items-center gap-2 ml-2">'.
                    csrf_field().
                    method_field('PATCH').
                    '<input type="hidden" name="decision" value="failed">'.
                    '<button type="submit" class="text-xs font-semibold text-red-700 hover:text-red-800">Mark failed</button>'.
                    '</form>'
                );
            }

            return [
                'PAY-'.$p->id,
                $p->channel,
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
            'columns' => ['Ref', 'Channel', 'Amount', 'Received at', 'Payer phone / ref', 'Allocated to', 'Status', 'Actions'],
            'tableRows' => $rows,
            'tenants' => PmTenant::query()->orderBy('name')->get(),
            'openInvoices' => PmInvoice::query()
                ->with('tenant')
                ->whereColumn('amount_paid', '<', 'amount')
                ->orderBy('due_date')
                ->get(),
        ]);
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
}
