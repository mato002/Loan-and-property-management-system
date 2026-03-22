<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmLease;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PropertyPortfolioController extends Controller
{
    public function propertyList(): View
    {
        $portfolio = Property::query()
            ->with(['landlords' => fn ($q) => $q->orderBy('name')])
            ->withCount('units')
            ->orderBy('name')
            ->get();

        $stats = [
            ['label' => 'Properties', 'value' => (string) $portfolio->count(), 'hint' => 'In portfolio'],
            ['label' => 'Total units', 'value' => (string) PropertyUnit::query()->count(), 'hint' => 'Across all'],
            ['label' => 'Occupied', 'value' => (string) PropertyUnit::query()->where('status', PropertyUnit::STATUS_OCCUPIED)->count(), 'hint' => 'Units'],
            ['label' => 'Vacant', 'value' => (string) PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)->count(), 'hint' => 'Units'],
        ];

        $rows = $portfolio->map(function (Property $p) {
            $landlordCell = $p->landlords->isEmpty()
                ? '—'
                : $p->landlords->pluck('name')->join(', ');

            return [
                $p->name,
                $p->code ?? '—',
                $p->address_line ?? '—',
                (string) $p->units_count,
                $p->city ?? '—',
                $landlordCell,
                'Active',
            ];
        })->all();

        $landlordLinks = DB::table('property_landlord')
            ->join('properties', 'properties.id', '=', 'property_landlord.property_id')
            ->join('users', 'users.id', '=', 'property_landlord.user_id')
            ->select([
                'property_landlord.id',
                'properties.id as property_id',
                'properties.name as property_name',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
                'property_landlord.ownership_percent',
            ])
            ->orderBy('properties.name')
            ->orderBy('users.name')
            ->get();

        return view('property.agent.properties.list', [
            'stats' => $stats,
            'columns' => ['Name', 'Code', 'Address', 'Units', 'City', 'Landlord(s)', 'Status'],
            'tableRows' => $rows,
            'landlordUsers' => User::query()->where('property_portal_role', 'landlord')->orderBy('name')->get(),
            'properties' => $portfolio,
            'landlordLinks' => $landlordLinks,
        ]);
    }

    public function detachLandlord(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $property = Property::query()->findOrFail($data['property_id']);
        $property->landlords()->detach($data['user_id']);

        return back()->with('success', __('Landlord unlinked from property.'));
    }

    public function updateLandlordOwnership(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'user_id' => ['required', 'exists:users,id'],
            'ownership_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $property = Property::query()->findOrFail($data['property_id']);
        if (! $property->landlords()->whereKey($data['user_id'])->exists()) {
            return back()->withErrors(['user_id' => __('That landlord is not linked to this property.')]);
        }

        $property->landlords()->updateExistingPivot($data['user_id'], [
            'ownership_percent' => $data['ownership_percent'],
        ]);

        return back()->with('success', __('Ownership % updated.'));
    }

    public function propertyPerformance(): View
    {
        $units = PropertyUnit::query()
            ->with([
                'property',
                'leases' => fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE),
            ])
            ->orderBy('property_id')
            ->orderBy('label')
            ->get();

        $lossToLease = 0.0;
        $rows = $units->map(function (PropertyUnit $u) use (&$lossToLease) {
            $lease = $u->leases->first();
            $asking = (float) $u->rent_amount;
            $effective = $lease ? (float) $lease->monthly_rent : null;
            $delta = $effective !== null ? $effective - $asking : null;
            if ($delta !== null && $delta < 0) {
                $lossToLease += abs($delta);
            }

            $vacancyDays = ($u->status === PropertyUnit::STATUS_VACANT && $u->vacant_since)
                ? (int) $u->vacant_since->diffInDays(now())
                : 0;

            $collected = '—';
            $target = PropertyMoney::kes($asking);
            $variance = $delta === null ? '—' : PropertyMoney::kes($delta);
            $trend = $u->status === PropertyUnit::STATUS_VACANT ? 'Vacant' : ($delta !== null && $delta < 0 ? 'Below ask' : 'At/above ask');

            return [
                $u->label,
                $u->property->name,
                (string) $vacancyDays,
                $collected,
                $target,
                $variance,
                $trend,
            ];
        })->all();

        $worst = $units->filter(fn (PropertyUnit $u) => $u->status === PropertyUnit::STATUS_VACANT)->count();

        return view('property.agent.properties.performance', [
            'stats' => [
                ['label' => 'Loss to lease (est.)', 'value' => PropertyMoney::kes($lossToLease), 'hint' => 'Active leases below asking'],
                ['label' => 'Vacant units', 'value' => (string) $worst, 'hint' => 'Current'],
                ['label' => 'Total units', 'value' => (string) $units->count(), 'hint' => ''],
                ['label' => 'With active lease', 'value' => (string) $units->filter(fn (PropertyUnit $u) => $u->leases->isNotEmpty())->count(), 'hint' => ''],
            ],
            'columns' => ['Unit', 'Property', 'Days vacant (current)', 'Collected', 'Target', 'Variance', 'Trend'],
            'tableRows' => $rows,
        ]);
    }

    public function landlordsIndex(): View
    {
        $landlords = User::query()
            ->where('property_portal_role', 'landlord')
            ->with(['landlordProperties' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $linked = $landlords->filter(fn (User $u) => $u->landlordProperties->isNotEmpty());

        $stats = [
            ['label' => 'Landlord accounts', 'value' => (string) $landlords->count(), 'hint' => 'Landlord portal role'],
            ['label' => 'Linked to properties', 'value' => (string) $linked->count(), 'hint' => 'At least one building'],
            ['label' => 'Not linked yet', 'value' => (string) ($landlords->count() - $linked->count()), 'hint' => 'Use link form on property list'],
        ];

        return view('property.agent.landlords.index', [
            'stats' => $stats,
            'landlords' => $landlords,
        ]);
    }

    public function storeProperty(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', 'unique:properties,code'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
        ]);

        Property::query()->create($data);

        return back()->with('success', 'Property saved.');
    }

    public function unitList(): View
    {
        $units = PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get();

        $stats = [
            ['label' => 'Units', 'value' => (string) $units->count(), 'hint' => 'Total'],
            ['label' => 'Occupied', 'value' => (string) $units->where('status', PropertyUnit::STATUS_OCCUPIED)->count(), 'hint' => ''],
            ['label' => 'Vacant', 'value' => (string) $units->where('status', PropertyUnit::STATUS_VACANT)->count(), 'hint' => ''],
            ['label' => 'Listed rent (avg)', 'value' => $units->count() ? PropertyMoney::kes($units->avg('rent_amount')) : PropertyMoney::kes(0), 'hint' => 'Asking'],
        ];

        $rows = $units->map(fn (PropertyUnit $u) => [
            $u->label,
            $u->property->name,
            (string) $u->bedrooms,
            PropertyMoney::kes((float) $u->rent_amount),
            ucfirst($u->status),
            '—',
            $u->vacant_since?->format('Y-m-d') ?? '—',
            'Edit in DB',
        ])->all();

        return view('property.agent.properties.units', [
            'stats' => $stats,
            'columns' => ['Unit', 'Property', 'Beds', 'Rent', 'Status', 'Tenant', 'Vacant since', 'Actions'],
            'tableRows' => $rows,
            'properties' => Property::query()->orderBy('name')->get(),
        ]);
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'label' => ['required', 'string', 'max:64'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:20'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:vacant,occupied,notice'],
        ]);

        PropertyUnit::query()->create([
            ...$data,
            'rent_amount' => $data['rent_amount'],
            'vacant_since' => $data['status'] === PropertyUnit::STATUS_VACANT ? now()->toDateString() : null,
        ]);

        return back()->with('success', 'Unit saved.');
    }

    public function attachLandlord(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $property = Property::query()->findOrFail($data['property_id']);
        $property->landlords()->syncWithoutDetaching([
            $data['user_id'] => ['ownership_percent' => 100],
        ]);

        return back()->with('success', 'Landlord linked to property.');
    }

    public function occupancy(): View
    {
        $units = PropertyUnit::query()
            ->with([
                'property',
                'leases' => fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE)->with('pmTenant'),
            ])
            ->orderBy('property_id')
            ->orderBy('label')
            ->get();

        $total = $units->count();
        $occ = $units->where('status', PropertyUnit::STATUS_OCCUPIED)->count();
        $vac = $units->where('status', PropertyUnit::STATUS_VACANT)->count();
        $notice = $units->where('status', PropertyUnit::STATUS_NOTICE)->count();
        $rate = $total > 0 ? round(100 * $occ / $total, 1) : null;

        $stats = [
            ['label' => 'Occupancy rate', 'value' => $rate !== null ? $rate.'%' : '—', 'hint' => 'Occupied / all units'],
            ['label' => 'Occupied', 'value' => (string) $occ, 'hint' => 'Units'],
            ['label' => 'Vacant', 'value' => (string) $vac, 'hint' => 'Units'],
            ['label' => 'Notice', 'value' => (string) $notice, 'hint' => 'Move-out pipeline'],
        ];

        $rows = $units->map(function (PropertyUnit $u) {
            $lease = $u->leases->first();
            $tenant = $lease?->pmTenant;

            return [
                $u->label,
                $u->property->name,
                ucfirst($u->status),
                $tenant?->name ?? '—',
                PropertyMoney::kes((float) $u->rent_amount),
                $u->vacant_since?->format('Y-m-d') ?? '—',
            ];
        })->all();

        return view('property.agent.properties.occupancy', [
            'stats' => $stats,
            'columns' => ['Unit', 'Property', 'Status', 'Active tenant', 'List rent', 'Vacant since'],
            'tableRows' => $rows,
        ]);
    }
}
