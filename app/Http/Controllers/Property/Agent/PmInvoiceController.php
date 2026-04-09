<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PropertyUnit;
use App\Support\TabularExport;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmInvoiceController extends Controller
{
    public function leaseInfo(PmLease $lease)
    {
        $lease->loadMissing(['pmTenant:id,name', 'units:id,property_id,label', 'units.property:id,name']);

        $unitIds = $lease->units->pluck('id')->values()->all();
        $firstUnit = $lease->units->first();
        $response = [
            'ok' => true,
            'lease_id' => (int) $lease->id,
            'tenant' => [
                'id' => (int) $lease->pm_tenant_id,
                'name' => (string) ($lease->pmTenant?->name ?? ''),
            ],
            'unit' => $firstUnit ? [
                'id' => (int) $firstUnit->id,
                'label' => (string) ($firstUnit->label ?? ''),
                'property' => [
                    'id' => (int) ($firstUnit->property_id ?? 0),
                    'name' => (string) ($firstUnit->property?->name ?? ''),
                ],
            ] : null,
            'unit_ids' => array_map('intval', $unitIds),
            'monthly_rent' => (float) ($lease->monthly_rent ?? 0),
        ];

        return response()->json($response);
    }
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

        $invoiceNo = PmInvoice::nextInvoiceNumber();

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

    public function invoices(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'period' => trim((string) $request->query('period', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => strtolower(trim((string) $request->query('sort', 'issue_date'))),
            'dir' => strtolower(trim((string) $request->query('dir', 'desc'))),
        ];
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $baseQuery = PmInvoice::query()->with(['tenant', 'unit.property']);
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $baseQuery->where(function ($inner) use ($q) {
                $inner->where('invoice_no', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhereHas('tenant', fn ($tq) => $tq
                        ->where('name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%'))
                    ->orWhereHas('unit', fn ($uq) => $uq
                        ->where('label', 'like', '%'.$q.'%')
                        ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$q.'%')));
            });
        }
        if ($filters['status'] !== '' && in_array($filters['status'], [
            PmInvoice::STATUS_DRAFT,
            PmInvoice::STATUS_SENT,
            PmInvoice::STATUS_PARTIAL,
            PmInvoice::STATUS_PAID,
            PmInvoice::STATUS_OVERDUE,
            PmInvoice::STATUS_CANCELLED,
        ], true)) {
            $baseQuery->where('status', $filters['status']);
        }
        if ($filters['period'] !== '' && preg_match('/^\d{4}\-\d{2}$/', $filters['period']) === 1) {
            $baseQuery->where('billing_period', $filters['period']);
        }
        if ($filters['from'] !== '') {
            $baseQuery->whereDate('issue_date', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $baseQuery->whereDate('issue_date', '<=', $filters['to']);
        }
        $sortMap = [
            'issue_date' => 'issue_date',
            'due_date' => 'due_date',
            'amount' => 'amount',
            'status' => 'status',
            'invoice_no' => 'invoice_no',
            'id' => 'id',
        ];
        $sortBy = $sortMap[$filters['sort']] ?? 'issue_date';
        $dir = in_array($filters['dir'], ['asc', 'desc'], true) ? $filters['dir'] : 'desc';
        $baseQuery->orderBy($sortBy, $dir)->orderByDesc('id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $items = (clone $baseQuery)->limit(5000)->get();
            return TabularExport::stream(
                'invoices-'.now()->format('Ymd_His'),
                ['Invoice #', 'Tenant', 'Unit', 'Period', 'Amount', 'Issued', 'Due', 'Channel', 'Status'],
                function () use ($items) {
                    foreach ($items as $i) {
                        $channel = $i->status === PmInvoice::STATUS_PAID ? 'Settled' : 'Open';
                        yield [
                            (string) $i->invoice_no,
                            (string) ($i->tenant->name ?? ''),
                            (string) (($i->unit->property->name ?? '').'/'.($i->unit->label ?? '')),
                            $i->issue_date?->format('Y-m') ?? '',
                            number_format((float) $i->amount, 2, '.', ''),
                            $i->issue_date?->format('Y-m-d') ?? '',
                            $i->due_date?->format('Y-m-d') ?? '',
                            $channel,
                            ucfirst((string) $i->status),
                        ];
                    }
                },
                $export
            );
        }

        $invoices = (clone $baseQuery)->paginate($perPage)->withQueryString();
        $statsBase = (clone $baseQuery)->get();

        $stats = [
            ['label' => 'Draft', 'value' => (string) $statsBase->where('status', PmInvoice::STATUS_DRAFT)->count(), 'hint' => 'Filtered'],
            ['label' => 'Open', 'value' => (string) $statsBase->whereIn('status', [PmInvoice::STATUS_SENT, PmInvoice::STATUS_PARTIAL, PmInvoice::STATUS_OVERDUE])->count(), 'hint' => 'Filtered'],
            ['label' => 'Paid', 'value' => (string) $statsBase->where('status', PmInvoice::STATUS_PAID)->count(), 'hint' => 'Filtered'],
            ['label' => 'Outstanding', 'value' => PropertyMoney::kes((float) $statsBase->sum(fn ($i) => max(0, (float) $i->amount - (float) $i->amount_paid))), 'hint' => 'Filtered open balance'],
        ];

        $rows = $invoices->getCollection()->map(function (PmInvoice $i) {
            $channel = $i->status === PmInvoice::STATUS_PAID ? 'Settled' : 'Open';
            $actions = new HtmlString(
                '<a href="'.route('property.revenue.payments').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Apply payment</a>'
            );

            return [
                new HtmlString('<label class="inline-flex items-center"><input type="checkbox" name="ids[]" value="'.$i->id.'" form="property-invoices-bulk-form" class="rounded border-slate-300"><span class="sr-only">Select</span></label>'),
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
            'columns' => ['Select', 'Invoice #', 'Tenant', 'Unit', 'Period', 'Amount', 'Issued', 'Due', 'Channel', 'Status', 'Actions'],
            'tableRows' => $rows,
            'paginator' => $invoices,
            'filters' => [
                ...$filters,
                'sort' => $sortBy,
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
            'leases' => PmLease::query()->with(['pmTenant', 'units'])->orderByDesc('start_date')->get(),
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'tenants' => PmTenant::query()->orderBy('name')->get(),
        ]);
    }
}
