<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmVendor;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Support\CsvExport;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyMoney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PmMaintenanceWebController extends Controller
{
    public function requests(Request $request): View
    {
        $maintenanceEnabled = PropertyPortalSetting::getValue('form_maintenance_enabled', '1') === '1';
        $workflowAutoAssignTickets = PropertyPortalSetting::getValue('workflow_auto_assign_tickets', '0') === '1';
        $filters = $request->only(['q', 'status', 'urgency', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $requests = $this->requestsQuery($filters)->limit(400)->get();

        $stats = [
            ['label' => 'Open', 'value' => (string) $requests->where('status', 'open')->count(), 'hint' => ''],
            ['label' => 'In progress', 'value' => (string) $requests->where('status', 'in_progress')->count(), 'hint' => ''],
            ['label' => 'Done', 'value' => (string) $requests->where('status', 'done')->count(), 'hint' => ''],
            ['label' => 'Total', 'value' => (string) $requests->count(), 'hint' => 'Listed'],
        ];

        $rows = $requests->map(function (PmMaintenanceRequest $r) {
            $actions = new HtmlString(
                '<a href="'.route('property.maintenance.requests.edit', $r).'" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Edit</a>'
            );
            if (! in_array($r->status, ['done', 'closed'], true)) {
                $actions = new HtmlString(
                    '<div class="flex flex-wrap items-center gap-1">'.
                    '<a href="'.route('property.maintenance.requests.edit', $r).'" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Edit</a>'.
                    '<form method="POST" action="'.route('property.maintenance.requests.status', ['requestItem' => $r]).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="in_progress" />'.
                    '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Triage</button>'.
                    '</form>'.
                    '<form method="POST" action="'.route('property.maintenance.requests.status', ['requestItem' => $r]).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="done" />'.
                    '<button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Resolve</button>'.
                    '</form>'.
                    '</div>'
                );
            }

            return [
                '#'.$r->id,
                $r->unit->property->name.'/'.$r->unit->label,
                $r->category,
                Str::limit($r->description, 40),
                $r->created_at->format('Y-m-d'),
                ucfirst($r->urgency),
                ucfirst(str_replace('_', ' ', $r->status)),
                $r->reportedBy?->name ?? '—',
                $actions,
            ];
        })->all();

        return view('property.agent.maintenance.requests', [
            'stats' => $stats,
            'columns' => ['ID', 'Unit', 'Category', 'Summary', 'Reported', 'Priority', 'Status', 'Assignee', 'Actions'],
            'tableRows' => $rows,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'maintenanceEnabled' => $maintenanceEnabled,
            'workflowAutoAssignTickets' => $workflowAutoAssignTickets,
            'filters' => $filters,
        ]);
    }

    public function requestsExport(Request $request)
    {
        $filters = $request->only(['q', 'status', 'urgency', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $rows = $this->requestsQuery($filters)->get();

        return CsvExport::stream(
            'maintenance_requests_'.now()->format('Ymd_His').'.csv',
            ['ID', 'Unit', 'Category', 'Description', 'Urgency', 'Status', 'Reported By', 'Created At'],
            function () use ($rows) {
                foreach ($rows as $r) {
                    yield [
                        $r->id,
                        $r->unit->property->name.'/'.$r->unit->label,
                        $r->category,
                        $r->description,
                        $r->urgency,
                        $r->status,
                        $r->reportedBy?->name,
                        optional($r->created_at)->format('Y-m-d H:i:s'),
                    ];
                }
            }
        );
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        if (PropertyPortalSetting::getValue('form_maintenance_enabled', '1') !== '1') {
            return back()->with('error', __('Maintenance request form is disabled in System setup.'));
        }
        $workflowAutoAssignTickets = PropertyPortalSetting::getValue('workflow_auto_assign_tickets', '0') === '1';

        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'category' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'in:normal,urgent,emergency'],
        ]);

        PmMaintenanceRequest::query()->create([
            ...$data,
            'reported_by_user_id' => $request->user()->id,
            // Auto-assign behavior is represented by moving new tickets into triage immediately.
            'status' => $workflowAutoAssignTickets ? 'in_progress' : 'open',
        ]);

        return back()->with(
            'success',
            $workflowAutoAssignTickets
                ? 'Maintenance request logged and auto-routed to triage.'
                : 'Maintenance request logged.'
        );
    }

    public function updateRequestStatus(Request $request, PmMaintenanceRequest $requestItem): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,done,closed'],
        ]);

        $requestItem->update([
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Request status updated.');
    }

    public function editRequest(PmMaintenanceRequest $requestItem): View
    {
        $requestItem->load(['unit.property', 'reportedBy']);

        return view('property.agent.maintenance.request_edit', [
            'requestItem' => $requestItem,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get(),
        ]);
    }

    public function updateRequest(Request $request, PmMaintenanceRequest $requestItem): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'category' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'in:normal,urgent,emergency'],
            'status' => ['required', 'in:open,in_progress,done,closed'],
        ]);

        $requestItem->update($data);

        return back()->with('success', 'Maintenance request updated.');
    }

    public function jobs(Request $request): View
    {
        $filters = $request->only(['q', 'status', 'vendor_id', 'from', 'to', 'sort', 'dir']);
        $jobs = $this->jobsQuery($filters)->limit(400)->get();

        $mtdCompletedCount = PmMaintenanceJob::query()
            ->whereNotNull('completed_at')
            ->whereYear('completed_at', now()->year)
            ->whereMonth('completed_at', now()->month)
            ->count();
        $mtdSpend = (float) PmMaintenanceJob::query()
            ->whereNotNull('completed_at')
            ->whereYear('completed_at', now()->year)
            ->whereMonth('completed_at', now()->month)
            ->sum('quote_amount');

        $stats = [
            ['label' => 'Jobs', 'value' => (string) $jobs->count(), 'hint' => 'Listed'],
            ['label' => 'Completed (MTD)', 'value' => (string) $mtdCompletedCount, 'hint' => ''],
            ['label' => 'Spend (MTD)', 'value' => PropertyMoney::kes($mtdSpend), 'hint' => 'Quoted'],
        ];

        $rows = $jobs->map(function (PmMaintenanceJob $j) {
            $approved = in_array($j->status, ['approved', 'in_progress', 'done'], true) ? 'Yes' : 'No';
            $schedule = $j->completed_at?->format('Y-m-d') ?? ($j->updated_at?->format('Y-m-d') ?? '—');

            $actions = new HtmlString(
                '<div class="flex flex-wrap items-center gap-1">'.
                '<a href="'.route('property.maintenance.jobs.edit', $j).'" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Edit</a>'.
                '<form method="POST" action="'.route('property.maintenance.jobs.destroy', $j).'" class="inline-flex" data-swal-title="Delete job?" data-swal-confirm="Delete this job permanently?" data-swal-confirm-text="Yes, delete">'.csrf_field().method_field('DELETE').
                '<button type="submit" class="rounded border border-rose-400 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Delete</button>'.
                '</form>'.
                '<a href="'.route('property.maintenance.history').'" class="text-indigo-600 hover:text-indigo-700 font-medium text-xs px-1">History</a>'.
                '</div>'
            );
            if (! in_array($j->status, ['done', 'cancelled'], true)) {
                $approveButton = '';
                if ($j->status === 'quoted') {
                    $approveButton =
                        '<form method="POST" action="'.route('property.maintenance.jobs.status', $j).'" class="inline-flex">'.csrf_field().
                        '<input type="hidden" name="status" value="approved" />'.
                        '<button type="submit" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">Approve</button>'.
                        '</form>';
                }

                $actions = new HtmlString(
                    '<div class="flex flex-wrap items-center gap-1">'.
                    '<a href="'.route('property.maintenance.jobs.edit', $j).'" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Edit</a>'.
                    $approveButton.
                    '<form method="POST" action="'.route('property.maintenance.jobs.status', $j).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="in_progress" />'.
                    '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Start</button>'.
                    '</form>'.
                    '<form method="POST" action="'.route('property.maintenance.jobs.status', $j).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="done" />'.
                    '<button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Mark done</button>'.
                    '</form>'.
                    '<form method="POST" action="'.route('property.maintenance.jobs.status', $j).'" class="inline-flex" data-swal-title="Cancel job?" data-swal-confirm="Cancel this job?" data-swal-confirm-text="Yes, cancel">'.csrf_field().
                    '<input type="hidden" name="status" value="cancelled" />'.
                    '<button type="submit" class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Cancel</button>'.
                    '</form>'.
                    '<form method="POST" action="'.route('property.maintenance.jobs.destroy', $j).'" class="inline-flex" data-swal-title="Delete job?" data-swal-confirm="Delete this job permanently?" data-swal-confirm-text="Yes, delete">'.csrf_field().method_field('DELETE').
                    '<button type="submit" class="rounded border border-rose-400 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Delete</button>'.
                    '</form>'.
                    '</div>'
                );
            }

            return [
                '#'.$j->id,
                $j->request->unit->property->name.'/'.$j->request->unit->label,
                $j->vendor?->name ?? '—',
                $j->quote_amount !== null ? number_format((float) $j->quote_amount, 2) : '—',
                $approved,
                $schedule,
                ucfirst(str_replace('_', ' ', $j->status)),
                $actions,
            ];
        })->all();

        return view('property.agent.maintenance.jobs', [
            'stats' => $stats,
            'columns' => ['Job #', 'Unit', 'Vendor', 'Quote', 'Approved', 'Schedule', 'Status', 'Actions'],
            'tableRows' => $rows,
            'requests' => PmMaintenanceRequest::query()->with('unit.property')->orderByDesc('id')->limit(100)->get(),
            'vendors' => PmVendor::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function jobsExport(Request $request)
    {
        $filters = $request->only(['q', 'status', 'vendor_id', 'from', 'to', 'sort', 'dir']);
        $rows = $this->jobsQuery($filters)->get();

        return CsvExport::stream(
            'maintenance_jobs_'.now()->format('Ymd_His').'.csv',
            ['ID', 'Unit', 'Vendor', 'Quote', 'Status', 'Completed At', 'Notes'],
            function () use ($rows) {
                foreach ($rows as $j) {
                    yield [
                        $j->id,
                        $j->request->unit->property->name.'/'.$j->request->unit->label,
                        $j->vendor?->name,
                        $j->quote_amount,
                        $j->status,
                        optional($j->completed_at)->format('Y-m-d H:i:s'),
                        $j->notes,
                    ];
                }
            }
        );
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_maintenance_request_id' => ['required', 'exists:pm_maintenance_requests,id'],
            'pm_vendor_id' => ['nullable', 'exists:pm_vendors,id'],
            'quote_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:quoted,approved,in_progress,done,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $completedAt = $data['status'] === 'done' ? now() : null;

        $job = PmMaintenanceJob::query()->create([
            ...$data,
            'completed_at' => $completedAt,
        ]);
        PropertyAccountingPostingService::postMaintenanceExpense($job, $request->user());

        return back()->with('success', 'Job saved.');
    }

    public function editJob(PmMaintenanceJob $job): View
    {
        $job->load(['request.unit.property', 'vendor']);

        return view('property.agent.maintenance.job_edit', [
            'job' => $job,
            'requests' => PmMaintenanceRequest::query()->with('unit.property')->orderByDesc('id')->limit(200)->get(),
            'vendors' => PmVendor::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function updateJob(Request $request, PmMaintenanceJob $job): RedirectResponse
    {
        $data = $request->validate([
            'pm_maintenance_request_id' => ['required', 'exists:pm_maintenance_requests,id'],
            'pm_vendor_id' => ['nullable', 'exists:pm_vendors,id'],
            'quote_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:quoted,approved,in_progress,done,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = $data['status'];
        $job->update([
            ...$data,
            'completed_at' => $status === 'done' ? now() : null,
        ]);

        if ($status === 'done') {
            PropertyAccountingPostingService::postMaintenanceExpense($job, $request->user());
        }

        return redirect()->route('property.maintenance.jobs')->with('success', 'Job updated.');
    }

    public function destroyJob(PmMaintenanceJob $job): RedirectResponse
    {
        $job->delete();

        return back()->with('success', 'Job deleted.');
    }

    public function updateJobStatus(Request $request, PmMaintenanceJob $job): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:quoted,approved,in_progress,done,cancelled'],
        ]);

        $status = $data['status'];
        $job->update([
            'status' => $status,
            'completed_at' => $status === 'done' ? now() : null,
        ]);

        if ($status === 'done') {
            PropertyAccountingPostingService::postMaintenanceExpense($job, $request->user());
        }

        return back()->with('success', 'Job status updated.');
    }

    public function history(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property', 'vendor'])
            ->where(function ($q) {
                $q->where('status', 'done')->orWhere('status', 'cancelled');
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $stats = [
            ['label' => 'Closed jobs', 'value' => (string) $jobs->count(), 'hint' => 'Listed'],
            ['label' => 'Done', 'value' => (string) $jobs->where('status', 'done')->count(), 'hint' => ''],
            ['label' => 'Cancelled', 'value' => (string) $jobs->where('status', 'cancelled')->count(), 'hint' => ''],
        ];

        $rows = $jobs->map(fn (PmMaintenanceJob $j) => [
            $j->completed_at?->format('Y-m-d') ?? '—',
            '#'.$j->id,
            $j->request->unit->property->name.'/'.$j->request->unit->label,
            $j->vendor?->name ?? '—',
            $j->quote_amount !== null ? number_format((float) $j->quote_amount, 2) : '—',
            ucfirst(str_replace('_', ' ', $j->status)),
            Str::limit((string) $j->notes, 36),
        ])->all();

        return view('property.agent.maintenance.history', [
            'stats' => $stats,
            'columns' => ['Completed', 'Job #', 'Unit', 'Vendor', 'Quote', 'Status', 'Notes'],
            'tableRows' => $rows,
        ]);
    }

    public function costs(): View
    {
        $jobs = PmMaintenanceJob::query()
            ->with(['request.unit.property'])
            ->whereNotNull('quote_amount')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $ytd = $jobs->filter(fn (PmMaintenanceJob $j) => $j->completed_at
            && (int) $j->completed_at->year === (int) now()->year);
        $ytdSum = (float) $ytd->sum('quote_amount');

        $byCategory = $jobs->groupBy(fn (PmMaintenanceJob $j) => $j->request->category ?: 'General');

        $rows = $byCategory->map(function ($group, $category) {
            /** @var Collection<int, PmMaintenanceJob> $group */
            $sum = (float) $group->sum(fn (PmMaintenanceJob $j) => (float) $j->quote_amount);
            $done = $group->where('status', 'done')->count();
            $avg = $group->count() > 0 ? $sum / $group->count() : 0.0;

            return [
                (string) $category,
                'All properties',
                now()->format('Y'),
                PropertyMoney::kes($sum),
                (string) $group->count(),
                'Avg '.PropertyMoney::kes($avg),
                $done.' completed',
            ];
        })->values()->all();

        $unitCount = PropertyUnit::query()->count();
        $costPerDoor = $unitCount > 0 ? $ytdSum / $unitCount : 0.0;
        $topCategory = '—';
        if ($byCategory->isNotEmpty()) {
            $topCategory = (string) $byCategory->sortByDesc(fn ($g) => $g->sum(fn (PmMaintenanceJob $j) => (float) $j->quote_amount))->keys()->first();
        }

        return view('property.agent.maintenance.costs', [
            'stats' => [
                ['label' => 'Spend (YTD)', 'value' => PropertyMoney::kes($ytdSum), 'hint' => 'Completed jobs, quoted'],
                ['label' => 'Cost / door (YTD)', 'value' => PropertyMoney::kes($costPerDoor), 'hint' => 'Avg across '.$unitCount.' units'],
                ['label' => 'Top cost driver', 'value' => (string) $topCategory, 'hint' => 'By request category'],
                ['label' => 'Jobs with quotes', 'value' => (string) $jobs->count(), 'hint' => 'Listed sample'],
            ],
            'columns' => ['Scope', 'Property / unit', 'Period', 'Spend', 'Tickets', 'vs budget', 'Notes'],
            'tableRows' => $rows,
        ]);
    }

    private function requestsQuery(array $filters): Builder
    {
        $q = PmMaintenanceRequest::query()->with(['unit.property', 'reportedBy']);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search) {
                $b->where('category', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('unit', function (Builder $u) use ($search) {
                        $u->where('label', 'like', '%'.$search.'%')
                            ->orWhereHas('property', fn (Builder $p) => $p->where('name', 'like', '%'.$search.'%'));
                    });
            });
        }

        foreach (['status', 'urgency'] as $f) {
            $v = trim((string) ($filters[$f] ?? ''));
            if ($v !== '') {
                $q->where($f, $v);
            }
        }

        $unitId = (int) ($filters['unit_id'] ?? 0);
        if ($unitId > 0) {
            $q->where('property_unit_id', $unitId);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $q->whereDate('created_at', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $q->whereDate('created_at', '<=', $to);
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'status', 'urgency'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }

    private function jobsQuery(array $filters): Builder
    {
        $q = PmMaintenanceJob::query()->with(['request.unit.property', 'vendor']);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search) {
                $b->where('notes', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('request', function (Builder $r) use ($search) {
                        $r->where('category', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $q->where('status', $status);
        }

        $vendorId = (int) ($filters['vendor_id'] ?? 0);
        if ($vendorId > 0) {
            $q->where('pm_vendor_id', $vendorId);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $q->whereDate('created_at', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $q->whereDate('created_at', '<=', $to);
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'status', 'completed_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }

    public function frequency(): View
    {
        $requests = PmMaintenanceRequest::query()
            ->where('created_at', '>=', now()->subMonths(12))
            ->orderBy('created_at')
            ->get();

        $byMonth = $requests->groupBy(fn (PmMaintenanceRequest $r) => $r->created_at->format('Y-m'));
        $rows = $byMonth->map(function ($group, $ym) {
            $byCat = $group->groupBy(fn (PmMaintenanceRequest $r) => $r->category ?: 'General');
            $repeatUnits = $group
                ->groupBy('property_unit_id')
                ->filter(fn ($g) => $g->count() > 1)
                ->count();
            $note = $group->where('urgency', 'emergency')->count() > 0 ? 'Emergency activity' : 'Routine';

            return [
                (string) $ym,
                (string) $group->count(),
                (string) $byCat->count(),
                (string) $group->where('urgency', 'emergency')->count(),
                (string) $repeatUnits,
                $note,
            ];
        })->sortKeysDesc()->values()->all();

        $unitCount = PropertyUnit::query()->count();
        $monthsWithData = $byMonth->count();
        $emergencyTotal = $requests->where('urgency', 'emergency')->count();

        return view('property.agent.maintenance.frequency', [
            'stats' => [
                ['label' => 'Requests (12 mo)', 'value' => (string) $requests->count(), 'hint' => ''],
                ['label' => 'Months with data', 'value' => (string) $monthsWithData, 'hint' => ''],
                ['label' => 'Emergency (12 mo)', 'value' => (string) $emergencyTotal, 'hint' => ''],
                ['label' => 'Units in portfolio', 'value' => (string) $unitCount, 'hint' => ''],
            ],
            'columns' => ['Month', 'Tickets', 'Categories touched', 'Emergency', 'Repeat units', 'Notes'],
            'tableRows' => $rows,
        ]);
    }
}
