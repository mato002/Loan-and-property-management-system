<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PmTenantNotice;
use App\Models\PmUnitMovement;
use App\Models\PropertyPortalSetting;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Support\CsvExport;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PropertyTenantsOpsWebController extends Controller
{
    private function backfillMovementsFromLeases(): void
    {
        // Populate movement rows for existing leases so the page isn't empty.
        // This is idempotent-ish via notes "Lease #ID" checks.
        $leases = PmLease::query()
            ->with(['pmTenant:id,name', 'units:id'])
            ->whereIn('status', [PmLease::STATUS_ACTIVE, PmLease::STATUS_EXPIRED, PmLease::STATUS_TERMINATED])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        foreach ($leases as $lease) {
            $unitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();
            if ($unitIds === []) {
                continue;
            }

            $needle = 'Lease #'.$lease->id;
            $tenantName = $lease->pmTenant?->name ?? '—';
            $notes = 'Auto: '.$needle.' (Tenant: '.$tenantName.')';

            if ($lease->status === PmLease::STATUS_ACTIVE) {
                $date = $lease->start_date?->format('Y-m-d');
                foreach ($unitIds as $unitId) {
                    $exists = PmUnitMovement::query()
                        ->where('property_unit_id', $unitId)
                        ->where('movement_type', 'move_in')
                        ->where('notes', 'like', '%'.$needle.'%')
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    PmUnitMovement::query()->create([
                        'property_unit_id' => $unitId,
                        'movement_type' => 'move_in',
                        'status' => 'done',
                        'scheduled_on' => $date,
                        'completed_on' => $date,
                        'notes' => $notes,
                        'user_id' => null,
                    ]);
                }
            }

            if (in_array($lease->status, [PmLease::STATUS_EXPIRED, PmLease::STATUS_TERMINATED], true)) {
                $date = $lease->end_date?->format('Y-m-d') ?? now()->toDateString();
                foreach ($unitIds as $unitId) {
                    $exists = PmUnitMovement::query()
                        ->where('property_unit_id', $unitId)
                        ->where('movement_type', 'move_out')
                        ->where('notes', 'like', '%'.$needle.'%')
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    PmUnitMovement::query()->create([
                        'property_unit_id' => $unitId,
                        'movement_type' => 'move_out',
                        'status' => 'done',
                        'scheduled_on' => $date,
                        'completed_on' => $date,
                        'notes' => $notes,
                        'user_id' => null,
                    ]);
                }
            }
        }
    }

    public function movements(Request $request)
    {
        $this->backfillMovementsFromLeases();

        $tenantMoveInEnabled = PropertyPortalSetting::getValue('form_tenant_move_in_enabled', '1') === '1';
        $filters = $request->only(['q', 'movement_type', 'status', 'unit_id', 'property_id', 'from', 'to', 'sort', 'dir', 'preset']);
        $preset = trim((string) ($filters['preset'] ?? ''));
        if ($preset === 'planned') {
            $filters['status'] = 'planned';
        } elseif ($preset === 'in_progress') {
            $filters['status'] = 'in_progress';
        } elseif ($preset === 'done') {
            $filters['status'] = 'done';
        } elseif ($preset === 'move_out') {
            $filters['movement_type'] = 'move_out';
        }

        $export = strtolower(trim((string) $request->query('export', '')));
        $baseQuery = $this->movementsQuery($filters);
        $movements = (clone $baseQuery)->get();
        $movementsPage = (clone $baseQuery)->paginate(50)->withQueryString();

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream(
                'tenant-movements',
                ['ID', 'Unit', 'Type', 'Status', 'Scheduled On', 'Completed On', 'Owner', 'Notes'],
                function () use ($movements) {
                    return $movements->map(function (PmUnitMovement $m) {
                        return [
                            (string) $m->id,
                            (string) (($m->unit->property->name ?? '').'/'.($m->unit->label ?? '')),
                            (string) str_replace('_', ' ', $m->movement_type),
                            (string) $m->status,
                            (string) ($m->scheduled_on?->format('Y-m-d') ?? ''),
                            (string) ($m->completed_on?->format('Y-m-d') ?? ''),
                            (string) ($m->agent?->name ?? ''),
                            (string) ($m->notes ?? ''),
                        ];
                    });
                },
                $export
            );
        }

        $stats = [
            ['label' => 'Events', 'value' => (string) $movements->count(), 'hint' => ''],
            ['label' => 'Planned', 'value' => (string) $movements->where('status', 'planned')->count(), 'hint' => ''],
            ['label' => 'Done', 'value' => (string) $movements->where('status', 'done')->count(), 'hint' => ''],
            ['label' => 'Move-ins / move-outs', 'value' => $movements->where('movement_type', 'move_in')->count().' / '.$movements->where('movement_type', 'move_out')->count(), 'hint' => 'Filtered'],
        ];

        $rows = $movementsPage->getCollection()->map(function (PmUnitMovement $m) {
            $actions = '—';
            if (! in_array($m->status, ['done', 'cancelled'], true)) {
                $actions = new HtmlString(
                    '<div class="flex flex-wrap gap-1">'.
                    '<form method="POST" action="'.route('property.tenants.movements.status', $m).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="in_progress" />'.
                    '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Start</button>'.
                    '</form>'.
                    '<form method="POST" action="'.route('property.tenants.movements.status', $m).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="done" />'.
                    '<button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Mark done</button>'.
                    '</form>'.
                    '</div>'
                );
            }
            $extra = [
                '<a href="'.route('property.properties.show', ['property' => $m->unit->property_id], absolute: false).'" class="text-indigo-600 hover:text-indigo-700 font-medium">View property</a>',
                '<a href="'.route('property.properties.units', ['property_id' => $m->unit->property_id], absolute: false).'" class="text-slate-700 hover:text-slate-900 font-medium">Open units</a>',
            ];
            if ($m->movement_type === 'move_in') {
                $extra[] = '<a href="'.route('property.tenants.leases', ['unit_id' => $m->property_unit_id], absolute: false).'" class="text-emerald-600 hover:text-emerald-700 font-medium">Lease flow</a>';
            } else {
                $extra[] = '<a href="'.route('property.tenants.notices', ['unit_id' => $m->property_unit_id], absolute: false).'" class="text-amber-600 hover:text-amber-700 font-medium">Notice flow</a>';
            }
            $extraHtml = new HtmlString('<div class="mt-1 flex flex-wrap gap-2">'.implode('<span class="text-slate-300">|</span>', $extra).'</div>');
            if ($actions instanceof HtmlString) {
                $actions = new HtmlString((string) $actions.' '.$extraHtml);
            } else {
                $actions = $extraHtml;
            }

            return [
                $m->unit->property->name.'/'.$m->unit->label,
                str_replace('_', ' ', $m->movement_type),
                ucfirst($m->status),
                $m->scheduled_on?->format('Y-m-d') ?? '—',
                $m->completed_on?->format('Y-m-d') ?? '—',
                $m->agent?->name ?? '—',
                $actions,
            ];
        })->all();

        $trend = $this->movementTrend($movements);
        $typeSummary = [
            'move_in' => (int) $movements->where('movement_type', 'move_in')->count(),
            'move_out' => (int) $movements->where('movement_type', 'move_out')->count(),
            'done' => (int) $movements->where('status', 'done')->count(),
            'pending' => (int) $movements->whereIn('status', ['planned', 'in_progress'])->count(),
        ];
        $upcoming7 = (int) $movements
            ->filter(fn (PmUnitMovement $m) => $m->scheduled_on && $m->scheduled_on->betweenIncluded(now()->startOfDay(), now()->addDays(7)->endOfDay()))
            ->count();

        return view('property.agent.tenants.movements', [
            'stats' => $stats,
            'columns' => ['Unit', 'Type', 'Status', 'Scheduled', 'Completed', 'Owner', 'Actions'],
            'tableRows' => $rows,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'tenantMoveInEnabled' => $tenantMoveInEnabled,
            'filters' => $filters,
            'movementsPage' => $movementsPage,
            'typeSummary' => $typeSummary,
            'upcoming7' => $upcoming7,
            'trend' => $trend,
            'propertyOptions' => Property::query()
                ->whereIn('id', PropertyUnit::query()->select('property_id')->distinct())
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function movementsExport(Request $request)
    {
        $filters = $request->only(['q', 'movement_type', 'status', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $rows = $this->movementsQuery($filters)->get();

        return CsvExport::stream(
            'tenant_movements_'.now()->format('Ymd_His').'.csv',
            ['ID', 'Unit', 'Type', 'Status', 'Scheduled On', 'Completed On', 'Owner', 'Notes'],
            function () use ($rows) {
                foreach ($rows as $m) {
                    yield [
                        $m->id,
                        $m->unit->property->name.'/'.$m->unit->label,
                        $m->movement_type,
                        $m->status,
                        optional($m->scheduled_on)->format('Y-m-d'),
                        optional($m->completed_on)->format('Y-m-d'),
                        $m->agent?->name,
                        $m->notes,
                    ];
                }
            }
        );
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
            'movement_type' => ['required', 'in:move_in,move_out'],
            'status' => ['required', 'in:planned,in_progress,done,cancelled'],
            'scheduled_on' => ['nullable', 'date'],
            'completed_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (
            $data['movement_type'] === 'move_in'
            && PropertyPortalSetting::getValue('form_tenant_move_in_enabled', '1') !== '1'
        ) {
            return back()->with('error', __('Move-in form is disabled in System setup.'));
        }

        PmUnitMovement::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', __('Movement event saved.'));
    }

    public function notices(Request $request): View
    {
        $noticeTemplate = PropertyPortalSetting::getValue('template_notice_text', '');
        $workflowAutoReminders = PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1';
        $reminderLeadDays = max(0, (int) PropertyPortalSetting::getValue('workflow_reminder_lead_days', '3'));
        $filters = $request->only(['q', 'notice_type', 'status', 'tenant_id', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $notices = $this->noticesQuery($filters)->limit(400)->get();

        $stats = [
            ['label' => 'Notices', 'value' => (string) $notices->count(), 'hint' => ''],
            ['label' => 'Draft', 'value' => (string) $notices->where('status', 'draft')->count(), 'hint' => ''],
            ['label' => 'Sent', 'value' => (string) $notices->where('status', 'sent')->count(), 'hint' => ''],
        ];

        $rows = $notices->map(function (PmTenantNotice $n) {
            $actions = '—';
            if (! in_array($n->status, ['closed'], true)) {
                $actions = new HtmlString(
                    '<div class="flex flex-wrap gap-1">'.
                    '<form method="POST" action="'.route('property.tenants.notices.status', $n).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="sent" />'.
                    '<button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Mark sent</button>'.
                    '</form>'.
                    '<form method="POST" action="'.route('property.tenants.notices.status', $n).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="acknowledged" />'.
                    '<button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Acknowledge</button>'.
                    '</form>'.
                    '<form method="POST" action="'.route('property.tenants.notices.status', $n).'" class="inline-flex">'.csrf_field().
                    '<input type="hidden" name="status" value="closed" />'.
                    '<button type="submit" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">Close</button>'.
                    '</form>'.
                    '</div>'
                );
            }

            return [
                $n->tenant?->name ?? '—',
                $n->unit ? $n->unit->property->name.'/'.$n->unit->label : '—',
                str_replace('_', ' ', $n->notice_type),
                ucfirst($n->status),
                $n->due_on?->format('Y-m-d') ?? '—',
                $n->createdBy?->name ?? '—',
                $actions,
            ];
        })->all();

        $tenants = PmTenant::query()->orderBy('name')->get();
        $units = PropertyUnit::query()->with('property')->orderBy('property_id')->get();

        // Auto-pick the most recent invoiced unit per tenant when creating a notice.
        $tenantUnitMap = DB::table('pm_invoices as i')
            ->join('pm_tenants as t', 't.id', '=', 'i.pm_tenant_id')
            ->whereNotNull('i.property_unit_id')
            ->selectRaw('i.pm_tenant_id as tenant_id, i.property_unit_id as unit_id, MAX(COALESCE(i.issue_date, i.due_date, DATE(i.created_at))) as latest_date')
            ->groupBy('i.pm_tenant_id', 'i.property_unit_id')
            ->orderByDesc('latest_date')
            ->get()
            ->groupBy('tenant_id')
            ->map(static function (Collection $rows): ?int {
                $first = $rows->first();
                if (! $first) {
                    return null;
                }

                $unitId = (int) ($first->unit_id ?? 0);

                return $unitId > 0 ? $unitId : null;
            })
            ->filter(static fn (?int $unitId): bool => $unitId !== null)
            ->toArray();

        return view('property.agent.tenants.notices', [
            'stats' => $stats,
            'columns' => ['Tenant', 'Unit', 'Type', 'Status', 'Due', 'By', 'Actions'],
            'tableRows' => $rows,
            'tenants' => $tenants,
            'units' => $units,
            'tenantUnitMap' => $tenantUnitMap,
            'noticeTemplate' => $noticeTemplate,
            'workflowAutoReminders' => $workflowAutoReminders,
            'reminderLeadDays' => $reminderLeadDays,
            'filters' => $filters,
        ]);
    }

    public function noticesExport(Request $request)
    {
        $filters = $request->only(['q', 'notice_type', 'status', 'tenant_id', 'unit_id', 'from', 'to', 'sort', 'dir']);
        $rows = $this->noticesQuery($filters)->get();

        return CsvExport::stream(
            'tenant_notices_'.now()->format('Ymd_His').'.csv',
            ['ID', 'Tenant', 'Unit', 'Notice Type', 'Status', 'Due On', 'Created By', 'Notes'],
            function () use ($rows) {
                foreach ($rows as $n) {
                    yield [
                        $n->id,
                        $n->tenant?->name,
                        $n->unit ? ($n->unit->property->name.'/'.$n->unit->label) : null,
                        $n->notice_type,
                        $n->status,
                        optional($n->due_on)->format('Y-m-d'),
                        $n->createdBy?->name,
                        $n->notes,
                    ];
                }
            }
        );
    }

    public function storeNotice(Request $request): RedirectResponse
    {
        $workflowAutoReminders = PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1';
        $reminderLeadDays = max(0, (int) PropertyPortalSetting::getValue('workflow_reminder_lead_days', '3'));
        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'property_unit_id' => ['nullable', 'exists:property_units,id'],
            'notice_type' => ['required', 'string', 'max:64'],
            'status' => ['required', 'in:draft,sent,acknowledged,closed'],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        PmTenantNotice::query()->create([
            ...$data,
            'due_on' => ($data['due_on'] ?? null) ?: ($workflowAutoReminders ? now()->addDays($reminderLeadDays)->toDateString() : null),
            'notes' => ($data['notes'] ?? '') !== ''
                ? $data['notes']
                : PropertyPortalSetting::getValue('template_notice_text', ''),
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', __('Notice saved.'));
    }

    public function updateMovementStatus(Request $request, PmUnitMovement $movement): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:planned,in_progress,done,cancelled'],
        ]);

        $status = $data['status'];
        $movement->update([
            'status' => $status,
            'completed_on' => $status === 'done' ? now()->toDateString() : null,
        ]);

        return back()->with('success', __('Movement status updated.'));
    }

    public function updateNoticeStatus(Request $request, PmTenantNotice $notice): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,sent,acknowledged,closed'],
        ]);
        $notice->update([
            'status' => $data['status'],
        ]);

        return back()->with('success', __('Notice status updated.'));
    }

    private function movementsQuery(array $filters): Builder
    {
        $q = PmUnitMovement::query()->with(['unit.property', 'agent']);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search) {
                $b->where('notes', 'like', '%'.$search.'%')
                    ->orWhereHas('unit', function (Builder $u) use ($search) {
                        $u->where('label', 'like', '%'.$search.'%')
                            ->orWhereHas('property', fn (Builder $p) => $p->where('name', 'like', '%'.$search.'%'));
                    });
            });
        }

        foreach (['movement_type', 'status'] as $f) {
            $v = trim((string) ($filters[$f] ?? ''));
            if ($v !== '') {
                $q->where($f, $v);
            }
        }

        $unitId = (int) ($filters['unit_id'] ?? 0);
        if ($unitId > 0) {
            $q->where('property_unit_id', $unitId);
        }
        $propertyId = (int) ($filters['property_id'] ?? 0);
        if ($propertyId > 0) {
            $q->whereHas('unit', fn (Builder $u) => $u->where('property_id', $propertyId));
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $q->whereDate('scheduled_on', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $q->whereDate('scheduled_on', '<=', $to);
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'scheduled_on', 'completed_on', 'status'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }

    private function movementTrend(Collection $movements): Collection
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        return collect(range(0, 5))->map(function ($i) use ($start, $movements) {
            $month = $start->copy()->addMonths($i);
            $monthRows = $movements->filter(function (PmUnitMovement $m) use ($month) {
                $d = $m->completed_on ?? $m->scheduled_on;

                return $d && $d->format('Y-m') === $month->format('Y-m');
            });

            $in = (int) $monthRows->where('movement_type', 'move_in')->count();
            $out = (int) $monthRows->where('movement_type', 'move_out')->count();

            return [
                'label' => $month->format('M Y'),
                'move_in' => $in,
                'move_out' => $out,
                'net' => $in - $out,
            ];
        });
    }

    private function noticesQuery(array $filters): Builder
    {
        $q = PmTenantNotice::query()->with(['tenant', 'unit.property', 'createdBy']);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function (Builder $b) use ($search) {
                $b->where('notice_type', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%')
                    ->orWhereHas('tenant', fn (Builder $t) => $t->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('unit', fn (Builder $u) => $u->where('label', 'like', '%'.$search.'%'));
            });
        }

        foreach (['notice_type', 'status'] as $f) {
            $v = trim((string) ($filters[$f] ?? ''));
            if ($v !== '') {
                $q->where($f, $v);
            }
        }

        $tenantId = (int) ($filters['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            $q->where('pm_tenant_id', $tenantId);
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
        $allowedSort = ['id', 'created_at', 'due_on', 'status', 'notice_type'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }
}
