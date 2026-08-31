<?php

namespace App\Http\Controllers;

use App\Models\PmTenant;
use App\Models\PmListingApplication;
use App\Models\PmMessageLog;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Support\Property\PropertyWorkspaceBranding;
use Illuminate\Database\Eloquent\Builder;
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
        $featuredUnits = $this->scopePublicPropertyUnits(
            PropertyUnit::query()
        )
            ->publiclyListed()
            ->whereHas('property')
            ->with(['property', 'publicImages'])
            ->orderByDesc('public_listing_published')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        $publicStats = $this->publicStats();

        return view('public.home', [
            'featuredUnits' => $featuredUnits,
            'availableCities' => $this->availableCities(),
            'availableUnitTypes' => PropertyUnit::typeOptions(),
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
            'publicPageTitle' => 'Find Verified Rentals in Kenya',
            'publicPageDescription' => 'Browse verified rental properties, compare units, and connect with trusted property professionals across Kenya.',
            'publicStats' => $publicStats,
            'heroImage' => self::LISTING_PLACEHOLDER_IMAGE,
        ]);
    }

    /**
     * Display the searchable properties listing page.
     */
    public function properties(Request $request): View
    {
        $query = $this->scopePublicPropertyUnits(
            PropertyUnit::query()
        )
            ->publiclyListed()
            ->whereHas('property')
            ->with(['property', 'publicImages']);

        if ($request->filled('city')) {
            $city = $request->string('city')->trim();
            $query->whereHas('property', function ($q) use ($city) {
                $q->where('city', 'like', '%'.$city.'%');
            });
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->trim();
            $query->where(function ($q) use ($term) {
                $q->where('label', 'like', '%'.$term.'%')
                    ->orWhereHas('property', function ($pq) use ($term) {
                        $pq->where('name', 'like', '%'.$term.'%')
                            ->orWhere('city', 'like', '%'.$term.'%')
                            ->orWhere('address_line', 'like', '%'.$term.'%');
                    });
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

        $units = $query->paginate(12)->withQueryString();
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
            'activeFilters' => $this->buildActiveFilters($request, $sort),
            'publicPageTitle' => $seoTitle,
            'publicPageDescription' => $seoDescription,
        ]);
    }

    /**
     * Get selectable city options from currently listed properties.
     */
    private function availableCities()
    {
        return $this->scopePublicProperties(
            Property::query()
        )
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
        $unit = $this->scopePublicPropertyUnits(
            PropertyUnit::query()
        )
            ->publiclyListed()
            ->whereHas('property')
            ->whereKey($id)
            ->with(['property', 'publicImages', 'amenities'])
            ->firstOrFail();

        $imageUrls = $unit->publicImages->map(fn ($img) => $img->toGalleryItem())->filter(fn ($item) => ($item['url'] ?? '') !== '')->values()->all();

        $similarUnits = $this->scopePublicPropertyUnits(
            PropertyUnit::query()
        )
            ->publiclyListed()
            ->whereHas('property')
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
        $heroImage = ($imageUrls[0]['url'] ?? null) ?: self::LISTING_PLACEHOLDER_IMAGE;

        $publicBrand = PropertyWorkspaceBranding::publicSiteSnapshot();
        $companyName = $publicBrand['company_name'];
        $contactWhatsapp = $publicBrand['contact_whatsapp'];
        $contactPhone = $publicBrand['contact_phone'];
        $whatsAppDigits = preg_replace('/\D+/', '', $contactWhatsapp);
        $phoneHref = preg_replace('/[^0-9\+]/', '', $contactPhone);

        $offerSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Offer',
            'price' => (float) $unit->rent_amount,
            'priceCurrency' => 'KES',
            'availability' => 'https://schema.org/InStock',
            'url' => url()->current(),
        ];

        return view('public.property_details', [
            'unit' => $unit,
            'imageUrls' => $imageUrls,
            'listingPlaceholderImage' => self::LISTING_PLACEHOLDER_IMAGE,
            'similarUnits' => $similarUnits,
            'pageTitle' => $pageTitle,
            'publicPageTitle' => $pageTitle,
            'publicPageDescription' => $pageDescription,
            'publicPageImage' => $heroImage,
            'companyName' => $companyName,
            'whatsAppDigits' => $whatsAppDigits,
            'phoneHref' => $phoneHref,
            'offerSchema' => $offerSchema,
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
            'publicStats' => $this->publicStats(),
        ]);
    }

    /**
     * Display the public contact form landing.
     */
    public function contact(Request $request): View
    {
        $propertyUnit = null;
        if ($request->filled('property_unit')) {
            $propertyUnit = $this->scopePublicPropertyUnits(
                PropertyUnit::query()
            )
                ->publiclyListed()
                ->whereHas('property')
                ->whereKey($request->integer('property_unit'))
                ->with('property')
                ->first();
        }

        return view('public.contact', [
            'propertyUnit' => $propertyUnit,
            'contactIntent' => $request->string('intent')->toString() ?: 'general',
            'publicPageTitle' => 'Contact Us',
            'publicPageDescription' => 'Reach our property team for site visits, rental inquiries, and support with listings or applications.',
        ]);
    }

    /**
     * Store a public contact / newsletter / callback inquiry.
     */
    public function contactStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:5000'],
            'intent' => ['nullable', 'string', 'max:64'],
            'property_unit_id' => ['nullable', 'integer', 'exists:property_units,id'],
        ]);

        $name = trim($data['full_name'] ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')));
        if ($name === '') {
            $name = 'Website visitor';
        }

        $intent = $data['intent'] ?? 'general';
        $unitLabel = null;
        if (! empty($data['property_unit_id'])) {
            $unit = PropertyUnit::query()->with('property')->find($data['property_unit_id']);
            if ($unit && $unit->property) {
                $unitLabel = $unit->property->name.' / '.$unit->label;
            }
        }

        $bodyParts = array_filter([
            'Intent: '.$intent,
            'Name: '.$name,
            'Email: '.$data['email'],
            'Phone: '.($data['phone'] ?? '—'),
            'Unit: '.($unitLabel ?? '—'),
            'Message: '.($data['message'] ?? '—'),
            'Source: public.contact',
        ]);

        PmMessageLog::query()->create([
            'user_id' => null,
            'channel' => 'system',
            'to_address' => 'agents',
            'subject' => 'Public contact inquiry — '.$intent,
            'body' => implode(' | ', $bodyParts),
            'delivery_status' => 'new',
            'delivery_error' => null,
            'sent_at' => now(),
        ]);

        return redirect()->route('public.thank_you', ['type' => 'contact']);
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
                ->whereHas('property')
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
    public function thankYou(Request $request): View
    {
        $type = $request->string('type')->toString() ?: 'application';

        return view('public.thank_you', [
            'thankYouType' => $type,
            'publicPageTitle' => $type === 'contact' ? 'Message Received' : 'Application Received',
            'publicPageDescription' => 'Thank you. Your request has been received and our team will contact you.',
            'publicPageRobots' => 'noindex,nofollow',
        ]);
    }

    /**
     * Build removable filter chip metadata for the properties index.
     *
     * @return array<int, array{label: string, removeUrl: string}>
     */
    private function buildActiveFilters(Request $request, string $sort): array
    {
        $filters = [];
        $base = collect($request->query())->except(['page']);

        if ($request->filled('city')) {
            $remaining = $base->except(['city']);
            $filters[] = [
                'label' => 'Location: '.$request->string('city'),
                'removeUrl' => route('public.properties', $remaining->all()),
            ];
        }

        if ($request->filled('unit_type')) {
            $type = PropertyUnit::typeOptions()[$request->string('unit_type')->toString()] ?? $request->string('unit_type');
            $remaining = $base->except(['unit_type']);
            $filters[] = [
                'label' => 'Type: '.$type,
                'removeUrl' => route('public.properties', $remaining->all()),
            ];
        }

        $bedrooms = $request->input('bedrooms');
        if ($bedrooms !== null && $bedrooms !== '' && $bedrooms !== 'any') {
            $label = (int) $bedrooms === 0 ? 'Studio' : (int) $bedrooms.' bed';
            $remaining = $base->except(['bedrooms']);
            $filters[] = [
                'label' => $label,
                'removeUrl' => route('public.properties', $remaining->all()),
            ];
        }

        if ($request->filled('min_rent')) {
            $remaining = $base->except(['min_rent']);
            $filters[] = [
                'label' => 'Min KES '.number_format((float) $request->input('min_rent'), 0),
                'removeUrl' => route('public.properties', $remaining->all()),
            ];
        }

        if ($request->filled('max_rent')) {
            $remaining = $base->except(['max_rent']);
            $filters[] = [
                'label' => 'Max KES '.number_format((float) $request->input('max_rent'), 0),
                'removeUrl' => route('public.properties', $remaining->all()),
            ];
        }

        if ($request->filled('q')) {
            $remaining = $base->except(['q']);
            $filters[] = [
                'label' => 'Search: '.$request->string('q'),
                'removeUrl' => route('public.properties', $remaining->all()),
            ];
        }

        if ($sort !== 'updated') {
            $remaining = $base->except(['sort']);
            $filters[] = [
                'label' => 'Sort: '.match ($sort) {
                    'rent_asc' => 'Price low',
                    'rent_desc' => 'Price high',
                    'featured' => 'Featured',
                    default => $sort,
                },
                'removeUrl' => route('public.properties', $remaining->all()),
            ];
        }

        return $filters;
    }

    private function publicSiteAgentUserId(): ?int
    {
        return PropertyWorkspaceBranding::resolvePublicSiteAgentUserId();
    }

    private function scopePublicPropertyUnits(Builder $query): Builder
    {
        $agentId = $this->publicSiteAgentUserId();
        if ($agentId !== null && Schema::hasColumn('properties', 'agent_user_id')) {
            $query->whereHas('property', fn ($q) => $q->where('properties.agent_user_id', $agentId));
        }

        return $query;
    }

    private function scopePublicProperties(Builder $query): Builder
    {
        $agentId = $this->publicSiteAgentUserId();
        if ($agentId !== null && Schema::hasColumn('properties', 'agent_user_id')) {
            $query->where('agent_user_id', $agentId);
        }

        return $query;
    }

    /**
     * @return array{properties: int, vacant_listings: int, landlords: int, tenants: int}
     */
    private function publicStats(): array
    {
        $agentId = $this->publicSiteAgentUserId();

        $propertiesQuery = $this->scopePublicProperties(Property::query());
        $vacantQuery = $this->scopePublicPropertyUnits(
            PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)
        );

        $tenantsQuery = PmTenant::query();
        if ($agentId !== null && Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $tenantsQuery->where('agent_user_id', $agentId);
        }

        $landlordsQuery = User::query()->where('property_portal_role', 'landlord');
        if ($agentId !== null && Schema::hasTable('property_landlord') && Schema::hasColumn('properties', 'agent_user_id')) {
            $landlordsQuery->whereIn('id', function ($q) use ($agentId) {
                $q->select('property_landlord.user_id')
                    ->from('property_landlord')
                    ->join('properties', 'properties.id', '=', 'property_landlord.property_id')
                    ->where('properties.agent_user_id', $agentId);
            });
        }

        return [
            'properties' => $propertiesQuery->count(),
            'vacant_listings' => $vacantQuery->count(),
            'landlords' => $landlordsQuery->count(),
            'tenants' => $tenantsQuery->count(),
        ];
    }
}
