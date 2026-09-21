@php
    $applyStep = $applyStep ?? 'search';
    $applyUnit = $applyUnit ?? null;
    $matchingUnits = $matchingUnits ?? collect();
    $applyCatalog = $applyCatalog ?? [];
    $applyHeroImage = $applyHeroImage ?? \App\Http\Controllers\PublicController::APPLY_HERO_IMAGE;
    $searchQuery = array_filter([
        'city' => request('city'),
        'area' => request('area'),
        'property_id' => request('property_id'),
        'unit_type' => request('unit_type'),
        'bedrooms' => request('bedrooms'),
        'max_rent' => request('max_rent'),
    ], fn ($value) => $value !== null && $value !== '');
    $changeSearchUrl = route('public.apply', $searchQuery);
    $startOverUrl = route('public.apply');
    $locationHeadline = request('area') ?: request('city');
    $heroTitle = match ($applyStep) {
        'matches' => $locationHeadline ? 'Homes in '.$locationHeadline : 'Matching homes',
        'details' => 'Complete your application',
        default => 'Find a home, then apply',
    };
    $heroSubtitle = match ($applyStep) {
        'matches' => 'Pick a vacant unit. If nothing fits, change location — no personal details needed.',
        'details' => 'You have chosen a vacant unit. We only need a few details to contact you.',
        default => 'Start with where you want to live. We only take your details after you pick a vacant unit.',
    };
@endphp
<x-public-layout
    :page-title="$publicPageTitle ?? 'Apply for a Rental'"
    :page-description="$publicPageDescription ?? null"
    :page-image="$applyHeroImage"
    :page-robots="$publicPageRobots ?? 'noindex,nofollow'"
