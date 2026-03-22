<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmTenant;
use App\Models\PmTenantNotice;
use App\Models\PmUnitMovement;
use App\Models\PropertyUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyTenantsOpsWebController extends Controller
{
    public function movements(): View
    {
        $movements = PmUnitMovement::query()->with(['unit.property', 'agent'])->orderByDesc('id')->limit(200)->get();

        $stats = [
            ['label' => 'Events', 'value' => (string) $movements->count(), 'hint' => ''],
            ['label' => 'Planned', 'value' => (string) $movements->where('status', 'planned')->count(), 'hint' => ''],
            ['label' => 'Done', 'value' => (string) $movements->where('status', 'done')->count(), 'hint' => ''],
        ];

        $rows = $movements->map(fn (PmUnitMovement $m) => [
            $m->unit->property->name.'/'.$m->unit->label,
            str_replace('_', ' ', $m->movement_type),
            ucfirst($m->status),
            $m->scheduled_on?->format('Y-m-d') ?? '—',
            $m->completed_on?->format('Y-m-d') ?? '—',
            $m->agent?->name ?? '—',
        ])->all();

        return view('property.agent.tenants.movements', [
            'stats' => $stats,
            'columns' => ['Unit', 'Type', 'Status', 'Scheduled', 'Completed', 'Owner'],
            'tableRows' => $rows,
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
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

        PmUnitMovement::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', __('Movement event saved.'));
    }

    public function notices(): View
    {
        $notices = PmTenantNotice::query()->with(['tenant', 'unit.property', 'createdBy'])->orderByDesc('id')->limit(200)->get();

        $stats = [
            ['label' => 'Notices', 'value' => (string) $notices->count(), 'hint' => ''],
            ['label' => 'Draft', 'value' => (string) $notices->where('status', 'draft')->count(), 'hint' => ''],
            ['label' => 'Sent', 'value' => (string) $notices->where('status', 'sent')->count(), 'hint' => ''],
        ];

        $rows = $notices->map(fn (PmTenantNotice $n) => [
            $n->tenant->name,
            $n->unit ? $n->unit->property->name.'/'.$n->unit->label : '—',
            str_replace('_', ' ', $n->notice_type),
            ucfirst($n->status),
            $n->due_on?->format('Y-m-d') ?? '—',
            $n->createdBy?->name ?? '—',
        ])->all();

        return view('property.agent.tenants.notices', [
            'stats' => $stats,
            'columns' => ['Tenant', 'Unit', 'Type', 'Status', 'Due', 'By'],
            'tableRows' => $rows,
            'tenants' => PmTenant::query()->orderBy('name')->get(),
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->get(),
        ]);
    }

    public function storeNotice(Request $request): RedirectResponse
    {
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
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', __('Notice saved.'));
    }
}
