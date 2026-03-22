<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
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
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        return view('public.home', [
            'featuredUnits' => $featuredUnits,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
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

        $units = $query
            ->orderByDesc('updated_at')
            ->paginate(8)
            ->withQueryString();

        return view('public.properties', [
            'units' => $units,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
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
            ->with(['property', 'publicImages'])
            ->firstOrFail();

        $imageUrls = $unit->publicImages->map(fn ($img) => $img->publicUrl())->values()->all();

        $gallerySlots = [];
        for ($i = 0; $i < 5; $i++) {
            $gallerySlots[] = $imageUrls[$i] ?? self::LISTING_PLACEHOLDER_IMAGE;
        }
        $extraPhotoCount = max(0, count($imageUrls) - 5);

        return view('public.property_details', [
            'unit' => $unit,
            'gallerySlots' => $gallerySlots,
            'extraPhotoCount' => $extraPhotoCount,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
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
