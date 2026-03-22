<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PmPaymentController extends Controller
{
    public function payments(): View
    {
        $payments = PmPayment::query()->with('tenant')->orderByDesc('paid_at')->orderByDesc('id')->limit(200)->get();

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

        $rows = $payments->map(fn (PmPayment $p) => [
            'PAY-'.$p->id,
            $p->channel,
            number_format((float) $p->amount, 2),
            $p->paid_at?->format('Y-m-d H:i') ?? '—',
            $p->external_ref ?? '—',
            '—',
            ucfirst($p->status),
            '—',
        ])->all();

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
            'channel' => ['required', 'string', 'max:32'],
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

        DB::transaction(function () use ($data, $invoice) {
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
        });

        return back()->with('success', 'Payment recorded and allocated.');
    }
}
