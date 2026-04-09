<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmMaintenanceJob;
use App\Models\PmAccountingEntry;
use App\Models\PmPortalAction;
use App\Models\PmVendor;
use App\Models\User;
use App\Services\Property\PropertyChartSeries;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PmVendorWebController extends Controller
{
    public function directory(): View
    {
        $vendors = PmVendor::query()->orderBy('name')->get();

        $stats = [
            ['label' => 'Vendors', 'value' => (string) $vendors->count(), 'hint' => ''],
            ['label' => 'Active', 'value' => (string) $vendors->where('status', 'active')->count(), 'hint' => ''],
            ['label' => 'Inactive', 'value' => (string) $vendors->where('status', '!=', 'active')->count(), 'hint' => ''],
        ];

        $rows = $vendors->map(function (PmVendor $v) {
            $nextStatus = $v->status === 'active' ? 'inactive' : 'active';
            $statusBtn = $nextStatus === 'active' ? 'Activate' : 'Deactivate';

            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-1">'.
                '<a href="'.route('property.vendors.show', $v).'" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">View</a>'.
                '<a href="'.route('property.vendors.edit', $v).'" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">Edit</a>'.
                '<form method="POST" action="'.route('property.vendors.status', $v).'" class="inline-flex">'.csrf_field().
                '<input type="hidden" name="status" value="'.$nextStatus.'" />'.
                '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">'.$statusBtn.'</button>'.
                '</form>'.
                '<a href="'.route('property.vendors.quotes').'" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">Quotes</a>'.
                '</div>'
            );

            return [
                $v->name,
                $v->category ?? '—',
                $v->phone ?? '—',
                $v->email ?? '—',
                'On-file',
                $v->rating !== null ? number_format((float) $v->rating, 1) : '—',
                ucfirst($v->status),
                $actions,
            ];
        })->all();

        return view('property.agent.vendors.directory', [
            'stats' => $stats,
            'columns' => ['Vendor', 'Category', 'Contact', 'Payment terms', 'Insurance until', 'Rating', 'Status', 'Actions'],
            'tableRows' => $rows,
        ]);
    }

    public function show(PmVendor $vendor): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property'])
            ->where('pm_vendor_id', $vendor->id)
            ->latest('id')
            ->limit(300)
            ->get();

        $completed = $jobs->where('status', 'done');
        $completedAmount = (float) $completed->sum(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0));

        $references = $jobs->map(fn (PmMaintenanceJob $j) => 'MNT-'.$j->id)->all();
        $allocatedRefs = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_expense')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->whereIn('reference', $references)
            ->pluck('reference')
            ->flip();
        $paidRefs = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_payment')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->whereIn('reference', $references)
            ->pluck('reference')
            ->flip();
        $paymentEntries = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_payment')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->whereIn('reference', $references)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();
        $allocatedAmount = (float) $jobs
            ->filter(fn (PmMaintenanceJob $j) => isset($allocatedRefs['MNT-'.$j->id]))
            ->sum(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0));
        $paidAmount = (float) $paymentEntries->sum(fn (PmAccountingEntry $e) => (float) $e->amount);
        $outstandingAmount = max(0.0, $allocatedAmount - $paidAmount);
        $recorderNames = User::query()
            ->whereIn('id', $paymentEntries->pluck('recorded_by_user_id')->filter()->unique()->values())
            ->pluck('name', 'id');

        $rows = $jobs->map(function (PmMaintenanceJob $j) use ($allocatedRefs, $paidRefs, $vendor) {
            $ref = 'MNT-'.$j->id;
            $allocated = isset($allocatedRefs[$ref]);
            $paid = isset($paidRefs[$ref]);
            $status = ucfirst(str_replace('_', ' ', (string) $j->status));
            $allocationState = $allocated
                ? new HtmlString('<span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Allocated</span>')
                : new HtmlString('<span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Not allocated</span>');
            $payoutState = $paid
                ? new HtmlString('<span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">Paid</span>')
                : new HtmlString('<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Unpaid</span>');
            $action = '—';
            if ($allocated && ! $paid && $j->status === 'done' && (float) ($j->quote_amount ?? 0) > 0) {
                $action = new HtmlString(
                    '<form method="POST" action="'.route('property.vendors.jobs.mark_paid', ['vendor' => $vendor, 'job' => $j], false).'" class="inline-flex flex-wrap items-center gap-1" data-swal-title="Mark job as paid?" data-swal-confirm="This will post settlement entries for job #'.$j->id.'. Continue?" data-swal-confirm-text="Yes, mark paid">'
                    .csrf_field()
                    .'<input type="date" name="paid_date" value="'.now()->toDateString().'" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />'
                    .'<input type="text" name="payment_note" placeholder="Ref / note (optional)" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />'
                    .'<button type="submit" class="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50">Mark paid</button>'
                    .'</form>'
                );
            }

            return [
                '#'.$j->id,
                optional($j->request?->unit?->property)->name.'/'.(optional($j->request?->unit)->label ?? '—'),
                $j->request?->category ?? '—',
                $j->quote_amount !== null ? PropertyMoney::kes((float) $j->quote_amount) : '—',
                $status,
                $j->completed_at?->format('Y-m-d') ?? '—',
                $allocationState,
                $payoutState,
                $action,
            ];
        })->all();

        return view('property.agent.vendors.show', [
            'vendor' => $vendor,
            'stats' => [
                ['label' => 'Total jobs', 'value' => (string) $jobs->count(), 'hint' => 'All statuses'],
                ['label' => 'Completed jobs', 'value' => (string) $completed->count(), 'hint' => 'Done'],
                ['label' => 'Completed earnings', 'value' => PropertyMoney::kes($completedAmount), 'hint' => 'Quoted totals'],
                ['label' => 'Allocated entries', 'value' => (string) $jobs->filter(fn (PmMaintenanceJob $j) => isset($allocatedRefs['MNT-'.$j->id]))->count(), 'hint' => 'Payables posted'],
                ['label' => 'Paid jobs', 'value' => (string) $jobs->filter(fn (PmMaintenanceJob $j) => isset($paidRefs['MNT-'.$j->id]))->count(), 'hint' => 'Vendor settled'],
                ['label' => 'Paid amount', 'value' => PropertyMoney::kes($paidAmount), 'hint' => 'Settled to vendor'],
                ['label' => 'Outstanding payable', 'value' => PropertyMoney::kes($outstandingAmount), 'hint' => 'Allocated minus paid'],
            ],
            'columns' => ['Job', 'Unit', 'Category', 'Quote', 'Status', 'Completed', 'Allocation', 'Payout', 'Actions'],
            'tableRows' => $rows,
            'paymentEntries' => $paymentEntries,
            'recorderNames' => $recorderNames,
        ]);
    }

    public function markJobPaid(Request $request, PmVendor $vendor, PmMaintenanceJob $job): RedirectResponse
    {
        $data = $request->validate([
            'paid_date' => ['nullable', 'date'],
            'payment_note' => ['nullable', 'string', 'max:255'],
        ]);

        if ((int) $job->pm_vendor_id !== (int) $vendor->id) {
            return back()->withErrors(['vendor' => 'This job is not assigned to the selected vendor.']);
        }

        $amount = (float) ($job->quote_amount ?? 0);
        if ($amount <= 0 || $job->status !== 'done') {
            return back()->withErrors(['job' => 'Only completed jobs with quote amount can be marked paid.']);
        }

        $reference = 'MNT-'.$job->id;
        $allocatedExists = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_expense')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->where('reference', $reference)
            ->exists();
        if (! $allocatedExists) {
            return back()->withErrors(['job' => 'This job is not allocated yet. Allocate expense before marking paid.']);
        }

        $alreadyPaid = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_payment')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->where('reference', $reference)
            ->exists();
        if ($alreadyPaid) {
            return back()->with('success', 'Vendor payment was already marked.');
        }

        $entryDate = ! empty($data['paid_date']) ? (string) $data['paid_date'] : now()->toDateString();
        $note = trim((string) ($data['payment_note'] ?? ''));
        $description = 'Vendor payment settled'.($note !== '' ? ' - '.$note : '');

        PmAccountingEntry::query()->create([
            'entry_date' => $entryDate,
            'property_id' => optional(optional($job->request)->unit)->property_id,
            'recorded_by_user_id' => $request->user()->id,
            'account_name' => 'Accounts Payable',
            'category' => PmAccountingEntry::CATEGORY_LIABILITY,
            'entry_type' => PmAccountingEntry::TYPE_DEBIT,
            'amount' => $amount,
            'reference' => $reference,
            'description' => $description,
            'source_key' => 'maintenance_payment',
        ]);
        PmAccountingEntry::query()->create([
            'entry_date' => $entryDate,
            'property_id' => optional(optional($job->request)->unit)->property_id,
            'recorded_by_user_id' => $request->user()->id,
            'account_name' => 'Cash / Bank',
            'category' => PmAccountingEntry::CATEGORY_ASSET,
            'entry_type' => PmAccountingEntry::TYPE_CREDIT,
            'amount' => $amount,
            'reference' => $reference,
            'description' => $description,
            'source_key' => 'maintenance_payment',
        ]);

        return back()->with('success', 'Vendor marked as paid for job #'.$job->id.'.');
    }

    public function payOutstanding(Request $request, PmVendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'paid_date' => ['nullable', 'date'],
            'payment_note' => ['nullable', 'string', 'max:255'],
            'confirm_phrase' => ['required', 'string'],
        ]);
        if (strtoupper(trim((string) $data['confirm_phrase'])) !== 'PAY') {
            return back()->withErrors(['confirm_phrase' => 'Type PAY to confirm bulk settlement.'])->withInput();
        }

        $entryDate = ! empty($data['paid_date']) ? (string) $data['paid_date'] : now()->toDateString();
        $note = trim((string) ($data['payment_note'] ?? ''));
        $description = 'Vendor payment settled (bulk)'.($note !== '' ? ' - '.$note : '');

        $jobs = PmMaintenanceJob::query()
            ->with('request.unit')
            ->where('pm_vendor_id', $vendor->id)
            ->where('status', 'done')
            ->whereNotNull('quote_amount')
            ->get()
            ->filter(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0) > 0);

        if ($jobs->isEmpty()) {
            return back()->withErrors(['vendor' => 'No completed quoted jobs available to settle.']);
        }

        $references = $jobs->map(fn (PmMaintenanceJob $j) => 'MNT-'.$j->id)->values();
        $allocatedRefs = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_expense')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->whereIn('reference', $references)
            ->pluck('reference')
            ->flip();
        $paidRefs = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_payment')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->whereIn('reference', $references)
            ->pluck('reference')
            ->flip();

        $eligible = $jobs->filter(function (PmMaintenanceJob $j) use ($allocatedRefs, $paidRefs) {
            $ref = 'MNT-'.$j->id;
            return isset($allocatedRefs[$ref]) && ! isset($paidRefs[$ref]);
        })->values();

        if ($eligible->isEmpty()) {
            return back()->withErrors(['vendor' => 'No outstanding allocated jobs found to settle.']);
        }

        foreach ($eligible as $job) {
            $amount = (float) ($job->quote_amount ?? 0);
            $reference = 'MNT-'.$job->id;
            $propertyId = optional(optional($job->request)->unit)->property_id;

            PmAccountingEntry::query()->create([
                'entry_date' => $entryDate,
                'property_id' => $propertyId,
                'recorded_by_user_id' => $request->user()->id,
                'account_name' => 'Accounts Payable',
                'category' => PmAccountingEntry::CATEGORY_LIABILITY,
                'entry_type' => PmAccountingEntry::TYPE_DEBIT,
                'amount' => $amount,
                'reference' => $reference,
                'description' => $description,
                'source_key' => 'maintenance_payment',
            ]);
            PmAccountingEntry::query()->create([
                'entry_date' => $entryDate,
                'property_id' => $propertyId,
                'recorded_by_user_id' => $request->user()->id,
                'account_name' => 'Cash / Bank',
                'category' => PmAccountingEntry::CATEGORY_ASSET,
                'entry_type' => PmAccountingEntry::TYPE_CREDIT,
                'amount' => $amount,
                'reference' => $reference,
                'description' => $description,
                'source_key' => 'maintenance_payment',
            ]);
        }

        return back()->with('success', 'Settled '.count($eligible).' outstanding vendor job payment(s).');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
        ]);

        $vendor = PmVendor::query()->create([
            ...$data,
            'agent_user_id' => (int) $request->user()->id,
        ]);

        $nextSteps = [
            'title' => 'Vendor saved',
            'message' => 'Next, assign this vendor to maintenance jobs or include them when preparing RFQs.',
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'category' => $vendor->category,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
            ],
            'actions' => [
                [
                    'label' => 'Create RFQ draft',
                    'href' => route('property.vendors.bidding_create', absolute: false),
                    'kind' => 'primary',
                    'icon' => 'fa-solid fa-file-pen',
                    'turbo_frame' => 'property-main',
                ],
                [
                    'label' => 'View maintenance jobs',
                    'href' => route('property.vendors.work_records', absolute: false),
                    'kind' => 'secondary',
                    'icon' => 'fa-solid fa-screwdriver-wrench',
                    'turbo_frame' => 'property-main',
                ],
            ],
        ];

        return back()
            ->with('success', 'Vendor saved.')
            ->with('next_steps', $nextSteps);
    }

    public function storeJson(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $vendor = PmVendor::query()->create([
            ...$data,
            'status' => 'active',
            'rating' => null,
            'agent_user_id' => (int) $request->user()->id,
        ]);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $vendor->id,
                'label' => $vendor->name.($vendor->phone ? ' ('.$vendor->phone.')' : ''),
            ],
            'message' => 'Vendor created.',
        ]);
    }

    public function edit(PmVendor $vendor): View
    {
        return view('property.agent.vendors.edit', [
            'vendor' => $vendor,
        ]);
    }

    public function update(Request $request, PmVendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
        ]);

        $vendor->update($data);

        return back()->with('success', 'Vendor updated.');
    }

    public function updateStatus(Request $request, PmVendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);
        $vendor->update([
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Vendor status updated.');
    }

    public function createBiddingRfqForm(): View
    {
        return view('property.agent.vendors.bidding_create');
    }

    public function storeBiddingRfq(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'string', 'max:5000'],
            'deadline' => ['nullable', 'date'],
            'access_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $ctx = array_filter([
            'property_unit' => $data['property_unit'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'access_notes' => $data['access_notes'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        PmPortalAction::query()->create([
            'user_id' => $request->user()->id,
            'portal_role' => 'agent',
            'action_key' => 'create_vendor_rfq',
            'notes' => $data['scope'],
            'context' => $ctx !== [] ? $ctx : null,
        ]);

        return redirect()
            ->route('property.vendors.bidding')
            ->with('success', 'RFQ draft saved. Vendors can be invited from your workflow when RFQs are wired to the database.');
    }

    public function bidding(): View
    {
        $rfqs = PmPortalAction::query()
            ->where('action_key', 'create_vendor_rfq')
            ->where('user_id', $request->user()->id)
            ->with('user')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $stats = [
            ['label' => 'RFQ drafts', 'value' => (string) $rfqs->count(), 'hint' => 'Portal actions'],
            ['label' => 'MTD', 'value' => (string) $rfqs->where('created_at', '>=', now()->startOfMonth())->count(), 'hint' => ''],
        ];

        $rows = $rfqs->map(fn (PmPortalAction $a) => [
            '#'.$a->id,
            $a->context['property_unit'] ?? '—',
            Str::limit($a->notes, 48),
            ($a->context['deadline'] ?? '—'),
            'Pending invites',
            'Pending quotes',
            'Draft',
            new HtmlString('<a href="'.route('property.vendors.quotes').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open quotes</a>'),
        ])->all();

        return view('property.agent.vendors.bidding', [
            'stats' => $stats,
            'columns' => ['RFQ #', 'Property / unit', 'Scope', 'Deadline', 'Invited', 'Quotes', 'Status', 'Actions'],
            'tableRows' => $rows,
        ]);
    }

    public function quotes(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property', 'vendor'])
            ->whereNotNull('quote_amount')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $stats = [
            ['label' => 'Jobs with quote', 'value' => (string) $jobs->count(), 'hint' => 'Any status'],
            ['label' => 'With vendor', 'value' => (string) $jobs->filter(fn ($j) => $j->pm_vendor_id)->count(), 'hint' => ''],
        ];

        $rows = $jobs->map(function (PmMaintenanceJob $j) {
            $action = '—';
            if ($j->status === 'quoted' && $j->pm_vendor_id) {
                $action = new HtmlString(
                    '<form method="POST" action="'.route('property.vendors.quotes.award', $j).'" data-swal-title="Award quote?" data-swal-confirm="Award this quote and move job to Approved?" data-swal-confirm-text="Yes, award">'.
                    csrf_field().
                    '<button type="submit" class="rounded border border-blue-300 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-50">Award</button>'.
                    '</form>'
                );
            } elseif (in_array($j->status, ['approved', 'in_progress', 'done'], true)) {
                $action = 'Awarded';
            }

            return [
                '#'.$j->id,
                $j->vendor?->name ?? '—',
                $j->request->unit->property->name.'/'.$j->request->unit->label,
                $j->quote_amount !== null ? PropertyMoney::kes((float) $j->quote_amount) : '—',
                $j->created_at->format('Y-m-d'),
                ucfirst(str_replace('_', ' ', $j->status)),
                ((int) $j->created_at->diffInDays(now())).' days',
                $action,
            ];
        })->all();

        return view('property.agent.vendors.quotes', [
            'stats' => $stats,
            'columns' => ['Job', 'Vendor', 'Unit', 'Quote', 'Date', 'Status', 'Lead time', 'Select'],
            'tableRows' => $rows,
        ]);
    }

    public function awardQuote(PmMaintenanceJob $job): RedirectResponse
    {
        if (! $job->pm_vendor_id) {
            return back()->with('error', 'Assign a vendor before awarding a quote.');
        }
        if ($job->quote_amount === null) {
            return back()->with('error', 'Add a quote amount before awarding.');
        }
        if (in_array($job->status, ['cancelled', 'done'], true)) {
            return back()->with('error', 'This job cannot be awarded in its current status.');
        }

        $job->update(['status' => 'approved']);

        return back()->with('success', 'Quote awarded and job moved to Approved.');
    }

    public function performance(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with('vendor')
            ->whereNotNull('pm_vendor_id')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $byVendor = $jobs->groupBy('pm_vendor_id');
        $rows = $byVendor->map(function ($group) {
            /** @var Collection<int, PmMaintenanceJob> $group */
            $v = $group->first()->vendor;
            $done = $group->where('status', 'done')->count();
            $sum = (float) $group->sum(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0));

            return [
                $v?->name ?? '—',
                (string) $group->count(),
                (string) $done,
                PropertyMoney::kes($sum),
                $v?->rating !== null ? number_format((float) $v->rating, 1) : '—',
                $group->count() > 0 ? (string) round(($done / $group->count()) * 100, 1).'%' : '—',
            ];
        })->values()->all();

        $top = $rows[0][0] ?? '—';
        $doneRate = $jobs->isNotEmpty()
            ? round(100 * $jobs->where('status', 'done')->count() / $jobs->count(), 1)
            : null;

        return view('property.agent.vendors.performance', [
            'stats' => [
                ['label' => 'Vendors used', 'value' => (string) count($rows), 'hint' => 'With jobs'],
                ['label' => 'Jobs sampled', 'value' => (string) $jobs->count(), 'hint' => ''],
                ['label' => 'Top vendor', 'value' => $top, 'hint' => 'By job count'],
                ['label' => 'Done / all', 'value' => $doneRate !== null ? $doneRate.'%' : '—', 'hint' => 'Completion share'],
            ],
            'columns' => ['Vendor', 'Jobs', 'Completed', 'Quoted total', 'Rating', 'SLA'],
            'tableRows' => $rows,
            'scatterPoints' => PropertyChartSeries::vendorScatterPoints(),
        ]);
    }

    public function workRecords(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property', 'vendor'])
            ->where('status', 'done')
            ->orderByDesc('completed_at')
            ->limit(200)
            ->get();

        $rows = $jobs->map(fn (PmMaintenanceJob $j) => [
            $j->completed_at?->format('Y-m-d') ?? '—',
            '#'.$j->id,
            $j->vendor?->name ?? '—',
            $j->request->unit->property->name.'/'.$j->request->unit->label,
            $j->quote_amount !== null ? PropertyMoney::kes((float) $j->quote_amount) : '—',
            Str::limit((string) $j->notes, 40),
            new HtmlString('<a href="'.route('property.maintenance.history').'" class="text-indigo-600 hover:text-indigo-700 font-medium">History</a>'),
        ])->all();

        return view('property.agent.vendors.work_records', [
            'stats' => [
                ['label' => 'Records', 'value' => (string) $jobs->count(), 'hint' => 'Completed'],
            ],
            'columns' => ['Date', 'Job', 'Vendor', 'Unit', 'Amount', 'Notes', 'Actions'],
            'tableRows' => $rows,
        ]);
    }
}
