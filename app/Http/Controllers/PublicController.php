<?php

namespace App\Http\Controllers;

use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicController extends Controller
{
    public const LISTING_PLACEHOLDER_IMAGE = 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=800&q=80';

    /**
     * Display the public home page with hero and featured items.
     */
    public function home(): View
    {
        $featuredUnits = PropertyUnit::query()
            ->publiclyListed()
            ->with(['property', 'publicImages'])
            ->orderByDesc('public_listing_published')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        return view('public.home', [
            'featuredUnits' => $featuredUnits,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
            'publicStats' => [
                'properties' => Property::query()->count(),
                'vacant_listings' => PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)->count(),
                'landlords' => User::query()->where('property_portal_role', 'landlord')->count(),
                'tenants' => PmTenant::query()->count(),
            ],
        ]);
    }

    /**
     * Display the searchable properties listing page.
     */
    public function properties(Request $request): View
    {
        $query = PropertyUnit::query()
            ->publiclyListed()
            ->with(['property', 'publicImages']);

        if ($request->filled('city')) {
            $city = $request->string('city')->trim();
            $query->whereHas('property', function ($q) use ($city) {
                $q->where('city', 'like', '%'.$city.'%');
            });
        }

        if ($request->filled('min_rent') && is_numeric($request->input('min_rent'))) {
            $query->where('rent_amount', '>=', (float) $request->input('min_rent'));
        }

        if ($request->filled('max_rent') && is_numeric($request->input('max_rent'))) {
            $query->where('rent_amount', '<=', (float) $request->input('max_rent'));
        }

        $bedrooms = $request->input('bedrooms');
        if ($bedrooms !== null && $bedrooms !== '' && $bedrooms !== 'any') {
            $query->where('bedrooms', (int) $bedrooms);
        }

        $sort = $request->string('sort')->toString() ?: 'updated';
        match ($sort) {
            'rent_asc' => $query->orderBy('rent_amount')->orderBy('property_id'),
            'rent_desc' => $query->orderByDesc('rent_amount')->orderBy('property_id'),
            'featured' => $query->orderByDesc('public_listing_published')->orderByDesc('updated_at'),
            default => $query->orderByDesc('updated_at'),
        };

        $units = $query->paginate(8)->withQueryString();

        $filterCities = Property::query()
            ->whereHas('units', fn ($q) => $q->where('status', PropertyUnit::STATUS_VACANT))
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->values();

        $sortLabel = match ($sort) {
            'rent_asc' => 'Rent: low to high',
            'rent_desc' => 'Rent: high to low',
            'featured' => 'Featured first',
            default => 'Recently updated',
        };

        return view('public.properties', [
            'units' => $units,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
            'filterCities' => $filterCities,
            'sortLabel' => $sortLabel,
        ]);
    }

    /**
     * Display the details for a published vacant unit (public listing).
     */
    public function propertyDetails(int|string $id): View
    {
        $unit = PropertyUnit::query()
            ->publiclyListed()
            ->whereKey($id)
            ->with(['property', 'publicImages', 'amenities'])
            ->firstOrFail();

        $imageUrls = $unit->publicImages->map(fn ($img) => $img->publicUrl())->values()->all();

        $gallerySlots = [];
        for ($i = 0; $i < 5; $i++) {
            $gallerySlots[] = $imageUrls[$i] ?? self::LISTING_PLACEHOLDER_IMAGE;
        }
        $extraPhotoCount = max(0, count($imageUrls) - 5);

        $similarUnits = PropertyUnit::query()
            ->publiclyListed()
            ->where('property_id', $unit->property_id)
            ->whereKeyNot($unit->id)
            ->with(['property', 'publicImages'])
            ->orderByDesc('public_listing_published')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        $pageTitle = $unit->property->name.' — Unit '.$unit->label;

        return view('public.property_details', [
            'unit' => $unit,
            'gallerySlots' => $gallerySlots,
            'extraPhotoCount' => $extraPhotoCount,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
            'similarUnits' => $similarUnits,
            'pageTitle' => $pageTitle,
        ]);
    }

    /**
     * Display the custom tenant/landlord signup logic landing.
     */
    public function signup(): View
    {
        return view('public.signup');
    }

    /**
     * Display the about us company information page.
     */
    public function about(): View
    {
        return view('public.about');
    }

    /**
     * Display the public contact form landing.
     */
    public function contact(): View
    {
        return view('public.contact');
    }

    /**
     * Display the application form wizard for a property.
     */
    public function apply(Request $request): View
    {
        $propertyId = $request->query('property');
        $propertyUnitId = $request->query('property_unit');

        $applyUnit = null;
        if ($propertyUnitId) {
            $applyUnit = PropertyUnit::query()
                ->publiclyListed()
                ->whereKey($propertyUnitId)
                ->with('property')
                ->first();
        }

        return view('public.apply', compact('propertyId', 'applyUnit'));
    }

    /**
     * Display the post-application/inquiry thank you confirmation page.
     */
    public function thankYou(): View
    {
        return view('public.thank_you');
    }
}
