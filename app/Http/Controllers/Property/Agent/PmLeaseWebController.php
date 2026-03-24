<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PmLeaseWebController extends Controller
{
    public function leases(): View
    {
        $leaseTemplate = PropertyPortalSetting::getValue('template_lease_text', '');
        $leases = PmLease::query()->with(['pmTenant', 'units.property'])->orderByDesc('start_date')->get();

        $stats = [
            ['label' => 'All leases', 'value' => (string) $leases->count(), 'hint' => ''],
            ['label' => 'Active', 'value' => (string) $leases->where('status', PmLease::STATUS_ACTIVE)->count(), 'hint' => ''],
            ['label' => 'Ending ≤60d', 'value' => (string) $leases->filter(fn (PmLease $l) => $l->status === PmLease::STATUS_ACTIVE && $l->end_date->isFuture() && $l->end_date->lte(now()->addDays(60)))->count(), 'hint' => ''],
            ['label' => 'Draft', 'value' => (string) $leases->where('status', PmLease::STATUS_DRAFT)->count(), 'hint' => ''],
        ];

        $rows = $leases->map(function (PmLease $l) {
            $units = $l->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ');
            $actions = new HtmlString(
                '<a href="'.route('property.leases.edit', $l).'" class="text-indigo-600 hover:text-indigo-700 font-medium">Edit</a>'
            );


            
            return [
                '#'.$l->id,
                $l->pmTenant->name,
                $units !== '' ? $units : '—',
                $l->start_date->format('Y-m-d'),
                $l->end_date->format('Y-m-d'),
                number_format((float) $l->monthly_rent, 2),
                number_format((float) $l->deposit_amount, 2),
                ucfirst($l->status),
                $actions,
            ];
        })->all();

        return view('property.agent.tenants.leases', [
            'stats' => $stats,
            'columns' => ['Lease #', 'Tenant', 'Unit(s)', 'Start', 'End', 'Rent', 'Deposit held', 'Status', 'Actions'],
            'tableRows' => $rows,
            'tenants' => PmTenant::query()->orderBy('name')->get(),
            'vacantUnits' => PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)->with('property')->orderBy('property_id')->get(),
            'leaseTemplate' => $leaseTemplate,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => ['nullable', 'array'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
        ]);

        DB::transaction(function () use ($data) {
            $lease = PmLease::query()->create([
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
            ]);

            $unitIds = $data['property_unit_ids'] ?? [];
            if ($unitIds !== []) {
                $lease->units()->sync($unitIds);
                if ($data['status'] === PmLease::STATUS_ACTIVE) {
                    PropertyUnit::query()->whereIn('id', $unitIds)->update([
                        'status' => PropertyUnit::STATUS_OCCUPIED,
                        'vacant_since' => null,
                    ]);
                }
            }
        });

        return back()->with('success', 'Lease saved.');
    }

    public function edit(PmLease $lease): View
    {
        $lease->load(['pmTenant', 'units.property']);

        return view('property.agent.tenants.lease_edit', [
            'lease' => $lease,
            'tenants' => PmTenant::query()->orderBy('name')->get(),
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get(),
            'leaseTemplate' => PropertyPortalSetting::getValue('template_lease_text', ''),
        ]);
    }

    public function update(Request $request, PmLease $lease): RedirectResponse
    {
        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => ['nullable', 'array'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
        ]);

        DB::transaction(function () use ($data, $lease) {
            $lease->update([
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
            ]);

            $unitIds = $data['property_unit_ids'] ?? [];
            $lease->units()->sync($unitIds);

            if ($data['status'] === PmLease::STATUS_ACTIVE && $unitIds !== []) {
                PropertyUnit::query()->whereIn('id', $unitIds)->update([
                    'status' => PropertyUnit::STATUS_OCCUPIED,
                    'vacant_since' => null,
                ]);
            }
        });

        return back()->with('success', 'Lease updated.');
    }

    public function expiry(): View
    {
        $leases = PmLease::query()
            ->with(['pmTenant', 'units.property.landlords'])
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays(90)->toDateString())
            ->orderBy('end_date')
            ->get();

        $rentAtRisk = (float) $leases
            ->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(90)))
            ->sum('monthly_rent');

        $in30 = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(30)))->count();
        $in60 = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(60)))->count();
        $in90 = $leases->count();

        $mapped = $leases->map(function (PmLease $l) {
            $units = $l->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ') ?: '—';
            $daysLeft = $l->end_date->isBefore(today()) ? 0 : (int) today()->diffInDays($l->end_date);

            $filterParts = [
                mb_strtolower($l->pmTenant->name),
                mb_strtolower($units),
                (string) $daysLeft,
            ];
            if ($daysLeft <= 30) {
                $filterParts[] = 'within30';
            }
            if ($daysLeft <= 60) {
                $filterParts[] = 'within60';
            }
            if ($daysLeft <= 90) {
                $filterParts[] = 'within90';
            }

            return [
                'filter' => implode(' ', $filterParts),
                'cells' => [
                    $l->pmTenant->name,
                    $units,
                    $l->end_date->format('Y-m-d'),
                    (string) max(0, $daysLeft),
                    PropertyMoney::kes((float) $l->monthly_rent),
                    $daysLeft <= 30 ? 'Urgent renewal call' : ($daysLeft <= 60 ? 'Send renewal offer' : 'Monitor'),
                    ucfirst($l->status),
                    new HtmlString(
                        '<a href="'.route('property.tenants.notices').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open notices</a>'
                    ),
                ],
            ];
        });

        $tableRows = $mapped->map(fn (array $r) => $r['cells'])->values()->all();
        $filterTexts = $mapped->map(fn (array $r) => $r['filter'])->values()->all();

        return view('property.agent.tenants.expiry', [
            'stats' => [
                ['label' => 'Expiring ≤30d', 'value' => (string) $in30, 'hint' => 'Urgent'],
                ['label' => 'Expiring ≤60d', 'value' => (string) $in60, 'hint' => 'Outreach'],
                ['label' => 'Expiring ≤90d', 'value' => (string) $in90, 'hint' => 'This list'],
                ['label' => 'Rent at risk (mo)', 'value' => PropertyMoney::kes($rentAtRisk), 'hint' => 'If not renewed'],
            ],
            'columns' => ['Tenant', 'Unit', 'End date', 'Days left', 'Current rent', 'Renewal offer', 'Status', 'Owner'],
            'tableRows' => $tableRows,
            'expiryFilterTexts' => $filterTexts,
        ]);
    }
}
