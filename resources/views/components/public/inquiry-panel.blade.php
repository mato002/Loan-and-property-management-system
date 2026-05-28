@props([
    'unit',
    'whatsAppDigits' => '',
    'phoneHref' => '',
    'companyName' => '',
])

@php
    $applyUrl = route('public.apply', ['property_unit' => $unit->id]);
    $contactUrl = route('public.contact', ['property_unit' => $unit->id, 'intent' => 'viewing']);
    $waMessage = rawurlencode('Hi, I am interested in '.$unit->property->name.' — Unit '.$unit->label.'. Can we schedule a viewing?');
    $waUrl = $whatsAppDigits ? 'https://wa.me/'.$whatsAppDigits.'?text='.$waMessage : '#';
@endphp

<aside {{ $attributes->merge(['class' => 'lg:sticky lg:top-24']) }}>
    <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-[0_12px_40px_rgb(0,0,0,0.06)]">
        <div class="flex items-center gap-4 pb-5 mb-5 border-b border-gray-100">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-emerald-500/25">
                {{ strtoupper(substr($companyName ?: 'GP', 0, 2)) }}
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Managed by</p>
                <p class="text-lg font-black text-gray-900 truncate">{{ $companyName ?: config('app.name') }}</p>
                <p class="text-xs font-bold text-emerald-600">Verified property manager</p>
            </div>
        </div>

        <p class="text-2xl sm:text-3xl font-black text-gray-900 mb-1">
            KES {{ number_format((float) $unit->rent_amount, 0) }}
            <span class="text-sm font-semibold text-gray-500">/ month</span>
        </p>
        <p class="text-xs text-gray-500 mb-5">Available for immediate viewing</p>

        <div class="space-y-2.5">
            <a href="{{ $applyUrl }}" class="public-btn public-btn-primary w-full !rounded-xl !py-3.5">
                Apply online
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
            @if ($whatsAppDigits)
                <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="public-btn w-full !rounded-xl !py-3 bg-[#25D366] hover:bg-[#1ebe57] text-white !shadow-lg !shadow-[#25D366]/30">
                    WhatsApp inquiry
                </a>
            @endif
            <a href="{{ $contactUrl }}" class="public-btn public-btn-secondary w-full !rounded-xl !py-3">
                Schedule viewing
            </a>
            @if ($phoneHref)
                <a href="tel:{{ $phoneHref }}" class="public-btn public-btn-secondary w-full !rounded-xl !py-3">
                    Request callback
                </a>
            @endif
        </div>

        <ul class="mt-5 pt-5 border-t border-gray-100 space-y-2 text-xs text-gray-600">
            <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Professionally managed unit</li>
            <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Secure online application</li>
            <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Fast response within 24hrs</li>
        </ul>
    </div>
</aside>
