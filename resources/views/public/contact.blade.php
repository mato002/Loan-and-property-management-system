<x-public-layout
    :page-title="$publicPageTitle ?? 'Contact Us'"
    :page-description="$publicPageDescription ?? null"
>
    @php
        $brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name');
        $contactEmailPrimary = \App\Models\PropertyPortalSetting::getValue('contact_email_primary', '') ?: 'info@gaithoproperties.co.ke';
        $contactEmailSupport = \App\Models\PropertyPortalSetting::getValue('contact_email_support', '') ?: $contactEmailPrimary;
        $contactPhone = \App\Models\PropertyPortalSetting::getValue('contact_phone', '') ?: '0717 018779';
        $contactWhatsapp = \App\Models\PropertyPortalSetting::getValue('contact_whatsapp', '') ?: '254717018779';
        $contactAddress = \App\Models\PropertyPortalSetting::getValue('contact_address', '') ?: "Nairobi, Kenya";
        $contactMapEmbedUrl = \App\Models\PropertyPortalSetting::getValue('contact_map_embed_url', '');
        $whatsAppDigits = preg_replace('/\D+/', '', $contactWhatsapp) ?: '254717018779';
        $intent = $contactIntent ?? 'general';
        $intentLabel = match ($intent) {
            'viewing' => 'Schedule a viewing',
            'landlord' => 'Landlord inquiry',
            'callback' => 'Request a callback',
            default => 'Get in touch',
        };
    @endphp

    <x-public.page-hero
        :eyebrow="$brandName"
        :title="$intentLabel"
        subtitle="Our property team responds within 24 hours. Reach us by form, phone, or WhatsApp."
    >
        <x-slot:background>
            <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=2200&q=80" alt="Modern property" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/70 via-slate-900/60 to-slate-950/75"></div>
        </x-slot:background>
    </x-public.page-hero>

    <div class="public-container py-8 sm:py-14">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 lg:gap-10">
            <div class="lg:col-span-2 space-y-4">
                <h2 class="text-xl font-black text-gray-900">Contact information</h2>

                @foreach ([
                    ['label' => 'Office', 'value' => $contactAddress, 'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'],
                    ['label' => 'Email', 'value' => $contactEmailPrimary, 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ['label' => 'Phone', 'value' => $contactPhone, 'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                ] as $item)
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 flex gap-3">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/></svg>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wide text-emerald-700">{{ $item['label'] }}</p>
                            <p class="text-sm font-bold text-gray-800 break-words">{!! nl2br(e($item['value'])) !!}</p>
                        </div>
                    </div>
                @endforeach

                <a href="https://wa.me/{{ $whatsAppDigits }}" target="_blank" rel="noopener noreferrer" class="public-btn w-full bg-[#25D366] hover:bg-[#1ebe57] text-white !rounded-xl">
                    Chat on WhatsApp
                </a>
            </div>

            <div class="lg:col-span-3">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-8 shadow-[0_12px_40px_rgb(0,0,0,0.06)]">
                    @if ($propertyUnit ?? null)
                        <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-100 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 mb-1">Inquiring about</p>
                            <p class="font-black text-gray-900">{{ $propertyUnit->property->name }} — Unit {{ $propertyUnit->label }}</p>
                        </div>
                    @endif

                    <form action="{{ route('public.contact.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="intent" value="{{ $intent }}">
                        @if ($propertyUnit ?? null)
                            <input type="hidden" name="property_unit_id" value="{{ $propertyUnit->id }}">
                        @endif

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">First name</label>
                                <input type="text" name="first_name" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Last name</label>
                                <input type="text" name="last_name" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Phone <span class="text-gray-400 font-medium">(recommended)</span></label>
                            <input type="tel" name="phone" placeholder="07XXXXXXXX" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Message</label>
                            <textarea name="message" rows="4" class="w-full rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Tell us about your preferred location, budget, or viewing times..." required></textarea>
                        </div>
                        <button type="submit" class="public-btn public-btn-primary w-full !py-3.5 !rounded-xl">
                            Send message
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if ($contactMapEmbedUrl)
            <div class="mt-10 rounded-2xl overflow-hidden border border-gray-200 h-56 sm:h-72">
                <iframe title="{{ $brandName }} office" src="{{ $contactMapEmbedUrl }}" class="w-full h-full" loading="lazy"></iframe>
            </div>
        @endif
    </div>
</x-public-layout>
