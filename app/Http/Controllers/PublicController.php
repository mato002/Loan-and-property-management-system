<?php

namespace App\Http\Controllers;

use App\Models\PmTenant;
use App\Models\PmListingApplication;
use App\Models\PmMessageLog;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
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
            'availableCities' => $this->availableCities(),
            'availableUnitTypes' => PropertyUnit::typeOptions(),
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
            'publicPageTitle' => 'Find Verified Rentals',
            'publicPageDescription' => 'Browse verified rental properties, compare units, and connect with trusted property agents in minutes.',
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

        $unitType = strtolower(trim($request->string('unit_type')->toString()));
        if (
            $unitType !== ''
            && Schema::hasColumn('property_units', 'unit_type')
            && array_key_exists($unitType, PropertyUnit::typeOptions())
        ) {
            $query->where('unit_type', $unitType);
        }

        $sort = $request->string('sort')->toString() ?: 'updated';
        match ($sort) {
            'rent_asc' => $query->orderBy('rent_amount')->orderBy('property_id'),
            'rent_desc' => $query->orderByDesc('rent_amount')->orderBy('property_id'),
            'featured' => $query->orderByDesc('public_listing_published')->orderByDesc('updated_at'),
            default => $query->orderByDesc('updated_at'),
        };

        $units = $query->paginate(8)->withQueryString();
        $filterCities = $this->availableCities();

        $sortLabel = match ($sort) {
            'rent_asc' => 'Rent: low to high',
            'rent_desc' => 'Rent: high to low',
            'featured' => 'Featured first',
            default => 'Recently updated',
        };

        $activeCity = trim((string) $request->string('city'));
        $seoTitle = $activeCity !== '' ? 'Properties in '.$activeCity : 'Available Properties';
        $seoDescription = $activeCity !== ''
            ? 'Explore verified rental properties in '.$activeCity.' with transparent pricing and current vacancy status.'
            : 'Explore verified property listings with up-to-date pricing, unit details, and availability.';

        return view('public.properties', [
            'units' => $units,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
            'filterCities' => $filterCities,
            'filterUnitTypes' => PropertyUnit::typeOptions(),
            'sortLabel' => $sortLabel,
            'publicPageTitle' => $seoTitle,
            'publicPageDescription' => $seoDescription,
        ]);
    }

    /**
     * Get selectable city options from currently listed properties.
     */
    private function availableCities()
    {
        return Property::query()
            ->whereHas('units', fn ($q) => $q->where('status', PropertyUnit::STATUS_VACANT))
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->values();
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
            $gallerySlots[] = $imageUrls[$i] ?? null;
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
        $metaBits = array_filter([
            (string) $unit->property->city,
            $unit->bedrooms ? ($unit->bedrooms.' bedroom') : null,
            $unit->rent_amount ? ('KES '.number_format((float) $unit->rent_amount, 0).' / month') : null,
        ]);
        $pageDescription = 'View '.$unit->label.' at '.$unit->property->name
            .(count($metaBits) ? ' in '.implode(', ', $metaBits) : '')
            .'. See photos, amenities, and availability before booking a visit.';
        $heroImage = $imageUrls[0] ?? self::LISTING_PLACEHOLDER_IMAGE;

        return view('public.property_details', [
            'unit' => $unit,
            'gallerySlots' => $gallerySlots,
            'extraPhotoCount' => $extraPhotoCount,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
            'similarUnits' => $similarUnits,
            'pageTitle' => $pageTitle,
            'publicPageTitle' => $pageTitle,
            'publicPageDescription' => $pageDescription,
            'publicPageImage' => $heroImage,
        ]);
    }

    /**
     * Display the custom tenant/landlord signup logic landing.
     */
    public function signup(): View
    {
        return view('public.signup', [
            'publicPageTitle' => 'Create Your Account',
            'publicPageDescription' => 'Sign up to continue with property applications and tenant or landlord services.',
            'publicPageRobots' => 'noindex,nofollow',
        ]);
    }

    /**
     * Display the about us company information page.
     */
    public function about(): View
    {
        return view('public.about', [
            'publicPageTitle' => 'About Us',
            'publicPageDescription' => 'Learn about our property management team, our mission, and how we help landlords and tenants succeed.',
        ]);
    }

    /**
     * Display the public contact form landing.
     */
    public function contact(): View
    {
        return view('public.contact', [
            'publicPageTitle' => 'Contact Us',
            'publicPageDescription' => 'Reach our property team for site visits, rental inquiries, and support with listings or applications.',
        ]);
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

        return view('public.apply', array_merge(compact('propertyId', 'applyUnit'), [
            'publicPageTitle' => 'Apply for a Rental',
            'publicPageDescription' => 'Submit your rental application securely through our online process.',
            'publicPageRobots' => 'noindex,nofollow',
        ]));
    }

    /**
     * Store a public rental application so agents can review and onboard.
     */
    public function applyStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'move_in_date' => ['nullable', 'date'],
            'property_unit_id' => ['nullable', 'integer', 'exists:property_units,id'],
            // Only present when no unit id is provided
            'property' => ['nullable', 'string', 'max:255'],
        ]);

        $notesParts = [];
        if (! empty($data['move_in_date'] ?? null)) {
            $notesParts[] = 'Move-in: '.$data['move_in_date'];
        }
        if (empty($data['property_unit_id'] ?? null) && ! empty($data['property'] ?? null)) {
            $notesParts[] = 'Property/Unit entered: '.$data['property'];
        }
        $notesParts[] = 'Source: public.apply';

        $application = PmListingApplication::query()->create([
            'property_unit_id' => $data['property_unit_id'] ?? null,
            'applicant_name' => $data['full_name'],
            'applicant_phone' => $data['phone'],
            'applicant_email' => $data['email'] ?? null,
            'status' => 'received',
            'notes' => implode(' | ', $notesParts),
        ]);

        $unitLabel = null;
        if (! empty($application->property_unit_id)) {
            $unit = PropertyUnit::query()->with('property')->find($application->property_unit_id);
            if ($unit && $unit->property) {
                $unitLabel = $unit->property->name.'/'.$unit->label;
            }
        }

        PmMessageLog::query()->create([
            'user_id' => null,
            'channel' => 'system',
            'to_address' => 'agents',
            'subject' => 'New public rental application #'.$application->id,
            'body' => 'Applicant: '.$application->applicant_name
                .' | Phone: '.($application->applicant_phone ?: '—')
                .' | Email: '.($application->applicant_email ?: '—')
                .' | Unit: '.($unitLabel ?: 'Not specified'),
            'delivery_status' => 'new',
            'delivery_error' => null,
            'sent_at' => now(),
        ]);

        return redirect()->route('public.thank_you');
    }

    /**
     * Display the post-application/inquiry thank you confirmation page.
     */
    public function thankYou(): View
    {
        return view('public.thank_you', [
            'publicPageTitle' => 'Application Received',
            'publicPageDescription' => 'Thank you. Your request has been received and our team will contact you.',
            'publicPageRobots' => 'noindex,nofollow',
        ]);
    }
}
