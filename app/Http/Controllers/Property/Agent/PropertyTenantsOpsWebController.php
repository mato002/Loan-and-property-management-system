<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmTenant;
use App\Models\PmTenantNotice;
use App\Models\PmUnitMovement;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PropertyTenantsOpsWebController extends Controller
{
    public function movements(): View
    {
        $tenantMoveInEnabled = PropertyPortalSetting::getValue('form_tenant_move_in_enabled', '1') === '1';
        $movements = PmUnitMovement::query()->with(['unit.property', 'agent'])->orderByDesc('id')->limit(200)->get();

        $stats = [
            ['label' => 'Events', 'value' => (string) $movements->count(), 'hint' => ''],
            ['label' => 'Planned', 'value' => (string) $movements->where('status', 'planned')->count(), 'hint' => ''],
            ['label' => 'Done', 'value' => (string) $movements->where('status', 'done')->count(), 'hint' => ''],
        ];

        $rows = $movements->map(function (PmUnitMovement $m) {
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

        return view('property.agent.tenants.movements', [
            'stats' => $stats,
            'columns' => ['Unit', 'Type', 'Status', 'Scheduled', 'Completed', 'Owner', 'Actions'],
            'tableRows' => $rows,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'tenantMoveInEnabled' => $tenantMoveInEnabled,
        ]);
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

    public function notices(): View
    {
        $noticeTemplate = PropertyPortalSetting::getValue('template_notice_text', '');
        $workflowAutoReminders = PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1';
        $reminderLeadDays = max(0, (int) PropertyPortalSetting::getValue('workflow_reminder_lead_days', '3'));
        $notices = PmTenantNotice::query()->with(['tenant', 'unit.property', 'createdBy'])->orderByDesc('id')->limit(200)->get();

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
                $n->tenant->name,
                $n->unit ? $n->unit->property->name.'/'.$n->unit->label : '—',
                str_replace('_', ' ', $n->notice_type),
                ucfirst($n->status),
                $n->due_on?->format('Y-m-d') ?? '—',
                $n->createdBy?->name ?? '—',
                $actions,
            ];
        })->all();

        return view('property.agent.tenants.notices', [
            'stats' => $stats,
            'columns' => ['Tenant', 'Unit', 'Type', 'Status', 'Due', 'By', 'Actions'],
            'tableRows' => $rows,
            'tenants' => PmTenant::query()->orderBy('name')->get(),
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
            'noticeTemplate' => $noticeTemplate,
            'workflowAutoReminders' => $workflowAutoReminders,
            'reminderLeadDays' => $reminderLeadDays,
        ]);
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
}