>
    <section @class(['public-hero', 'public-hero-apply' => $applyStep === 'search', 'public-hero-apply-compact' => $applyStep !== 'search'])>
        <div class="public-hero-bg">
            <img src="{{ $applyHeroImage }}" alt="Premium managed home" fetchpriority="high" decoding="async">
            <div class="public-hero-overlay"></div>
        </div>

        <div class="relative public-container w-full py-10 sm:py-14 lg:py-16">
            <div @class([
                'grid items-center gap-8 lg:gap-12',
                'lg:grid-cols-2' => $applyStep === 'search',
            ])>
                <div class="public-animate-in is-visible {{ $applyStep === 'search' ? '' : 'text-center max-w-3xl mx-auto' }}">
                    <p class="text-emerald-300 text-xs sm:text-sm font-bold uppercase tracking-[0.22em] mb-3">Private applications</p>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight leading-[1.08] mb-4">
                        {{ $heroTitle }}
                    </h1>
                    <p class="text-sm sm:text-lg text-slate-200/90 leading-relaxed {{ $applyStep === 'search' ? 'max-w-xl' : 'max-w-2xl mx-auto' }}">
                        {{ $heroSubtitle }}
                    </p>

                    <ol class="mt-6 flex flex-col sm:flex-row sm:flex-wrap {{ $applyStep === 'search' ? '' : 'justify-center' }} gap-3 sm:gap-5">
                        @foreach ([
                            'search' => 'Location',
                            'matches' => 'Pick a unit',
                            'details' => 'Your details',
                        ] as $stepKey => $stepLabel)
                            @php
                                $done = ($stepKey === 'search' && in_array($applyStep, ['matches', 'details'], true))
                                    || ($stepKey === 'matches' && $applyStep === 'details');
                            @endphp
                            <li @class(['public-apply-step', 'is-active' => $applyStep === $stepKey, 'is-done' => $done])>
                                <span class="public-apply-step-num">{{ $loop->iteration }}</span>
                                {{ $stepLabel }}
                            </li>
                        @endforeach
                    </ol>

                    @if ($applyStep === 'search')
                        <div class="public-trust-grid mt-8 max-w-xl">
                            <div class="public-trust-item">Verified vacant units</div>
                            <div class="public-trust-item">No details until you choose</div>
                            <div class="public-trust-item">Managed listings only</div>
                        </div>
                    @endif
                </div>

                @if ($applyStep === 'search')
                    <div
                        class="public-apply-card p-5 sm:p-8 public-animate-in"
                        x-data="applyLocationCascade(@js($applyCatalog), @js([
                            'city' => request('city'),
                            'area' => request('area'),
                            'property_id' => request('property_id'),
                            'unit_type' => request('unit_type'),
                            'bedrooms' => request('bedrooms', 'any'),
                            'max_rent' => request('max_rent'),
                        ]))"
                    >
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700 mb-1">Step 1 of 3</p>
                        <h2 class="text-xl font-black text-gray-900 mb-1">Where should we look?</h2>
                        <p class="text-sm text-gray-500 mb-5">Only places with vacant listed homes. Each choice narrows the next list.</p>
                        <form method="GET" action="{{ route('public.apply') }}" class="space-y-4">
                            <div>
                                <label for="city" class="block text-[11px] font-black uppercase tracking-wide text-gray-500 mb-1.5">Town / city</label>
                                <select id="city" name="city" x-model="city" @change="onCity()" required class="w-full min-h-[3rem] rounded-xl border-gray-200 bg-slate-50 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="">Select a listed town</option>
                                    <template x-for="opt in cities" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label + ' · ' + opt.count + ' vacant'"></option>
                                    </template>
                                </select>
                                <p class="mt-1.5 text-xs text-amber-800" x-show="cityIsBroad">Town was not recorded on these listings. Pick the estate or building next.</p>
                            </div>
                            <div x-show="city" x-cloak>
                                <label for="area" class="block text-[11px] font-black uppercase tracking-wide text-gray-500 mb-1.5">Estate / building</label>
                                <select id="area" name="area" x-model="area" @change="onArea()" :required="needsArea" class="w-full min-h-[3rem] rounded-xl border-gray-200 bg-slate-50 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="">Select estate or building</option>
                                    <template x-for="opt in areas" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label + ' · ' + opt.count + ' vacant'"></option>
                                    </template>
                                </select>
                                <p class="mt-1.5 text-xs text-amber-700" x-show="needsArea && !area">Pick the estate or building. A town name alone is not enough.</p>
                            </div>
                            <div x-show="needsBuilding" x-cloak>
                                <label for="property_id" class="block text-[11px] font-black uppercase tracking-wide text-gray-500 mb-1.5">Which building</label>
                                <select id="property_id" name="property_id" x-model="property_id" @change="onBuilding()" :disabled="!needsBuilding" :required="needsBuilding" class="w-full min-h-[3rem] rounded-xl border-gray-200 bg-slate-50 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="">Select a building</option>
                                    <template x-for="opt in buildings" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label + ' · ' + opt.count + ' vacant'"></option>
                                    </template>
                                </select>
                            </div>
                            <input type="hidden" name="property_id" :value="property_id" :disabled="needsBuilding">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="city" x-cloak>
                                <div>
                                    <label for="unit_type" class="block text-[11px] font-black uppercase tracking-wide text-gray-500 mb-1.5">Home type</label>
                                    <select id="unit_type" name="unit_type" x-model="unit_type" class="w-full min-h-[3rem] rounded-xl border-gray-200 bg-slate-50 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500">
                                        <option value="">Any type here</option>
                                        <template x-for="opt in unitTypes" :key="opt.value">
                                            <option :value="opt.value" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label for="bedrooms" class="block text-[11px] font-black uppercase tracking-wide text-gray-500 mb-1.5">Layout</label>
                                    <select id="bedrooms" name="bedrooms" x-model="bedrooms" class="w-full min-h-[3rem] rounded-xl border-gray-200 bg-slate-50 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500">
                                        <option value="any">Any layout here</option>
                                        <template x-for="opt in layouts" :key="opt.value">
                                            <option :value="opt.value" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <div x-show="city" x-cloak>
                                <label for="max_rent" class="block text-[11px] font-black uppercase tracking-wide text-gray-500 mb-1.5">Maximum monthly rent</label>
                                <select id="max_rent" name="max_rent" x-model="max_rent" class="w-full min-h-[3rem] rounded-xl border-gray-200 bg-slate-50 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="">No limit</option>
                                    <template x-for="opt in rentBands" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label"></option>
                                    </template>
                                </select>
                            </div>
                            <p class="text-xs font-semibold text-emerald-800" x-show="city" x-cloak x-text="remainingCount + ' vacant home' + (remainingCount === 1 ? '' : 's') + ' match these choices'"></p>
                            <button type="submit" name="results" value="1" class="public-btn public-btn-primary w-full !py-3.5 !text-sm !rounded-xl" :disabled="!canSearch">
                                Show vacant homes
                            </button>
                            <p class="text-xs text-center text-gray-400" x-show="!canSearch">Choose a listed town, then the estate or building, before we ask for your details.</p>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($applyStep !== 'search')
        <div class="bg-slate-50 border-t border-slate-100">
            <div class="public-container py-8 sm:py-12">
                @error('property_unit_id')
                    <div class="max-w-3xl mx-auto mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $message }}</div>
                @enderror

                @if ($applyStep === 'matches')
                    <div class="max-w-6xl mx-auto">
                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-8">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">Step 2 of 3</p>
                                <h2 class="text-2xl font-black text-gray-900">Vacant homes in {{ $locationHeadline }}</h2>
                                <p class="text-sm text-gray-500">{{ $matchingUnits->count() }} match{{ $matchingUnits->count() === 1 ? '' : 'es' }}. Choose one to continue.</p>
                            </div>
                            <a href="{{ $startOverUrl }}" class="public-btn public-btn-secondary !text-xs">Change search</a>
                        </div>

                        @if ($matchingUnits->isEmpty())
                            <x-public.empty-state
                                title="Nothing vacant there right now"
                                :description="'We have no listed vacant units in '.request('city').' for those filters. Change location or type instead of leaving your details.'"
                                action-label="Try another location"
                                :action-url="$startOverUrl"
                            >
                                <a href="{{ route('public.properties') }}" class="public-btn public-btn-secondary mt-3">Browse all listings</a>
                            </x-public.empty-state>
                        @else
                            <div class="public-listing-grid">
                                @foreach ($matchingUnits as $unit)
                                    <div class="flex flex-col">
                                        <x-public.property-card :unit="$unit" :placeholder-image="$listingPlaceholderImage" />
                                        <a
                                            href="{{ route('public.apply', array_merge($searchQuery, ['property_unit' => $unit->id])) }}"
                                            class="public-btn public-btn-primary w-full mt-2 !rounded-xl"
                                        >Apply for this unit</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if ($applyStep === 'details' && $applyUnit)
                    <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-5 gap-8">
                        <aside class="lg:col-span-2">
                            <div class="public-apply-card overflow-hidden lg:sticky lg:top-24">
                                @php
                                    $preview = $applyUnit->primaryPublicImageUrl() ?: $listingPlaceholderImage;
                                @endphp
                                <img src="{{ $preview }}" alt="{{ $applyUnit->property->name }}" class="h-40 w-full object-cover">
                                <div class="p-5">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Applying for</p>
                                    <p class="text-lg font-black text-gray-900">{{ $applyUnit->property->name }}</p>
                                    <p class="text-sm font-semibold text-gray-600">Unit {{ $applyUnit->label }} · {{ $applyUnit->unitTypeLabel() }}</p>
                                    <p class="mt-3 text-2xl font-black text-emerald-700">KES {{ number_format($applyUnit->listedRentAmount(), 0) }}<span class="text-sm font-semibold text-gray-500"> /mo</span></p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $applyUnit->bedroomsLabel() }}@if ($applyUnit->property->city) · {{ $applyUnit->property->city }}@endif</p>
                                    <div class="mt-4 flex flex-col gap-2">
                                        <a href="{{ route('public.property_details', $applyUnit->id) }}" class="public-btn public-btn-secondary !text-xs w-full">View listing</a>
                                        <a href="{{ $changeSearchUrl }}" class="text-center text-xs font-bold text-emerald-800">Choose a different unit</a>
                                    </div>
                                </div>
                            </div>
                        </aside>

                        <div class="lg:col-span-3">
                            <div class="public-apply-card overflow-hidden">
                                <form action="{{ route('public.apply.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="property_unit_id" value="{{ $applyUnit->id }}">
                                    <div class="p-5 sm:p-8 space-y-5">
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">Step 3 of 3</p>
                                            <h2 class="text-xl font-black text-gray-900">Your details</h2>
                                        </div>
                                        <div>
                                            <label for="full_name" class="block text-xs font-bold text-gray-700 mb-1">Full name</label>
                                            <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                                        </div>
                                        <div>
                                            <label for="phone" class="block text-xs font-bold text-gray-700 mb-1">Phone number</label>
                                            <input id="phone" name="phone" type="tel" placeholder="07XXXXXXXX" value="{{ old('phone') }}" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                                        </div>
                                        <div>
                                            <label for="email" class="block text-xs font-bold text-gray-700 mb-1">Email <span class="text-gray-400 font-medium">(optional)</span></label>
                                            <input id="email" name="email" type="email" value="{{ old('email') }}" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label for="id_number" class="block text-xs font-bold text-gray-700 mb-1">National ID / passport</label>
                                                <input id="id_number" name="id_number" type="text" value="{{ old('id_number') }}" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                            <div>
                                                <label for="occupants" class="block text-xs font-bold text-gray-700 mb-1">People moving in</label>
                                                <input id="occupants" name="occupants" type="number" min="1" max="20" value="{{ old('occupants', 1) }}" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                        </div>
                                        <div>
                                            <label for="employer" class="block text-xs font-bold text-gray-700 mb-1">Employer / occupation</label>
                                            <input id="employer" name="employer" type="text" value="{{ old('employer') }}" placeholder="Company or self-employed" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                        </div>
                                        <div>
                                            <label for="monthly_income" class="block text-xs font-bold text-gray-700 mb-1">Monthly income band</label>
                                            <select id="monthly_income" name="monthly_income" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                <option value="">Prefer not to say</option>
                                                @foreach (['Under KES 30,000', 'KES 30,000 – 50,000', 'KES 50,000 – 80,000', 'KES 80,000 – 120,000', 'Over KES 120,000'] as $band)
                                                    <option value="{{ $band }}" @selected(old('monthly_income') === $band)>{{ $band }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label for="move_in_date" class="block text-xs font-bold text-gray-700 mb-1">Preferred move-in date</label>
                                                <input id="move_in_date" name="move_in_date" type="date" value="{{ old('move_in_date') }}" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                            <div>
                                                <label for="viewing_slot" class="block text-xs font-bold text-gray-700 mb-1">Preferred viewing</label>
                                                <select id="viewing_slot" name="viewing_slot" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                    <option value="">Not sure yet</option>
                                                    @foreach (['Weekday morning', 'Weekday afternoon', 'Weekday evening', 'Saturday morning', 'Saturday afternoon'] as $slot)
                                                        <option value="{{ $slot }}" @selected(old('viewing_slot') === $slot)>{{ $slot }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-slate-50 px-5 sm:px-8 py-5 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <label class="flex items-start gap-2 text-xs text-gray-700">
                                            <input id="terms" type="checkbox" required class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                            I confirm this information is accurate.
                                        </label>
                                        <button type="submit" class="public-btn public-btn-primary w-full sm:w-auto !px-8">Submit application</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-public-layout>
