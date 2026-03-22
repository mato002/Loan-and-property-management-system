<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmAmenity;
use App\Models\PropertyUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyAmenityController extends Controller
{
    public function index(): View
    {
        $amenities = PmAmenity::query()->withCount('units')->orderBy('category')->orderBy('name')->get();
        $unitsTagged = PropertyUnit::query()->whereHas('amenities')->count();
        $totalUnits = PropertyUnit::query()->count();

        $rows = $amenities->map(fn (PmAmenity $a) => [
            $a->name,
            $a->category ?? '—',
            'Units',
            (string) $a->units_count,
            'Yes',
            $a->updated_at->format('Y-m-d'),
        ])->all();

        return view('property.agent.properties.amenities', [
            'stats' => [
                ['label' => 'Amenity types', 'value' => (string) $amenities->count(), 'hint' => 'In library'],
                ['label' => 'Units tagged', 'value' => (string) $unitsTagged, 'hint' => 'With ≥1 amenity'],
                ['label' => 'Untagged units', 'value' => (string) max(0, $totalUnits - $unitsTagged), 'hint' => 'No amenities yet'],
            ],
            'columns' => ['Amenity', 'Category', 'Applies to', 'Units', 'Show on listing', 'Last updated'],
            'tableRows' => $rows,
            'amenities' => $amenities,
            'units' => PropertyUnit::query()->with(['property', 'amenities'])->orderBy('property_id')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'category' => ['nullable', 'string', 'max:64'],
        ]);

        PmAmenity::query()->create($data);

        return back()->with('success', __('Amenity added to library.'));
    }

    public function attach(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_amenity_id' => ['required', 'exists:pm_amenities,id'],
            'property_unit_id' => ['required', 'exists:property_units,id'],
        ]);

        $unit = PropertyUnit::query()->findOrFail($data['property_unit_id']);
        $unit->amenities()->syncWithoutDetaching([$data['pm_amenity_id']]);

        return back()->with('success', __('Amenity linked to unit.'));
    }

    public function detach(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_amenity_id' => ['required', 'exists:pm_amenities,id'],
            'property_unit_id' => ['required', 'exists:property_units,id'],
        ]);

        $unit = PropertyUnit::query()->findOrFail($data['property_unit_id']);
        $unit->amenities()->detach($data['pm_amenity_id']);

        return back()->with('success', __('Amenity removed from unit.'));
    }

    public function destroy(PmAmenity $amenity): RedirectResponse
    {
        if ($amenity->units()->exists()) {
            return back()->withErrors(['amenity' => __('Detach this amenity from all units before deleting it from the library.')]);
        }

        $amenity->delete();

        return back()->with('success', __('Amenity removed from library.'));
    }
}
