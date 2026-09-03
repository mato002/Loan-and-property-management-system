<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmLease;
use App\Models\PmInvoice;
use App\Models\PmTenant;
use App\Models\PmTenantNotice;
use App\Models\PmTenantNoticeEvent;
use App\Models\PmUnitMovement;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyCommunicationService;
use App\Support\CsvExport;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PropertyTenantsOpsWebController extends Controller
{
    private function isLegalNoticeType(string $noticeType): bool
    {
        $normalized = strtolower(trim($noticeType));
        return str_contains($normalized, 'legal')
            || str_contains($normalized, 'arrears')
            || str_contains($normalized, 'evict')
            || str_contains($normalized, 'vacate')
            || str_contains($normalized, 'termination');
    }

    /**
     * @return list<string>
     */
    private function legalNoticeStatuses(): array
    {
        return [
            'draft',
            'pending_approval',
            'approved',
            'sent',
            'delivered',
            'acknowledged',
            'disputed',
            'expired',
            'cancelled',
            'escalated',
            'closed',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legalNoticeTransitions(): array
    {
        return [
            'draft' => ['pending_approval', 'approved', 'cancelled'],
            'pending_approval' => ['approved', 'cancelled'],
            'approved' => ['sent', 'cancelled'],
            'sent' => ['delivered', 'acknowledged', 'disputed', 'expired', 'escalated'],
            'delivered' => ['acknowledged', 'disputed', 'expired', 'escalated'],
            'acknowledged' => ['closed', 'escalated'],
            'disputed' => ['sent', 'escalated', 'closed'],
            'expired' => ['escalated', 'closed'],
            'escalated' => ['sent', 'closed'],
            'cancelled' => [],
            'closed' => [],
        ];
    }

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
                        $propertyName = (string) ($m->unit?->property?->name ?? 'Unknown property');
                        $unitLabel = (string) ($m->unit?->label ?? 'Unknown unit');

                        return [
                            (string) $m->id,
                            $propertyName.'/'.$unitLabel,
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
            $propertyId = $m->unit?->property_id;
            $propertyName = (string) ($m->unit?->property?->name ?? 'Unknown property');
            $unitLabel = (string) ($m->unit?->label ?? 'Unknown unit');
            $items = [];
            if (! in_array($m->status, ['done', 'cancelled'], true)) {
                $items[] = '<form method="POST" action="'.route('property.tenants.movements.status', $m).'">'.csrf_field().
                    '<input type="hidden" name="status" value="in_progress" />'.
                    '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50">Start</button>'.
                    '</form>';
                $items[] = '<form method="POST" action="'.route('property.tenants.movements.status', $m).'">'.csrf_field().
                    '<input type="hidden" name="status" value="done" />'.
                    '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-emerald-700 hover:bg-emerald-50">Mark done</button>'.
                    '</form>';
            }
            $extra = [];
            if ($propertyId) {
                $extra[] = '<a href="'.route('property.properties.show', ['property' => $propertyId], absolute: false).'" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">View property</a>';
                $extra[] = '<a href="'.route('property.properties.units', ['property_id' => $propertyId], absolute: false).'" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Open units</a>';
            } else {
                $extra[] = '<span class="block px-3 py-2 text-xs text-rose-700">Missing unit/property link</span>';
            }
            if ($m->movement_type === 'move_in') {
                $extra[] = '<a href="'.route('property.tenants.leases', ['unit_id' => $m->property_unit_id], absolute: false).'" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50">Lease flow</a>';
            } else {
                $extra[] = '<a href="'.route('property.tenants.notices', ['unit_id' => $m->property_unit_id], absolute: false).'" class="block px-3 py-2 text-xs text-amber-700 hover:bg-amber-50">Notice flow</a>';
            }
            $items = array_merge($items, $extra);
            $actions = new HtmlString(
                '<div class="relative inline-block text-left">'.
                '<details>'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                '<div class="absolute right-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                implode('', $items).
                '</div>'.
                '</details>'.
                '</div>'
            );

            return [
                $propertyName.'/'.$unitLabel,
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

        return property_view('property.agent.tenants.movements', [
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
        $format = TabularExport::requestedFormat($request->query('export'), $request->query('format'));

        return TabularExport::stream(
            'tenant_movements_'.now()->format('Ymd_His'),
            ['ID', 'Unit', 'Type', 'Status', 'Scheduled On', 'Completed On', 'Owner', 'Notes'],
            function () use ($rows) {
                foreach ($rows as $m) {
                    $propertyName = (string) ($m->unit?->property?->name ?? 'Unknown property');
                    $unitLabel = (string) ($m->unit?->label ?? 'Unknown unit');
                    yield [
                        $m->id,
                        $propertyName.'/'.$unitLabel,
                        $m->movement_type,
                        $m->status,
                        optional($m->scheduled_on)->format('Y-m-d'),
                        optional($m->completed_on)->format('Y-m-d'),
                        $m->agent?->name,
                        $m->notes,
                    ];
                }
            },
            $format,
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
        $workflowAutoReminders = PropertyPortalSetting::isRentReminderAutomationEnabled();
        $reminderLeadDays = max(0, (int) PropertyPortalSetting::getValue('workflow_reminder_lead_days', '3'));
        $filters = $request->only(['q', 'notice_type', 'status', 'tenant_id', 'unit_id', 'from', 'to', 'sort', 'dir', 'event_type', 'risk']);
        $notices = $this->noticesQuery($filters)->limit(400)->get();

        $stats = [
            ['label' => 'Notices', 'value' => (string) $notices->count(), 'hint' => ''],
            ['label' => 'Draft', 'value' => (string) $notices->where('status', 'draft')->count(), 'hint' => ''],
            ['label' => 'Sent', 'value' => (string) $notices->where('status', 'sent')->count(), 'hint' => ''],
        ];

        $user = $request->user();
        $rows = $notices->map(function (PmTenantNotice $n) use ($user) {
            $selectCell = in_array($n->status, ['closed'], true)
                ? ''
                : new HtmlString(
                    '<label class="inline-flex items-center" data-row-ignore-click>'.
                    '<input type="checkbox" name="notice_ids[]" value="'.(int) $n->id.'" form="property-notices-bulk-form" class="property-bulk-row-checkbox h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">'.
                    '<span class="sr-only">Select notice</span></label>'
                );

            $actions = '—';
            if (! in_array($n->status, ['closed'], true)) {
                $actionForms = [];
                if ($n->status === 'draft') {
                    $actionForms[] = '<form method="POST" action="'.route('property.tenants.notices.status', $n).'">'.csrf_field().
                        '<input type="hidden" name="status" value="pending_approval" />'.
                        '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50">Submit for approval</button>'.
                        '</form>';
                }
                if (in_array($n->status, ['draft', 'pending_approval'], true) && $user->hasPmPermission('communications.approve_notice')) {
                    $actionForms[] = '<form method="POST" action="'.route('property.tenants.notices.status', $n).'">'.csrf_field().
                        '<input type="hidden" name="status" value="approved" />'.
                        '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-indigo-700 hover:bg-indigo-50">Approve</button>'.
                        '</form>';
                }
                if (in_array($n->status, ['approved', 'escalated', 'disputed'], true) && $user->hasPmPermission('communications.send_legal_notice')) {
                    $actionForms[] = '<form method="POST" action="'.route('property.tenants.notices.status', $n).'">'.csrf_field().
                        '<input type="hidden" name="status" value="sent" />'.
                        '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-amber-700 hover:bg-amber-50">Mark sent</button>'.
                        '</form>';
                }
                if (in_array($n->status, ['sent', 'approved'], true) && $user->hasPmPermission('communications.send_legal_notice')) {
                    $actionForms[] = '<form method="POST" action="'.route('property.tenants.notices.status', $n).'">'.csrf_field().
                        '<input type="hidden" name="status" value="delivered" />'.
                        '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-emerald-700 hover:bg-emerald-50">Mark delivered</button>'.
                        '</form>';
                }
                if (in_array($n->status, ['sent', 'delivered'], true)) {
                    $actionForms[] = '<form method="POST" action="'.route('property.tenants.notices.status', $n).'">'.csrf_field().
                        '<input type="hidden" name="status" value="acknowledged" />'.
                        '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-emerald-700 hover:bg-emerald-50">Acknowledge</button>'.
                        '</form>';
                }
                if (in_array($n->status, ['sent', 'delivered', 'disputed', 'expired', 'acknowledged'], true)) {
                    $actionForms[] = '<form method="POST" action="'.route('property.tenants.notices.status', $n).'">'.csrf_field().
                        '<input type="hidden" name="status" value="escalated" />'.
                        '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50">Escalate</button>'.
                        '</form>';
                }
                $actionForms[] = '<form method="POST" action="'.route('property.tenants.notices.status', $n).'">'.csrf_field().
                    '<input type="hidden" name="status" value="closed" />'.
                    '<button type="submit" class="block w-full px-3 py-2 text-left text-xs text-indigo-700 hover:bg-indigo-50">Close</button>'.
                    '</form>';

                $actions = new HtmlString(
                    '<div class="relative inline-block text-left">'.
                    '<details>'.
                    '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Actions <span class="text-slate-400">▼</span></summary>'.
                    '<div class="absolute right-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">'.
                    implode('', $actionForms).
                    '</div>'.
                    '</details>'.
                    '</div>'
                );
            }

            return [
                $selectCell,
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
        $tenantUnitMapQuery = DB::table('pm_invoices as i')
            ->join('pm_tenants as t', 't.id', '=', 'i.pm_tenant_id')
            ->whereNotNull('i.property_unit_id')
            ->tap(fn ($q) => PmInvoice::applyLiveBalanceConstraints($q, 'i'))
            ->selectRaw('i.pm_tenant_id as tenant_id, i.property_unit_id as unit_id, MAX(COALESCE(i.issue_date, i.due_date, DATE(i.created_at))) as latest_date')
            ->groupBy('i.pm_tenant_id', 'i.property_unit_id')
            ->orderByDesc('latest_date');
        if (\App\Models\Concerns\AgentWorkspaceScope::shouldApply()) {
            $tenantUnitMapQuery->where('t.agent_user_id', (int) auth()->id());
        }
        $tenantUnitMap = $tenantUnitMapQuery
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

        return property_view('property.agent.tenants.notices', [
            'stats' => $stats,
            'columns' => ['', 'Tenant', 'Unit', 'Type', 'Status', 'Due', 'By', 'Actions'],
            'tableRows' => $rows,
            'tenants' => $tenants,
            'units' => $units,
            'tenantUnitMap' => $tenantUnitMap,
            'noticeTemplate' => $noticeTemplate,
            'workflowAutoReminders' => $workflowAutoReminders,
            'reminderLeadDays' => $reminderLeadDays,
            'filters' => $filters,
            'notices' => $notices,
            'legalStatuses' => $this->legalNoticeStatuses(),
        ]);
    }

    public function noticesExport(Request $request)
    {
        $filters = $request->only(['q', 'notice_type', 'status', 'tenant_id', 'unit_id', 'from', 'to', 'sort', 'dir', 'event_type', 'risk']);
        $rows = $this->noticesQuery($filters)->get();
        $format = TabularExport::requestedFormat($request->query('export'), $request->query('format'));

        return TabularExport::stream(
            'tenant_notices_'.now()->format('Ymd_His'),
            ['ID', 'Tenant', 'Unit', 'Notice Type', 'Status', 'Due On', 'Created By', 'Last Event', 'Last Event By', 'Last Event At', 'Notes'],
            function () use ($rows) {
                foreach ($rows as $n) {
                    $lastEvent = $n->events->first();
                    yield [
                        $n->id,
                        $n->tenant?->name,
                        $n->unit ? ($n->unit->property->name.'/'.$n->unit->label) : null,
                        $n->notice_type,
                        $n->status,
                        optional($n->due_on)->format('Y-m-d'),
                        $n->createdBy?->name,
                        $lastEvent
                            ? trim(
                                ucfirst(str_replace('_', ' ', (string) $lastEvent->event_type))
                                .' '
                                .($lastEvent->from_status ? '['.$lastEvent->from_status.' -> '.($lastEvent->to_status ?? '—').']' : '')
                            )
                            : null,
                        $lastEvent?->actor?->name,
                        optional($lastEvent?->created_at)->format('Y-m-d H:i:s'),
                        $n->notes,
                    ];
                }
            },
            $format,
        );
    }

    public function storeNotice(Request $request): RedirectResponse
    {
        $workflowAutoReminders = PropertyPortalSetting::isRentReminderAutomationEnabled();
        $reminderLeadDays = max(0, (int) PropertyPortalSetting::getValue('workflow_reminder_lead_days', '3'));
        $allowedStatuses = $this->legalNoticeStatuses();
        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'property_unit_id' => ['nullable', 'exists:property_units,id'],
            'notice_type' => ['required', 'string', 'max:64'],
            'status' => ['required', 'in:'.implode(',', $allowedStatuses)],
            'due_on' => ['nullable', 'date'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($this->isLegalNoticeType((string) ($data['notice_type'] ?? ''))) {
            if (
                in_array((string) ($data['status'] ?? ''), ['approved', 'sent', 'delivered', 'acknowledged', 'escalated'], true)
                && ! $request->user()->hasPmPermission('communications.send_legal_notice')
            ) {
                return back()->withErrors(['status' => 'You do not have permission to send legal notices.']);
            }
            if ((string) ($data['status'] ?? '') === 'approved' && ! $request->user()->hasPmPermission('communications.approve_notice')) {
                return back()->withErrors(['status' => 'You do not have permission to approve legal notices.']);
            }
        }

        $notice = PmTenantNotice::query()->create([
            ...$data,
            'due_on' => ($data['due_on'] ?? null) ?: ($workflowAutoReminders ? now()->addDays($reminderLeadDays)->toDateString() : null),
            'effective_date' => $data['effective_date'] ?? now()->toDateString(),
            'expiry_date' => $data['expiry_date'] ?? null,
            'notes' => ($data['notes'] ?? '') !== ''
                ? $data['notes']
                : PropertyPortalSetting::getValue('template_notice_text', ''),
            'created_by_user_id' => $request->user()->id,
        ]);
        $this->logNoticeEvent(
            $notice,
            'created',
            null,
            (string) $notice->status,
            (int) $request->user()->id,
            'Notice created.',
            [
                'notice_type' => $notice->notice_type,
                'due_on' => optional($notice->due_on)->format('Y-m-d'),
                'effective_date' => optional($notice->effective_date)->format('Y-m-d'),
                'expiry_date' => optional($notice->expiry_date)->format('Y-m-d'),
            ]
        );
        $this->dispatchNoticeIfRequired($notice, $request);

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

    public function noticesBulk(Request $request): RedirectResponse
    {
        $allowedStatuses = $this->legalNoticeStatuses();
        $data = $request->validate([
            'action' => ['required', 'in:'.implode(',', $allowedStatuses)],
            'notice_ids' => ['required', 'array', 'min:1'],
            'notice_ids.*' => ['integer', 'exists:pm_tenant_notices,id'],
        ]);

        $to = (string) $data['action'];
        $notices = PmTenantNotice::query()->whereIn('id', $data['notice_ids'])->get();
        $applied = 0;
        $skipped = 0;
        $errors = [];

        foreach ($notices as $notice) {
            $error = $this->transitionNoticeTo($request, $notice, $to);
            if ($error !== null) {
                $skipped++;
                $errors[] = 'Notice #'.$notice->id.': '.$error;

                continue;
            }
            $applied++;
        }

        $summary = "Updated {$applied} notice(s)";
        if ($skipped > 0) {
            $summary .= ", skipped {$skipped}";
        }
        $summary .= '.';

        if ($errors !== []) {
            return back()
                ->with($applied > 0 ? 'success' : 'warning', $summary)
                ->with('bulk_notice_errors', array_slice($errors, 0, 8));
        }

        return back()->with('success', $summary);
    }

    public function updateNoticeStatus(Request $request, PmTenantNotice $notice): RedirectResponse
    {
        $allowedStatuses = $this->legalNoticeStatuses();
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', $allowedStatuses)],
            'proof_attachment' => ['nullable', 'file', 'max:10240'],
        ]);
        $from = (string) ($notice->status ?? 'draft');
        $to = (string) $data['status'];

        if ($from !== $to) {
            $error = $this->transitionNoticeTo($request, $notice, $to);
            if ($error !== null) {
                return back()->withErrors(['status' => $error]);
            }
            $notice->refresh();
        }

        $uploaded = $request->file('proof_attachment');
        if ($uploaded) {
            $proofPath = $uploaded->store('property/notices/proof', 'public');
            $notice->update(['proof_attachment' => $proofPath]);
            $this->logNoticeEvent(
                $notice,
                'status_changed',
                $from,
                $to,
                (int) $request->user()->id,
                'Proof attachment updated.',
                [
                    'proof_attachment' => $proofPath,
                    'message_id' => $notice->message_id,
                    'delivery_proof_id' => $notice->delivery_proof_id,
                ]
            );
        }

        return back()->with('success', __('Notice status updated.'));
    }

    private function transitionNoticeTo(Request $request, PmTenantNotice $notice, string $to): ?string
    {
        $from = (string) ($notice->status ?? 'draft');
        if ($from === $to) {
            return null;
        }

        $transitions = $this->legalNoticeTransitions();
        if (! in_array($to, $transitions[$from] ?? [], true)) {
            $this->logNoticeEvent(
                $notice,
                'transition_denied',
                $from,
                $to,
                (int) $request->user()->id,
                'Denied invalid status transition.',
                ['reason' => 'invalid_transition']
            );

            return 'Invalid transition from '.$from.' to '.$to.'.';
        }

        if ($this->isLegalNoticeType((string) $notice->notice_type)) {
            if ($to === 'approved' && ! $request->user()->hasPmPermission('communications.approve_notice')) {
                $this->logNoticeEvent(
                    $notice,
                    'permission_denied',
                    $from,
                    $to,
                    (int) $request->user()->id,
                    'Denied status change due to missing communications.approve_notice permission.',
                    ['required_permission' => 'communications.approve_notice']
                );

                return 'Missing permission to approve legal notices.';
            }
            if (in_array($to, ['sent', 'delivered', 'acknowledged', 'escalated'], true) && ! $request->user()->hasPmPermission('communications.send_legal_notice')) {
                $this->logNoticeEvent(
                    $notice,
                    'permission_denied',
                    $from,
                    $to,
                    (int) $request->user()->id,
                    'Denied status change due to missing communications.send_legal_notice permission.',
                    ['required_permission' => 'communications.send_legal_notice']
                );

                return 'Missing permission to send legal notices.';
            }
        }

        $notice->update([
            'status' => $to,
            'served_by_user_id' => in_array($to, ['sent', 'delivered', 'acknowledged'], true) ? $request->user()->id : $notice->served_by_user_id,
            'served_at' => in_array($to, ['sent', 'delivered', 'acknowledged'], true) ? now() : $notice->served_at,
        ]);
        $this->logNoticeEvent(
            $notice,
            'status_changed',
            $from,
            $to,
            (int) $request->user()->id,
            'Notice status changed from '.$from.' to '.$to.'.',
            [
                'proof_attachment' => $notice->proof_attachment,
                'message_id' => $notice->message_id,
                'delivery_proof_id' => $notice->delivery_proof_id,
            ]
        );
        $this->dispatchNoticeIfRequired($notice, $request);

        return null;
    }

    private function dispatchNoticeIfRequired(PmTenantNotice $notice, Request $request): void
    {
        if ($notice->status !== 'sent' || $notice->message_id) {
            return;
        }

        $tenant = $notice->tenant;
        if (! $tenant) {
            return;
        }

        $subject = '[NOTICE] '.strtoupper((string) $notice->notice_type).' #'.$notice->id;
        $body = trim((string) ($notice->notes ?? ''));
        if ($body === '') {
            $body = 'A tenant notice has been issued. Please check your portal or contact management.';
        }

        $channel = null;
        $recipients = [];
        if (! empty($tenant->email)) {
            $channel = 'email';
            $recipients = [(string) $tenant->email];
        } elseif (! empty($tenant->phone)) {
            $normalized = app(\App\Services\BulkSmsService::class)->normalizeRecipientList((string) $tenant->phone);
            if ($normalized !== []) {
                $channel = 'sms';
                $recipients = $normalized;
            }
        }
        if ($channel === null || $recipients === []) {
            return;
        }

        $message = app(PropertyCommunicationService::class)->sendNow([
            'created_by_user_id' => (int) $request->user()->id,
            'channel' => $channel,
            'category' => 'legal_notice',
            'purpose' => 'tenant_notice',
            'subject' => $subject,
            'body' => $body,
            'priority' => 'high',
            'severity' => 'warning',
            'recipient_type' => 'tenant',
            'recipient_id' => (int) $tenant->id,
            'idempotency_key' => 'tenant_notice:'.$notice->id,
        ], $recipients);

        $notice->update([
            'message_id' => $message->id,
            'served_by_user_id' => $request->user()->id,
            'served_at' => now(),
        ]);
        $this->logNoticeEvent(
            $notice->fresh(),
            'dispatched',
            null,
            (string) $notice->status,
            (int) $request->user()->id,
            'Notice dispatched via '.$channel.'.',
            [
                'channel' => $channel,
                'recipient' => $recipients[0] ?? null,
                'message_id' => $message->id,
            ]
        );
    }

    private function logNoticeEvent(
        PmTenantNotice $notice,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $actorUserId,
        ?string $notes = null,
        array $meta = []
    ): void {
        PmTenantNoticeEvent::query()->create([
            'notice_id' => (int) $notice->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actorUserId,
            'notes' => $notes,
            'meta' => $meta === [] ? null : $meta,
        ]);
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
        $q = PmTenantNotice::query()->with([
            'tenant',
            'unit.property',
            'createdBy',
            'servedBy',
            'message',
            'deliveryProof',
            'events' => fn ($events) => $events->with('actor:id,name')->orderByDesc('id'),
        ]);

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

        $eventType = trim((string) ($filters['event_type'] ?? ''));
        if ($eventType !== '') {
            $q->whereHas('events', fn (Builder $events) => $events->where('event_type', $eventType));
        }

        $risk = trim((string) ($filters['risk'] ?? ''));
        if ($risk === 'denied') {
            $q->whereHas('events', fn (Builder $events) => $events->whereIn('event_type', ['permission_denied', 'transition_denied']));
        } elseif ($risk === 'escalated') {
            $q->where(function (Builder $b) {
                $b->where('status', 'escalated')
                    ->orWhereHas('events', fn (Builder $events) => $events->where(function (Builder $ev) {
                        $ev->where('event_type', 'status_changed')->where('to_status', 'escalated');
                    }));
            });
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
