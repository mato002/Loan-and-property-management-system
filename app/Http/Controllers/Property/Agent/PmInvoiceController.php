<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PmInvoiceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_lease_id' => ['nullable', 'exists:pm_leases,id'],
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,sent'],
            'invoice_type' => ['nullable', 'in:rent,water,mixed'],
            'billing_period' => ['nullable', 'date_format:Y-m'],
        ]);

        $next = (int) (PmInvoice::query()->max('id') ?? 0) + 1;
        $invoiceNo = 'INV-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);

        $invoice = PmInvoice::query()->create([
            ...$data,
            'invoice_no' => $invoiceNo,
            'amount_paid' => 0,
            'invoice_type' => $data['invoice_type'] ?? PmInvoice::TYPE_RENT,
        ]);

        $invoice->refreshComputedStatus();
        $invoice->loadMissing('unit');
        PropertyAccountingPostingService::postInvoiceIssued($invoice, $request->user());

        return back()->with('success', 'Invoice '.$invoice->invoice_no.' created.');
    }

    public function invoices(): View
    {
        $invoices = PmInvoice::query()->with(['tenant', 'unit.property'])->orderByDesc('issue_date')->limit(200)->get();

        $stats = [
            ['label' => 'Draft', 'value' => (string) $invoices->where('status', PmInvoice::STATUS_DRAFT)->count(), 'hint' => ''],
            ['label' => 'Open', 'value' => (string) $invoices->whereIn('status', [PmInvoice::STATUS_SENT, PmInvoice::STATUS_PARTIAL, PmInvoice::STATUS_OVERDUE])->count(), 'hint' => ''],
            ['label' => 'Paid', 'value' => (string) $invoices->where('status', PmInvoice::STATUS_PAID)->count(), 'hint' => ''],
            ['label' => 'Outstanding', 'value' => PropertyMoney::kes((float) $invoices->sum(fn ($i) => max(0, (float) $i->amount - (float) $i->amount_paid))), 'hint' => 'Open balance'],
        ];

        $rows = $invoices->map(function (PmInvoice $i) {
            $channel = $i->status === PmInvoice::STATUS_PAID ? 'Settled' : 'Open';
            $actions = new HtmlString(
                '<a href="'.route('property.revenue.payments').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Apply payment</a>'
            );

            return [
                $i->invoice_no,
                $i->tenant->name,
                $i->unit->property->name.'/'.$i->unit->label,
                $i->issue_date->format('Y-m'),
                number_format((float) $i->amount, 2),
                $i->issue_date->format('Y-m-d'),
                $i->due_date->format('Y-m-d'),
                $channel,
                ucfirst($i->status),
                $actions,
            ];
        })->all();

        return view('property.agent.revenue.invoices', [
            'stats' => $stats,
            'columns' => ['Invoice #', 'Tenant', 'Unit', 'Period', 'Amount', 'Issued', 'Due', 'Channel', 'Status', 'Actions'],
            'tableRows' => $rows,
            'leases' => PmLease::query()->with('pmTenant')->orderByDesc('start_date')->get(),
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'tenants' => PmTenant::query()->orderBy('name')->get(),
        ]);
    }
}
