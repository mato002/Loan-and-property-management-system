<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmAmenity;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyAmenityController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'category', 'property_id', 'tagged', 'preset']);
        $search = trim((string) ($filters['q'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $tagged = trim((string) ($filters['tagged'] ?? ''));
        $preset = trim((string) ($filters['preset'] ?? ''));
        $export = strtolower(trim((string) $request->query('export', '')));

        if ($preset === 'tagged') {
            $tagged = 'yes';
        } elseif ($preset === 'unused') {
            $tagged = 'no';
        }

        $amenityLibrary = PmAmenity::query()->orderBy('category')->orderBy('name')->get();

        $query = PmAmenity::query()
            ->withCount('properties')
            ->with('properties:id,name')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', '%'.$search.'%')
                        ->orWhere('category', 'like', '%'.$search.'%');
                });
            })
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($propertyId > 0, fn ($q) => $q->whereHas('properties', fn ($pq) => $pq->where('properties.id', $propertyId)))
            ->when($tagged === 'yes', fn ($q) => $q->has('properties'))
            ->when($tagged === 'no', fn ($q) => $q->doesntHave('properties'))
            ->orderBy('category')
            ->orderBy('name');

        $amenities = (clone $query)->get();
        $amenitiesPage = (clone $query)->paginate(50)->withQueryString();

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream(
                'property-amenities',
                ['Amenity', 'Category', 'Applies To', 'Tagged Properties', 'Property List', 'Last Updated'],
                function () use ($amenities) {
                    return $amenities->map(function (PmAmenity $a) {
                        return [
                            (string) $a->name,
                            (string) ($a->category ?? ''),
                            'Property',
                            (string) ((int) ($a->properties_count ?? 0)),
                            (string) $a->properties->pluck('name')->join(', '),
                            (string) $a->updated_at->format('Y-m-d'),
                        ];
                    });
                },
                $export
            );
        }

        $rows = $amenitiesPage->getCollection()->map(function (PmAmenity $a) {
            $propertyTags = $a->properties->map(function (Property $p) use ($a) {
                return '<span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">'.
                    e($p->name).
                    '<form method="post" action="'.route('property.properties.amenities.detach', absolute: false).'" class="inline" onsubmit="return confirm(\'Remove this property tag?\');">'.
                    csrf_field().
                    '<input type="hidden" name="pm_amenity_id" value="'.$a->id.'" />'.
                    '<input type="hidden" name="property_id" value="'.$p->id.'" />'.
                    '<button type="submit" class="text-red-600 hover:underline font-semibold leading-none" title="Remove">×</button>'.
                    '</form></span>';
            })->implode(' ');
            if ($propertyTags === '') {
                $propertyTags = '<span class="text-slate-400 text-xs">No property tags</span>';
            }

            return [
                $a->name,
                $a->category ?? '—',
                'Property',
                (string) ($a->properties_count ?? 0),
                new HtmlString($propertyTags),
                $a->updated_at->format('Y-m-d'),
            ];
        })->all();

        $propertiesTagged = Property::query()->has('amenities')->count();
        $totalProperties = Property::query()->count();
        $coveragePct = $totalProperties > 0 ? round(($propertiesTagged / $totalProperties) * 100, 1) : 0.0;
        $categorySummary = $amenities
            ->groupBy(fn (PmAmenity $a) => trim((string) ($a->category ?? '')) !== '' ? $a->category : 'Uncategorized')
            ->map(fn ($items, $cat) => ['category' => $cat, 'count' => $items->count()])
            ->values();
        $amenityPropertyIds = $amenityLibrary
            ->mapWithKeys(fn (PmAmenity $a) => [$a->id => $a->properties()->pluck('properties.id')->map(fn ($id) => (int) $id)->all()])
            ->all();

        return view('property.agent.properties.amenities', [
            'stats' => [
                ['label' => 'Amenity types', 'value' => (string) $amenityLibrary->count(), 'hint' => 'In library'],
                ['label' => 'Properties tagged', 'value' => (string) $propertiesTagged, 'hint' => 'With ≥1 amenity'],
                ['label' => 'Coverage', 'value' => number_format($coveragePct, 1).'%', 'hint' => 'Tagged properties / all'],
            ],
            'columns' => ['Amenity', 'Category', 'Applies to', 'Properties', 'Property tags', 'Last updated'],
            'tableRows' => $rows,
            'amenities' => $amenityLibrary,
            'properties' => Property::query()->with(['amenities'])->orderBy('name')->get(),
            'filters' => [
                'q' => $search,
                'category' => $category,
                'property_id' => $propertyId > 0 ? (string) $propertyId : '',
                'tagged' => $tagged,
                'preset' => $preset,
            ],
            'amenitiesPage' => $amenitiesPage,
            'categorySummary' => $categorySummary,
            'amenityPropertyIds' => $amenityPropertyIds,
            'amenityFields' => $this->amenityFieldConfig(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $fieldCfg = $this->amenityFieldConfig();
        $data = $request->validate([
            'name' => [Rule::requiredIf($this->isFieldRequired($fieldCfg, 'name')), 'nullable', 'string', 'max:128'],
            'category' => [Rule::requiredIf($this->isFieldRequired($fieldCfg, 'category')), 'nullable', 'string', 'max:64'],
        ]);

        PmAmenity::query()->create($data);

        return back()->with('success', __('Amenity added to library.'));
    }

    public function attach(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_amenity_id' => ['required', 'exists:pm_amenities,id'],
            'property_id' => ['required', 'exists:properties,id'],
        ]);

        $property = Property::query()->findOrFail($data['property_id']);
        if ($property->amenities()->where('pm_amenities.id', (int) $data['pm_amenity_id'])->exists()) {
            return back()
                ->withErrors(['property_id' => __('This property already has the selected amenity.')])
                ->withInput();
        }
        $property->amenities()->syncWithoutDetaching([$data['pm_amenity_id']]);

        return back()->with('success', __('Amenity linked to property.'));
    }

    public function detach(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_amenity_id' => ['required', 'exists:pm_amenities,id'],
            'property_id' => ['required', 'exists:properties,id'],
        ]);

        $property = Property::query()->findOrFail($data['property_id']);
        $property->amenities()->detach($data['pm_amenity_id']);

        return back()->with('success', __('Amenity removed from property.'));
    }

    public function destroy(PmAmenity $amenity): RedirectResponse
    {
        if ($amenity->properties()->exists()) {
            return back()->withErrors(['amenity' => __('Detach this amenity from all properties before deleting it from the library.')]);
        }

        $amenity->delete();

        return back()->with('success', __('Amenity removed from library.'));
    }

    /**
     * @return array<string,array{enabled:bool,required:bool}>
     */
    private function amenityFieldConfig(): array
    {
        $defaults = [
            'name' => ['enabled' => true, 'required' => true],
            'category' => ['enabled' => true, 'required' => false],
            'property_id' => ['enabled' => true, 'required' => true],
        ];
        $raw = PropertyPortalSetting::getValue('system_setup_amenity_fields_json', '');
        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! array_key_exists($key, $defaults)) {
                continue;
            }
            $defaults[$key]['enabled'] = ! array_key_exists('enabled', $row) || (bool) $row['enabled'];
            $defaults[$key]['required'] = (bool) ($row['required'] ?? false);
        }

        return $defaults;
    }

    /**
     * @param  array<string,array{enabled:bool,required:bool}>  $config
     */
    private function isFieldRequired(array $config, string $field): bool
    {
        return (bool) (($config[$field]['enabled'] ?? false) && ($config[$field]['required'] ?? false));
    }
}
