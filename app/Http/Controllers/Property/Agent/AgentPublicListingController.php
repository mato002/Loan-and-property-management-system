<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

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
                    : $vacantCount.' vacant on the public Discover page — '.$publishedCount.' featured (photos + publish), '.$withPhotosCount.' with photos.',
            ],
            [
                'route' => 'property.listings.ads',
                'title' => 'Live on website',
                'description' => $publishedCount === 0
                    ? 'No featured listings yet — vacant units still appear on Discover with a placeholder image until you add photos and publish here.'
                    : $publishedCount.' featured listing'.($publishedCount === 1 ? '' : 's').' with gallery + public URLs.',
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
                ['label' => 'Featured', 'value' => (string) $publishedCount, 'hint' => 'Photos + publish'],
                ['label' => 'With photos', 'value' => (string) $withPhotosCount, 'hint' => 'Gallery ready'],
            ],
        ]);
    }

    public function ads(): View
    {
        $published = PropertyUnit::query()
            ->with(['property', 'publicImages'])
            ->publicListingPublished()
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
            ['label' => 'Featured', 'value' => (string) $vacantUnits->where('public_listing_published', true)->count(), 'hint' => 'Photos + publish'],
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
            ['label' => 'Featured', 'value' => (string) $units->where('public_listing_published', true)->count(), 'hint' => 'Photos + publish'],
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

        $validator = Validator::make($request->all(), [
            'photos' => ['required', 'array', 'max:12'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp'],
        ], [
            'photos.required' => 'Select at least one image to upload.',
            'photos.array' => 'Upload failed: photos input was not sent correctly.',
            'photos.max' => 'Upload at most 12 files per batch.',
            'photos.*.required' => 'One of the selected files is empty or missing.',
            'photos.*.file' => 'One of the selected items is not a valid file.',
            'photos.*.image' => 'Only image files are allowed.',
            'photos.*.mimes' => 'Allowed image types: JPEG, JPG, PNG, WEBP.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $files = $request->file('photos', []);
        if (empty($files)) {
            $contentLength = (int) ($request->server('CONTENT_LENGTH') ?? 0);
            $postMax = (string) ini_get('post_max_size');
            $postMaxBytes = $this->iniBytes($postMax);
            $postMaxHint = $postMax !== '' ? $postMax : 'unknown';

            $message = 'No files reached the server.';
            if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
                $message = 'Upload rejected by server: request body size ('.number_format($contentLength).' bytes) is larger than post_max_size='.$postMaxHint.'.';
            }

            return back()->withErrors([
                'photos' => $message,
            ]);
        }

        $next = (int) $property_unit->publicImages()->max('sort_order') + 1;
        $newImageIds = [];

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }
            $original = $file->getClientOriginalName() ?: 'selected file';

            if (! $file->isValid()) {
                return back()->withErrors([
                    'photos' => 'Upload failed for "'.$original.'": '.$file->getErrorMessage().' (PHP upload error code '.$file->getError().').',
                ]);
            }

            try {
                $path = $file->store('public-listings/'.$property_unit->id, 'public');
            } catch (Throwable $e) {
                return back()->withErrors([
                    'photos' => 'Upload failed for "'.$original.'": '.$e->getMessage(),
                ]);
            }

            $created = PropertyUnitPublicImage::query()->create([
                'property_unit_id' => $property_unit->id,
                'path' => $path,
                'sort_order' => $next++,
            ]);
            $newImageIds[] = $created->id;
        }

        // Make newly uploaded images appear first so the public listing reflects the latest upload immediately.
        if ($newImageIds !== []) {
            $images = PropertyUnitPublicImage::query()
                ->where('property_unit_id', $property_unit->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $newOnes = $images->filter(fn (PropertyUnitPublicImage $img) => in_array($img->id, $newImageIds, true))->values();
            $existing = $images->reject(fn (PropertyUnitPublicImage $img) => in_array($img->id, $newImageIds, true))->values();
            $ordered = $newOnes->concat($existing)->values();

            DB::transaction(function () use ($ordered): void {
                foreach ($ordered as $index => $img) {
                    $img->update(['sort_order' => $index + 1]);
                }
            });
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

    public function makePrimaryPhoto(PropertyUnit $property_unit, int $public_image): RedirectResponse
    {
        abort_unless($property_unit->status === PropertyUnit::STATUS_VACANT, 404);

        $images = PropertyUnitPublicImage::query()
            ->where('property_unit_id', $property_unit->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $selected = $images->firstWhere('id', $public_image);
        abort_unless($selected !== null, 404);

        $ordered = $images
            ->reject(fn (PropertyUnitPublicImage $img) => $img->id === $selected->id)
            ->prepend($selected)
            ->values();

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $index => $img) {
                $img->update(['sort_order' => $index + 1]);
            }
        });

        return back()->with('success', __('Main photo updated.'));
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
