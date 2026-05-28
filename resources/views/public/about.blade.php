<x-public-layout
    :page-title="$publicPageTitle ?? 'About Us'"
    :page-description="$publicPageDescription ?? null"
>
    @php
        $brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: 'Gaitho Properties';
        $contactEmail = \App\Models\PropertyPortalSetting::getValue('contact_email_primary', '') ?: 'info@gaithoproperties.co.ke';
        $contactPhone = \App\Models\PropertyPortalSetting::getValue('contact_phone', '') ?: '0717 018779';
        $publicStats = [
            'properties' => \App\Models\Property::query()->count(),
            'vacant_listings' => \App\Models\PropertyUnit::query()->where('status', \App\Models\PropertyUnit::STATUS_VACANT)->count(),
            'landlords' => \App\Models\User::query()->where('property_portal_role', 'landlord')->count(),
            'tenants' => \App\Models\PmTenant::query()->count(),
        ];
    @endphp

    <x-public.page-hero
        eyebrow="Property management + marketplace"
        :title="$brandName"
        subtitle="We combine rental discovery with hands-on property operations — so landlords earn with less stress and tenants find verified homes faster."
    >
        <x-slot:background>
            <img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=2400&q=80" alt="Premium property" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-slate-950/70"></div>
        </x-slot:background>
    </x-public.page-hero>

    <section class="py-10 sm:py-14 bg-white">
        <div class="public-container grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            <div class="public-animate-in">
                <h2 class="public-section-title mb-4">Relax — we handle the operations</h2>
                <p class="text-gray-600 leading-relaxed mb-4">From rent collection and tenant follow-ups to maintenance coordination and monthly reporting, our team runs day-to-day rental operations with accountability and transparency.</p>
                <p class="text-gray-600 leading-relaxed">Landlords get clear performance visibility. Tenants get faster communication and a professional rental experience from search to move-in.</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 sm:p-8 public-animate-in">
                <h3 class="text-lg font-black text-emerald-900 mb-4">What makes us different</h3>
                <ul class="space-y-2.5 text-emerald-900 text-sm font-semibold">
                    <li class="flex gap-2"><svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Verified public listings synced from live vacancy data</li>
                    <li class="flex gap-2"><svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Transparent earnings breakdown for landlords</li>
                    <li class="flex gap-2"><svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Mobile-first tenant & landlord portals</li>
                    <li class="flex gap-2"><svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>WhatsApp & SMS communication built-in</li>
                    <li class="flex gap-2"><svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Timely payouts and local office support</li>
                </ul>
            </div>
        </div>
    </section>

    <x-public.why-choose-us :stats="$publicStats" />

    <section class="py-10 sm:py-14 bg-slate-50">
        <div class="public-container">
            <h2 class="public-section-title mb-8">Services for landlords</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ([
                    ['title' => 'Rent collection', 'desc' => 'Automated follow-ups with clear payment records.'],
                    ['title' => 'Tenant screening', 'desc' => 'Structured applications and onboarding workflow.'],
                    ['title' => 'Maintenance', 'desc' => 'Track requests from report to resolution.'],
                    ['title' => 'Monthly reports', 'desc' => 'Statements, collections, and performance at a glance.'],
                ] as $service)
                    <div class="rounded-xl bg-white border border-gray-100 p-5 shadow-sm public-animate-in">
                        <h3 class="font-black text-gray-900 mb-2">{{ $service['title'] }}</h3>
                        <p class="text-sm text-gray-600">{{ $service['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-public.testimonials />

    <section class="py-12 sm:py-16 bg-emerald-600">
        <div class="public-container text-center public-animate-in">
            <h2 class="text-2xl sm:text-4xl font-black text-white mb-3">Let your property earn. We do the work.</h2>
            <p class="text-emerald-100 mb-6">Call {{ $contactPhone }} · Email {{ $contactEmail }}</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('public.contact', ['intent' => 'landlord']) }}" class="public-btn bg-white text-emerald-700 hover:bg-emerald-50">Partner with us</a>
                <a href="{{ route('public.properties') }}" class="public-btn public-btn-ghost">Browse listings</a>
            </div>
        </div>
    </section>
</x-public-layout>
