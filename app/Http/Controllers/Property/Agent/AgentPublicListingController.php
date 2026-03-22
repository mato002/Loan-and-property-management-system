<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AgentPublicListingController extends Controller
{
    public function hub(): View
    {
        $vacant = PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT);
        $vacantCount = (clone $vacant)->count();
        $publishedCount = (clone $vacant)->where('public_listing_published', true)->count();
        $withPhotosCount = (clone $vacant)->whereHas('publicImages')->count();

        $hubItems = [
            [
                'route' => 'property.listings.create',
                'title' => 'Setup a listing',
                'description' => $vacantCount === 0
                    ? 'Start here once you have a vacant unit — we walk you to photos & publish.'
                    : 'Pick a vacant unit → upload photos, description, go live on Discover.',
            ],
            [
                'route' => 'property.listings.vacant',
                'title' => 'Vacant units',
                'description' => $vacantCount === 0
                    ? 'No vacant inventory yet — add units under Properties.'
                    : $vacantCount.' vacant — '.$publishedCount.' live on the website, '.$withPhotosCount.' with photos.',
            ],
            [
                'route' => 'property.listings.ads',
                'title' => 'Live on website',
                'description' => $publishedCount === 0
                    ? 'Nothing published yet — use Setup a listing or Vacant units first.'
                    : $publishedCount.' published listing'.($publishedCount === 1 ? '' : 's').' with public URLs and edit links.',
            ],
            [
                'route' => 'property.listings.leads',
                'title' => 'Leads',
                'description' => 'Optional pipeline — forms only; no listing record here.',
            ],
            [
                'route' => 'property.listings.applications',
                'title' => 'Applications',
                'description' => 'Roadmap: screening and documents (not wired yet).',
            ],
        ];

        return view('property.agent.listings.index', [
            'hubItems' => $hubItems,
            'hubStats' => [
                ['label' => 'Vacant', 'value' => (string) $vacantCount, 'hint' => 'Units'],
                ['label' => 'Published', 'value' => (string) $publishedCount, 'hint' => 'On Discover'],
                ['label' => 'With photos', 'value' => (string) $withPhotosCount, 'hint' => 'Gallery ready'],
            ],
        ]);
    }

    public function ads(): View
    {
        $published = PropertyUnit::query()
            ->with(['property', 'publicImages'])
            ->where('status', PropertyUnit::STATUS_VACANT)
            ->where('public_listing_published', true)
            ->orderBy('property_id')
            ->orderBy('label')
            ->get();

        $stats = [
            ['label' => 'Published', 'value' => (string) $published->count(), 'hint' => 'Live on website'],
            ['label' => 'Total photos', 'value' => (string) $published->sum(fn (PropertyUnit $u) => $u->publicImages->count()), 'hint' => 'Across listings'],
        ];

        return view('property.agent.listings.ads', [
            'stats' => $stats,
            'publishedUnits' => $published,
        ]);
    }

    public function create(): View
    {
        $vacantUnits = PropertyUnit::query()
            ->with(['property', 'publicImages'])
            ->where('status', PropertyUnit::STATUS_VACANT)
            ->orderBy('property_id')
            ->orderBy('label')
            ->get();

        $stats = [
            ['label' => 'Vacant units', 'value' => (string) $vacantUnits->count(), 'hint' => 'Can get a public listing'],
            ['label' => 'With photos', 'value' => (string) $vacantUnits->filter(fn (PropertyUnit $u) => $u->publicImages->isNotEmpty())->count(), 'hint' => 'Started or complete'],
            ['label' => 'Published', 'value' => (string) $vacantUnits->where('public_listing_published', true)->count(), 'hint' => 'Live on Discover'],
        ];

        return view('property.agent.listings.create', [
            'stats' => $stats,
            'vacantUnits' => $vacantUnits,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_unit_id' => ['required', 'exists:property_units,id'],
        ]);

        $unit = PropertyUnit::query()->findOrFail($data['property_unit_id']);
        abort_unless($unit->status === PropertyUnit::STATUS_VACANT, 404);

        return redirect()->route('property.listings.vacant.public.edit', $unit);
    }

    public function index(): View
    {
        $units = PropertyUnit::query()
            ->with(['property', 'publicImages'])
            ->where('status', PropertyUnit::STATUS_VACANT)
            ->orderBy('property_id')
            ->orderBy('label')
            ->get();

        $stats = [
            ['label' => 'Vacant units', 'value' => (string) $units->count(), 'hint' => 'Eligible to list'],
            ['label' => 'Published', 'value' => (string) $units->where('public_listing_published', true)->count(), 'hint' => 'Live on website'],
            ['label' => 'With photos', 'value' => (string) $units->filter(fn (PropertyUnit $u) => $u->publicImages->isNotEmpty())->count(), 'hint' => 'Gallery'],
        ];

        return view('property.agent.listings.vacant', [
            'stats' => $stats,
            'vacantUnits' => $units,
        ]);
    }

    public function edit(PropertyUnit $property_unit): View
    {
        abort_unless($property_unit->status === PropertyUnit::STATUS_VACANT, 404);

        $property_unit->load(['property', 'publicImages']);

        return view('property.agent.listings.public_edit', [
            'unit' => $property_unit,
        ]);
    }

    public function update(Request $request, PropertyUnit $property_unit): RedirectResponse
    {
        abort_unless($property_unit->status === PropertyUnit::STATUS_VACANT, 404);

        $data = $request->validate([
            'public_listing_description' => ['nullable', 'string', 'max:20000'],
            'public_listing_published' => ['sometimes', 'boolean'],
        ]);

        $publish = $request->boolean('public_listing_published');

        if ($publish && $property_unit->publicImages()->count() === 0) {
            return back()
                ->withInput()
                ->withErrors(['public_listing_published' => __('Add at least one photo before publishing.')]);
        }

        $desc = isset($data['public_listing_description']) && trim((string) $data['public_listing_description']) !== ''
            ? $data['public_listing_description']
            : null;

        $property_unit->update([
            'public_listing_description' => $desc,
            'public_listing_published' => $publish,
        ]);

        return back()->with('success', $publish ? __('Listing is live on the public site.') : __('Listing saved as draft.'));
    }

    public function storePhotos(Request $request, PropertyUnit $property_unit): RedirectResponse
    {
        abort_unless($property_unit->status === PropertyUnit::STATUS_VACANT, 404);

        $request->validate([
            'photos' => ['required', 'array', 'max:12'],
            'photos.*' => ['file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
        ]);

        $next = (int) $property_unit->publicImages()->max('sort_order') + 1;

        foreach ($request->file('photos', []) as $file) {
            if (! $file) {
                continue;
            }
            $path = $file->store('public-listings/'.$property_unit->id, 'public');
            PropertyUnitPublicImage::query()->create([
                'property_unit_id' => $property_unit->id,
                'path' => $path,
                'sort_order' => $next++,
            ]);
        }

        return back()->with('success', __('Photos uploaded.'));
    }

    public function destroyPhoto(PropertyUnit $property_unit, int $public_image): RedirectResponse
    {
        abort_unless($property_unit->status === PropertyUnit::STATUS_VACANT, 404);

        $image = PropertyUnitPublicImage::query()
            ->whereKey($public_image)
            ->where('property_unit_id', $property_unit->id)
            ->firstOrFail();

        Storage::disk('public')->delete($image->path);
        $image->delete();

        if ($property_unit->publicImages()->count() === 0) {
            $property_unit->update(['public_listing_published' => false]);
        }

        return back()->with('success', __('Photo removed.'));
    }
}
