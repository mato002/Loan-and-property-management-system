<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmInvoiceItem;
use App\Models\PmLease;
use App\Models\PmMessageLog;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Services\BulkSmsService;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyMoney;
use App\Support\TabularExport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmInvoiceController extends Controller
{
    public function show(PmInvoice $invoice): View
    {
        $invoice->loadMissing([
            'tenant:id,name,phone,email',
            'unit:id,label,property_id',
            'unit.property:id,name',
            'lease:id,start_date,end_date,monthly_rent',
            'items',
            'events.user:id,name',
            'allocations.payment:id,external_ref,paid_at,status,amount,channel',
            'creditNotes:id,invoice_no,amount,status,issue_date,original_invoice_id',
            'originalInvoice:id,invoice_no,amount,status',
        ]);

        $sharedUrl = null;
        if ($invoice->share_token) {
            $sharedUrl = route('property.invoices.public.show', ['token' => $invoice->share_token]);
        }

        return view('property.agent.revenue.invoices_show', [
            'invoice' => $invoice,
            'sharedUrl' => $sharedUrl,
        ]);
    }

    public function edit(PmInvoice $invoice): View
    {
        $invoice->loadMissing([
            'tenant:id,name',
            'unit:id,label,property_id',
            'unit.property:id,name',
            'items',
        ]);

        return view('property.agent.revenue.invoices_edit', [
            'invoice' => $invoice,
        ]);
    }

    public function update(Request $request, PmInvoice $invoice): RedirectResponse
    {
        // The status select on this form only exposes statuses an agent can
        // _set manually_: draft, sent, cancelled. Computed statuses
        // (partial, paid, overdue) cannot be hand-edited and the form
        // shouldn't even offer them.
        $data = $request->validate([
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,sent,cancelled'],
            'cancelled_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ((string) $invoice->status === PmInvoice::STATUS_PAID) {
            return back()->withErrors(['status' => 'Paid invoices cannot be edited manually.'])->withInput();
        }

        $amountPaid = (float) $invoice->amount_paid;
        if ((float) $data['amount'] < $amountPaid) {
            return back()->withErrors(['amount' => 'Amount cannot be less than already paid value (KES '.number_format($amountPaid, 2).').'])->withInput();
        }

        if ((string) $data['status'] === PmInvoice::STATUS_CANCELLED && $amountPaid > 0) {
            return back()->withErrors(['status' => 'Cannot cancel an invoice that already has payments.'])->withInput();
        }

        $previousAmount = (float) $invoice->amount;
        $previousStatus = (string) $invoice->status;
        $newAmount = (float) $data['amount'];
        $newStatus = (string) $data['status'];

        DB::transaction(function () use ($invoice, $data, $request, $previousAmount, $previousStatus, $newAmount, $newStatus) {
            $payload = [
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'amount' => $newAmount,
                'total_amount' => $newAmount,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $newStatus,
            ];

            // Stamp status transitions so the audit trail and downstream
            // reports (e.g. landlord activity) have authoritative timestamps.
            if ($newStatus === PmInvoice::STATUS_SENT && $previousStatus !== PmInvoice::STATUS_SENT && empty($invoice->sent_at)) {
                $payload['sent_at'] = now();
                $payload['sent_by_user_id'] = $request->user()?->id;
            }
            if ($newStatus === PmInvoice::STATUS_CANCELLED && $previousStatus !== PmInvoice::STATUS_CANCELLED) {
                $payload['cancelled_at'] = now();
                $payload['cancelled_by_user_id'] = $request->user()?->id;
                $payload['cancelled_reason'] = $data['cancelled_reason'] ?? null;
            }
            if ($newStatus !== PmInvoice::STATUS_CANCELLED && $previousStatus === PmInvoice::STATUS_CANCELLED) {
                $payload['cancelled_at'] = null;
                $payload['cancelled_by_user_id'] = null;
                $payload['cancelled_reason'] = null;
            }

            $invoice->update($payload);

            PmInvoiceEvent::record(
                (int) $invoice->id,
                PmInvoiceEvent::EVENT_EDITED,
                $request->user()?->id,
                'Invoice edited',
                [
                    'before' => ['amount' => $previousAmount, 'status' => $previousStatus],
                    'after' => ['amount' => $newAmount, 'status' => $newStatus],
                ]
            );

            // Accounting parity: keep the GL in sync with edits.
            if ($newStatus === PmInvoice::STATUS_CANCELLED && $previousStatus !== PmInvoice::STATUS_CANCELLED) {
                PropertyAccountingPostingService::reverseInvoiceIssued($invoice, $request->user(), 'Edit: status moved to cancelled');
                PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_CANCELLED, $request->user()?->id, $data['cancelled_reason'] ?? null);
            } elseif ($previousStatus === PmInvoice::STATUS_CANCELLED && $newStatus !== PmInvoice::STATUS_CANCELLED) {
                PropertyAccountingPostingService::postInvoiceIssued($invoice, $request->user());
                PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_REOPENED, $request->user()?->id);
            } elseif (round($previousAmount, 2) !== round($newAmount, 2) && $newStatus !== PmInvoice::STATUS_CANCELLED) {
                // Material amount change on an active invoice — reverse the
                // previous journal and post a fresh one under a new revision.
                PropertyAccountingPostingService::repostInvoiceAfterEdit($invoice, $request->user());
            }
        });

        return redirect()
            ->route('property.revenue.invoices.show', $invoice)
            ->with('success', 'Invoice '.$invoice->invoice_no.' updated.');
    }

    public function destroy(Request $request, PmInvoice $invoice): RedirectResponse
    {
        if ((float) $invoice->amount_paid > 0 || $invoice->allocations()->exists()) {
            return back()->withErrors([
                'invoice' => 'Cannot delete an invoice that already has payment allocations. Cancel it or issue a credit note instead.',
            ]);
        }

        $invoiceNo = (string) $invoice->invoice_no;

        DB::transaction(function () use ($invoice, $request) {
            // Reverse any open journal entries so the GL doesn't ghost-credit
            // an income line for an invoice that no longer exists.
            PropertyAccountingPostingService::reverseInvoiceIssued($invoice, $request->user(), 'Invoice deleted');

            PmInvoiceEvent::record(
                (int) $invoice->id,
                PmInvoiceEvent::EVENT_DELETED,
                $request->user()?->id,
                'Invoice soft-deleted'
            );

            $invoice->delete();
        });

        return back()->with('success', 'Invoice '.$invoiceNo.' deleted.');
    }

    public function updateStatus(Request $request, PmInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,sent,cancelled'],
            'cancelled_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ((string) $invoice->status === PmInvoice::STATUS_PAID) {
            return back()->withErrors(['status' => 'Paid invoices cannot be changed manually.']);
        }

        $target = (string) $data['status'];
        $previous = (string) $invoice->status;
        if ($target === PmInvoice::STATUS_CANCELLED && (float) $invoice->amount_paid > 0) {
            return back()->withErrors(['status' => 'Cannot cancel an invoice that already has payments.']);
        }

        DB::transaction(function () use ($invoice, $request, $target, $previous, $data) {
            $payload = ['status' => $target];
            if ($target === PmInvoice::STATUS_SENT && empty($invoice->sent_at)) {
                $payload['sent_at'] = now();
                $payload['sent_by_user_id'] = $request->user()?->id;
            }
            if ($target === PmInvoice::STATUS_CANCELLED) {
                $payload['cancelled_at'] = now();
                $payload['cancelled_by_user_id'] = $request->user()?->id;
                $payload['cancelled_reason'] = $data['cancelled_reason'] ?? null;
            }
            if ($previous === PmInvoice::STATUS_CANCELLED && $target !== PmInvoice::STATUS_CANCELLED) {
                $payload['cancelled_at'] = null;
                $payload['cancelled_by_user_id'] = null;
                $payload['cancelled_reason'] = null;
            }
            $invoice->update($payload);

            if ($target === PmInvoice::STATUS_CANCELLED && $previous !== PmInvoice::STATUS_CANCELLED) {
                PropertyAccountingPostingService::reverseInvoiceIssued($invoice, $request->user(), 'Status: cancelled');
                PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_CANCELLED, $request->user()?->id, $data['cancelled_reason'] ?? null);
            } elseif ($previous === PmInvoice::STATUS_CANCELLED && $target !== PmInvoice::STATUS_CANCELLED) {
                PropertyAccountingPostingService::postInvoiceIssued($invoice, $request->user());
                PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_REOPENED, $request->user()?->id);
            } elseif ($target === PmInvoice::STATUS_SENT && $previous === PmInvoice::STATUS_DRAFT) {
                PropertyAccountingPostingService::postInvoiceIssued($invoice, $request->user());
                PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_SENT, $request->user()?->id);
            }
        });

        return back()->with('success', 'Invoice '.$invoice->invoice_no.' status updated to '.ucfirst($target).'.');
    }

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
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,sent'],
            'invoice_type' => ['nullable', 'in:rent,water,mixed'],
            'billing_period' => ['nullable', 'date_format:Y-m'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        // C2: cross-validate tenant + unit + (optional) lease.
        $unit = PropertyUnit::query()->find($data['property_unit_id']);
        if (! $unit) {
            return back()->withErrors(['property_unit_id' => 'Unit not found.'])->withInput();
        }

        if (! empty($data['pm_lease_id'])) {
            $lease = PmLease::query()->with(['units:id', 'pmTenant:id'])->find($data['pm_lease_id']);
            if ($lease) {
                if ((int) $lease->pm_tenant_id !== (int) $data['pm_tenant_id']) {
                    return back()->withErrors(['pm_tenant_id' => 'Tenant does not match the lease selected.'])->withInput();
                }
                $leaseUnitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();
                if ($leaseUnitIds && ! in_array((int) $data['property_unit_id'], $leaseUnitIds, true)) {
                    return back()->withErrors(['property_unit_id' => 'Unit is not attached to the selected lease.'])->withInput();
                }
            }
        }

        // C2: idempotency. If the caller provided an idempotency_key OR if we
        // already have a recent invoice (within ~10 seconds) with identical
        // tenant + unit + amount + type, return that one rather than
        // double-creating. Mostly catches accidental double form submits.
        $idemKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idemKey !== '') {
            $cacheKey = 'pm_invoice_idem:'.$request->user()?->id.':'.$idemKey;
            $existingId = Cache::get($cacheKey);
            if ($existingId) {
                $existing = PmInvoice::query()->find($existingId);
                if ($existing) {
                    return back()->with('success', 'Invoice '.$existing->invoice_no.' already created.');
                }
            }
        }
        $recent = PmInvoice::query()
            ->where('pm_tenant_id', $data['pm_tenant_id'])
            ->where('property_unit_id', $data['property_unit_id'])
            ->where('amount', $data['amount'])
            ->where('invoice_type', $data['invoice_type'] ?? PmInvoice::TYPE_RENT)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->first();
        if ($recent) {
            return back()->with('success', 'Invoice '.$recent->invoice_no.' already created.');
        }

        $invoice = DB::transaction(function () use ($data, $request, $unit) {
            $invoiceNo = PmInvoice::nextInvoiceNumber();
            $invoiceType = $data['invoice_type'] ?? PmInvoice::TYPE_RENT;
            $amount = (float) $data['amount'];

            return PmInvoice::query()->create([
                'pm_lease_id' => $data['pm_lease_id'] ?? null,
                'property_unit_id' => $data['property_unit_id'],
                'pm_tenant_id' => $data['pm_tenant_id'],
                'agent_user_id' => optional($unit->property)->agent_user_id ?? $request->user()?->id,
                'created_by_user_id' => $request->user()?->id,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'amount' => $amount,
                'subtotal_amount' => $amount,
                'total_amount' => $amount,
                'amount_paid' => 0,
                'invoice_no' => $invoiceNo,
                'status' => $data['status'],
                'sent_at' => $data['status'] === PmInvoice::STATUS_SENT ? now() : null,
                'sent_by_user_id' => $data['status'] === PmInvoice::STATUS_SENT ? $request->user()?->id : null,
                'invoice_type' => $invoiceType,
                'billing_period' => $data['billing_period'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        if ($idemKey !== '') {
            Cache::put('pm_invoice_idem:'.$request->user()?->id.':'.$idemKey, $invoice->id, now()->addMinutes(10));
        }

        $invoice->refreshComputedStatus();
        $invoice->loadMissing('unit.property');

        // Only post to GL once the invoice is "sent" — drafts stay off-ledger.
        if ((string) $invoice->status !== PmInvoice::STATUS_DRAFT) {
            PropertyAccountingPostingService::postInvoiceIssued($invoice, $request->user());
        }

        PmInvoiceEvent::record(
            (int) $invoice->id,
            PmInvoiceEvent::EVENT_ISSUED,
            $request->user()?->id,
            'Invoice manually issued',
            ['amount' => (float) $invoice->amount, 'status' => (string) $invoice->status]
        );

        return redirect()
            ->route('property.revenue.invoices.show', $invoice)
            ->with('success', 'Invoice '.$invoice->invoice_no.' created.');
    }

    public function invoices(Request $request): View|StreamedResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'type' => strtolower(trim((string) $request->query('type', ''))),
            'tenant_id' => (int) $request->query('tenant_id', 0),
            'unit_id' => (int) $request->query('unit_id', 0),
            'period' => trim((string) $request->query('period', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'due_from' => (string) $request->query('due_from', ''),
            'due_to' => (string) $request->query('due_to', ''),
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
                    ->orWhere('notes', 'like', '%'.$q.'%')
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
        if (in_array($filters['type'], [PmInvoice::TYPE_RENT, PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED], true)) {
            $baseQuery->where('invoice_type', $filters['type']);
        }
        if ($filters['tenant_id'] > 0) {
            $baseQuery->where('pm_tenant_id', $filters['tenant_id']);
        }
        if ($filters['unit_id'] > 0) {
            $baseQuery->where('property_unit_id', $filters['unit_id']);
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
        if ($filters['due_from'] !== '') {
            $baseQuery->whereDate('due_date', '>=', $filters['due_from']);
        }
        if ($filters['due_to'] !== '') {
            $baseQuery->whereDate('due_date', '<=', $filters['due_to']);
        }
        $sortMap = [
            'issue_date' => 'issue_date',
            'due_date' => 'due_date',
            'amount' => 'amount',
            'balance' => DB::raw('(amount - amount_paid)'),
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
                ['Invoice #', 'Type', 'Tenant', 'Unit', 'Period', 'Amount', 'Paid', 'Balance', 'Issued', 'Due', 'Status'],
                function () use ($items) {
                    foreach ($items as $i) {
                        yield [
                            (string) $i->invoice_no,
                            (string) ($i->invoice_type ?? 'rent'),
                            (string) ($i->tenant->name ?? ''),
                            (string) (($i->unit->property->name ?? '').'/'.($i->unit->label ?? '')),
                            $i->billing_period ?: ($i->issue_date?->format('Y-m') ?? ''),
                            number_format((float) $i->amount, 2, '.', ''),
                            number_format((float) $i->amount_paid, 2, '.', ''),
                            number_format(max(0, (float) $i->amount - (float) $i->amount_paid), 2, '.', ''),
                            $i->issue_date?->format('Y-m-d') ?? '',
                            $i->due_date?->format('Y-m-d') ?? '',
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
            $csrf = csrf_token();
            $statusAction = route('property.revenue.invoices.status', $i, false);
            $showAction = route('property.revenue.invoices.show', $i, false);
            $editAction = route('property.revenue.invoices.edit', $i, false);
            $destroyAction = route('property.revenue.invoices.destroy', $i, false);
            $pdfAction = route('property.revenue.invoices.pdf', $i, false);
            $balance = max(0, (float) $i->amount - (float) $i->amount_paid);

            $options = collect([
                PmInvoice::STATUS_DRAFT => 'Draft',
                PmInvoice::STATUS_SENT => 'Sent',
                PmInvoice::STATUS_CANCELLED => 'Cancelled',
            ])->map(function (string $label, string $value) use ($i): string {
                $selected = (string) $i->status === $value ? ' selected' : '';

                return '<option value="'.$value.'"'.$selected.'>'.$label.'</option>';
            })->implode('');

            $actions = new HtmlString(
                '<div class="relative inline-block text-left">'.
                    '<details>'.
                        '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                        '<div class="absolute right-0 z-30 mt-1 w-48 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                            '<a href="'.$showAction.'" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">View</a>'.
                            '<a href="'.$editAction.'" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Edit</a>'.
                            '<a href="'.$pdfAction.'" target="_blank" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Download PDF</a>'.
                            ($balance > 0 ? '<a href="'.$showAction.'#record-payment" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50">Record payment</a>' : '').
                            '<form method="post" action="'.$statusAction.'" class="block px-3 py-2">'.
                        '<input type="hidden" name="_token" value="'.$csrf.'">'.
                        '<input type="hidden" name="_method" value="patch">'.
                        '<select name="status" class="w-full rounded border border-slate-300 px-1.5 py-0.5 text-xs">'.
                            $options.
                        '</select>'.
                        '<button type="submit" class="mt-2 rounded bg-slate-800 px-2 py-0.5 text-[11px] font-semibold text-white hover:bg-slate-700">Save</button>'.
                            '</form>'.
                            '<form method="post" action="'.$destroyAction.'" class="block" data-swal-confirm="Delete this invoice? This only works for invoices without payments.">'.
                        '<input type="hidden" name="_token" value="'.$csrf.'">'.
                        '<input type="hidden" name="_method" value="delete">'.
                        '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-red-700 hover:bg-rose-50">Delete</button>'.
                            '</form>'.
                        '</div>'.
                    '</details>'.
                '</div>'
            );

            $typeLabel = ucfirst((string) ($i->invoice_type ?? 'rent'));
            $statusBadge = '<span class="rounded-full px-2 py-0.5 text-[11px] font-semibold '.self::statusBadgeClasses((string) $i->status).'">'.ucfirst((string) $i->status).'</span>';

            return [
                new HtmlString('<label class="inline-flex items-center"><input type="checkbox" name="ids[]" value="'.$i->id.'" form="property-invoices-bulk-form" class="rounded border-slate-300"><span class="sr-only">Select</span></label>'),
                new HtmlString('<a href="'.$showAction.'" class="font-semibold text-blue-700 hover:underline">'.$i->invoice_no.'</a>'),
                $typeLabel,
                $i->tenant->name ?? '—',
                ($i->unit->property->name ?? '—').'/'.($i->unit->label ?? '—'),
                $i->billing_period ?: ($i->issue_date?->format('Y-m') ?? '—'),
                number_format((float) $i->amount, 2),
                number_format($balance, 2),
                $i->issue_date?->format('Y-m-d') ?? '—',
                $i->due_date?->format('Y-m-d') ?? '—',
                new HtmlString($statusBadge),
                $actions,
            ];
        })->all();

        return view('property.agent.revenue.invoices', [
            'stats' => $stats,
            'columns' => ['Select', 'Invoice #', 'Type', 'Tenant', 'Unit', 'Period', 'Amount', 'Balance', 'Issued', 'Due', 'Status', 'Actions'],
            'tableRows' => $rows,
            'paginator' => $invoices,
            'filters' => [
                ...$filters,
                'sort' => is_string($sortBy) ? $sortBy : 'issue_date',
                'dir' => $dir,
                'per_page' => (string) $perPage,
            ],
            'leases' => PmLease::query()->with(['pmTenant', 'units'])->orderByDesc('start_date')->get(),
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'tenants' => PmTenant::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * B1: explicit Mark Sent action (separate from edit/status-change so it
     * has clear audit semantics and stamps sent_at + sent_by).
     */
    public function markSent(Request $request, PmInvoice $invoice): RedirectResponse
    {
        if (! in_array((string) $invoice->status, [PmInvoice::STATUS_DRAFT, PmInvoice::STATUS_CANCELLED], true)) {
            return back()->with('info', 'Invoice is already sent.');
        }

        $previous = (string) $invoice->status;
        DB::transaction(function () use ($invoice, $request) {
            $invoice->update([
                'status' => PmInvoice::STATUS_SENT,
                'sent_at' => now(),
                'sent_by_user_id' => $request->user()?->id,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'cancelled_reason' => null,
            ]);
            $invoice->refreshComputedStatus();

            PropertyAccountingPostingService::postInvoiceIssued($invoice, $request->user());
            PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_SENT, $request->user()?->id);
        });

        if ($previous === PmInvoice::STATUS_CANCELLED) {
            PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_REOPENED, $request->user()?->id, 'Reopened from cancelled → sent');
        }

        return back()->with('success', 'Invoice '.$invoice->invoice_no.' marked as sent.');
    }

    public function cancel(Request $request, PmInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'cancelled_reason' => ['nullable', 'string', 'max:255'],
        ]);
        if ((float) $invoice->amount_paid > 0) {
            return back()->withErrors(['status' => 'Cannot cancel an invoice that already has payments. Issue a credit note instead.']);
        }
        if ((string) $invoice->status === PmInvoice::STATUS_CANCELLED) {
            return back()->with('info', 'Invoice is already cancelled.');
        }

        DB::transaction(function () use ($invoice, $request, $data) {
            $invoice->update([
                'status' => PmInvoice::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $request->user()?->id,
                'cancelled_reason' => $data['cancelled_reason'] ?? null,
            ]);
            PropertyAccountingPostingService::reverseInvoiceIssued($invoice, $request->user(), $data['cancelled_reason'] ?? 'Invoice cancelled');
            PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_CANCELLED, $request->user()?->id, $data['cancelled_reason'] ?? null);
        });

        return back()->with('success', 'Invoice '.$invoice->invoice_no.' cancelled.');
    }

    public function reopen(Request $request, PmInvoice $invoice): RedirectResponse
    {
        if ((string) $invoice->status !== PmInvoice::STATUS_CANCELLED) {
            return back()->with('info', 'Invoice is not cancelled.');
        }

        DB::transaction(function () use ($invoice, $request) {
            $invoice->update([
                'status' => PmInvoice::STATUS_SENT,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'cancelled_reason' => null,
            ]);
            $invoice->refreshComputedStatus();
            PropertyAccountingPostingService::postInvoiceIssued($invoice, $request->user());
            PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_REOPENED, $request->user()?->id);
        });

        return back()->with('success', 'Invoice '.$invoice->invoice_no.' reopened.');
    }

    /**
     * B3: Quick record-payment-against-this-invoice. Bypasses the full
     * payments form so an agent can settle from the invoice page.
     */
    public function recordPayment(Request $request, PmInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'channel' => ['required', 'in:mpesa,bank,cash,card,cheque'],
            'external_ref' => ['nullable', 'string', 'max:128'],
            'paid_at' => ['nullable', 'date'],
        ]);

        if ((string) $invoice->status === PmInvoice::STATUS_CANCELLED) {
            return back()->withErrors(['amount' => 'Cannot record payment on a cancelled invoice.']);
        }

        $remaining = (float) $invoice->amount - (float) $invoice->amount_paid;
        if ((float) $data['amount'] > $remaining + 0.0001) {
            return back()->withErrors(['amount' => 'Amount exceeds open balance on invoice (KES '.number_format($remaining, 2).').'])->withInput();
        }
        if ($data['channel'] !== 'cash' && blank($data['external_ref'] ?? null)) {
            return back()->withErrors(['external_ref' => 'Reference is required for non-cash payments.'])->withInput();
        }

        DB::transaction(function () use ($invoice, $data, $request) {
            $payment = PmPayment::query()->create([
                'pm_tenant_id' => $invoice->pm_tenant_id,
                'channel' => $data['channel'],
                'amount' => $data['amount'],
                'external_ref' => $data['external_ref'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'status' => PmPayment::STATUS_COMPLETED,
                'meta' => null,
            ]);
            if (\Illuminate\Support\Facades\Schema::hasColumn('payments', 'agent_user_id')
                || \Illuminate\Support\Facades\Schema::hasColumn('pm_payments', 'agent_user_id')) {
                $payment->forceFill(['agent_user_id' => $invoice->agent_user_id])->save();
            }

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

            PmInvoiceEvent::record(
                (int) $invoice->id,
                PmInvoiceEvent::EVENT_PARTIALLY_PAID,
                $request->user()?->id,
                'Payment recorded: KES '.number_format((float) $data['amount'], 2).' via '.$data['channel'],
                [
                    'payment_id' => (int) $payment->id,
                    'amount' => (float) $data['amount'],
                    'channel' => $data['channel'],
                ]
            );
        });

        return back()->with('success', 'Payment recorded for invoice '.$invoice->invoice_no.'.');
    }

    /**
     * B2: streamed PDF download for a single invoice (agent or tenant-authed
     * caller). Public-share variant lives at `publicPdf`.
     */
    public function downloadPdf(PmInvoice $invoice): StreamedResponse|Response
    {
        return $this->renderInvoicePdf($invoice, 'attachment');
    }

    public function printable(PmInvoice $invoice): View
    {
        $invoice->loadMissing(['tenant', 'unit.property', 'items']);
        return view('property.agent.revenue.invoice_print', [
            'invoice' => $invoice,
            'branding' => $this->branding(),
        ]);
    }

    /**
     * B4: send invoice to tenant via email and/or SMS. Email gets the PDF
     * attached. SMS gets a short summary + the public share link.
     */
    public function sendToTenant(Request $request, PmInvoice $invoice, BulkSmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms,both'],
            'override_email' => ['nullable', 'email'],
            'override_phone' => ['nullable', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        if ((string) $invoice->status === PmInvoice::STATUS_DRAFT) {
            return back()->withErrors(['channel' => 'Draft invoices cannot be sent. Mark as Sent first.']);
        }

        $invoice->loadMissing(['tenant:id,name,phone,email', 'unit.property:id,name']);
        $tenant = $invoice->tenant;
        $shareToken = $invoice->ensureShareToken();
        $publicUrl = URL::to(route('property.invoices.public.show', ['token' => $shareToken], false));

        $defaultMessage = sprintf(
            "Hello %s, your invoice %s for KES %s is due %s. View / pay: %s",
            $tenant?->name ?? 'Tenant',
            $invoice->invoice_no,
            number_format((float) $invoice->amount - (float) $invoice->amount_paid, 2),
            optional($invoice->due_date)->format('Y-m-d') ?? '',
            $publicUrl,
        );
        $body = trim((string) ($data['message'] ?? '')) !== '' ? $data['message'] : $defaultMessage;

        $emailedCount = 0;
        $smsedCount = 0;
        $errors = [];

        if (in_array($data['channel'], ['email', 'both'], true)) {
            $emailTo = trim((string) ($data['override_email'] ?? $tenant?->email ?? ''));
            if ($emailTo === '') {
                $errors[] = 'Tenant has no email on file.';
            } else {
                try {
                    $pdf = $this->buildPdfBinary($invoice);
                    Mail::raw($body, function ($m) use ($emailTo, $invoice, $pdf) {
                        $m->to($emailTo)
                            ->subject('Invoice '.$invoice->invoice_no)
                            ->attachData($pdf, 'invoice-'.$invoice->invoice_no.'.pdf', [
                                'mime' => 'application/pdf',
                            ]);
                    });
                    PmMessageLog::query()->create([
                        'user_id' => $request->user()?->id,
                        'channel' => 'email',
                        'to_address' => $emailTo,
                        'subject' => 'Invoice '.$invoice->invoice_no,
                        'body' => $body,
                        'status' => 'sent',
                    ]);
                    PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_EMAILED, $request->user()?->id, 'Email sent to '.$emailTo);
                    $emailedCount++;
                } catch (\Throwable $e) {
                    report($e);
                    $errors[] = 'Email failed: '.$e->getMessage();
                }
            }
        }

        if (in_array($data['channel'], ['sms', 'both'], true)) {
            $phone = trim((string) ($data['override_phone'] ?? $tenant?->phone ?? ''));
            if ($phone === '') {
                $errors[] = 'Tenant has no phone on file.';
            } else {
                $result = $sms->sendNow($body, [$phone], $request->user()?->id, null, 'property');
                if (($result['ok'] ?? false) === true) {
                    PmMessageLog::query()->create([
                        'user_id' => $request->user()?->id,
                        'channel' => 'sms',
                        'to_address' => $phone,
                        'body' => $body,
                        'status' => 'sent',
                    ]);
                    PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_SMS_SENT, $request->user()?->id, 'SMS sent to '.$phone);
                    $smsedCount++;
                } else {
                    $errors[] = 'SMS failed: '.($result['error'] ?? 'unknown');
                }
            }
        }

        // Once we have a successful send, mark the invoice as sent if it
        // wasn't already (helps with the "I issued but didn't notify" flow).
        if (($emailedCount + $smsedCount) > 0 && empty($invoice->sent_at)) {
            $invoice->update(['sent_at' => now(), 'sent_by_user_id' => $request->user()?->id]);
        }

        if ($errors && ($emailedCount + $smsedCount) === 0) {
            return back()->withErrors(['channel' => implode(' ', $errors)]);
        }

        $parts = [];
        if ($emailedCount) {
            $parts[] = 'email sent';
        }
        if ($smsedCount) {
            $parts[] = 'SMS sent';
        }
        $msg = 'Invoice '.$invoice->invoice_no.': '.implode(' + ', $parts).'.';
        if ($errors) {
            $msg .= ' Issues: '.implode(' ', $errors);
        }

        return back()->with('success', $msg);
    }

    /**
     * Issue a credit note that offsets a (paid or partially-paid) invoice.
     * Creates a linked invoice row with kind=credit_note and a negative-like
     * journal posting handled by reverseInvoiceIssued on the original.
     */
    public function createCreditNote(Request $request, PmInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($invoice->isCreditNote()) {
            return back()->withErrors(['amount' => 'You cannot issue a credit note against a credit note.']);
        }
        $maxRefundable = (float) $invoice->amount;
        if ((float) $data['amount'] > $maxRefundable + 0.0001) {
            return back()->withErrors(['amount' => 'Credit note amount cannot exceed the invoice amount.']);
        }

        $creditNote = DB::transaction(function () use ($invoice, $data, $request) {
            $invoiceNo = PmInvoice::nextInvoiceNumber();
            $amount = -1 * abs((float) $data['amount']); // negative for clarity in reports

            $cn = PmInvoice::query()->create([
                'pm_lease_id' => $invoice->pm_lease_id,
                'property_unit_id' => $invoice->property_unit_id,
                'pm_tenant_id' => $invoice->pm_tenant_id,
                'agent_user_id' => $invoice->agent_user_id,
                'created_by_user_id' => $request->user()?->id,
                'invoice_no' => $invoiceNo,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'amount' => $amount,
                'subtotal_amount' => $amount,
                'total_amount' => $amount,
                'amount_paid' => 0,
                'status' => PmInvoice::STATUS_SENT,
                'sent_at' => now(),
                'sent_by_user_id' => $request->user()?->id,
                'invoice_type' => $invoice->invoice_type,
                'invoice_kind' => PmInvoice::KIND_CREDIT_NOTE,
                'original_invoice_id' => $invoice->id,
                'description' => 'Credit note for '.$invoice->invoice_no.($data['reason'] ? ' — '.$data['reason'] : ''),
            ]);

            PmInvoiceEvent::record(
                (int) $invoice->id,
                PmInvoiceEvent::EVENT_CREDIT_NOTE_ISSUED,
                $request->user()?->id,
                'Credit note '.$cn->invoice_no.' issued for KES '.number_format(abs($amount), 2),
                ['credit_note_id' => (int) $cn->id, 'reason' => $data['reason'] ?? null]
            );
            PmInvoiceEvent::record(
                (int) $cn->id,
                PmInvoiceEvent::EVENT_ISSUED,
                $request->user()?->id,
                'Credit note issued against '.$invoice->invoice_no
            );

            return $cn;
        });

        return redirect()
            ->route('property.revenue.invoices.show', $creditNote)
            ->with('success', 'Credit note '.$creditNote->invoice_no.' issued.');
    }

    /**
     * Public, no-auth invoice view (used by tenants when they receive the
     * SMS / email link). Token-based; bypasses agent scope.
     */
    public function publicShow(string $token): View
    {
        $invoice = $this->resolvePublic($token);
        $invoice->loadMissing(['tenant', 'unit.property', 'items']);
        return view('property.public.invoice_show', [
            'invoice' => $invoice,
            'branding' => $this->branding(),
        ]);
    }

    public function publicPdf(string $token): StreamedResponse|Response
    {
        $invoice = $this->resolvePublic($token);
        return $this->renderInvoicePdf($invoice, 'inline');
    }

    /**
     * Render the configured invoice view to a streamed PDF (Dompdf).
     */
    private function renderInvoicePdf(PmInvoice $invoice, string $disposition = 'attachment'): StreamedResponse|Response
    {
        $invoice->loadMissing(['tenant', 'unit.property', 'items']);

        $html = view('property.agent.revenue.invoice_print', [
            'invoice' => $invoice,
            'branding' => $this->branding(),
        ])->render();

        try {
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $binary = $dompdf->output();
        } catch (\Throwable $e) {
            report($e);
            // Fall back to HTML so the agent at least gets something.
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $filename = 'invoice-'.$invoice->invoice_no.'.pdf';
        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }

    private function buildPdfBinary(PmInvoice $invoice): string
    {
        $invoice->loadMissing(['tenant', 'unit.property', 'items']);
        $html = view('property.agent.revenue.invoice_print', [
            'invoice' => $invoice,
            'branding' => $this->branding(),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function resolvePublic(string $token): PmInvoice
    {
        $invoice = PmInvoice::query()
            ->withoutGlobalScopes()
            ->where('share_token', $token)
            ->whereNull('deleted_at')
            ->first();

        if (! $invoice) {
            abort(404);
        }
        return $invoice;
    }

    private function branding(): array
    {
        $b = PropertyPortalSetting::query()->where('key', 'branding')->value('value');
        $decoded = is_string($b) ? json_decode($b, true) : (is_array($b) ? $b : []);

        $defaults = [
            'company_name' => 'Property Manager',
            'address' => '',
            'phone' => '',
            'email' => '',
            'logo_url' => '',
            'colour' => '#1e40af',
            'footer_note' => 'Thank you for your business.',
        ];
        return array_merge($defaults, is_array($decoded) ? $decoded : []);
    }

    private static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            PmInvoice::STATUS_PAID => 'bg-emerald-100 text-emerald-700',
            PmInvoice::STATUS_PARTIAL => 'bg-amber-100 text-amber-700',
            PmInvoice::STATUS_OVERDUE => 'bg-red-100 text-red-700',
            PmInvoice::STATUS_CANCELLED => 'bg-slate-200 text-slate-600',
            PmInvoice::STATUS_DRAFT => 'bg-slate-100 text-slate-700',
            default => 'bg-blue-100 text-blue-700',
        };
    }
}
