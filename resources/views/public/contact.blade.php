<x-public-layout>
    @php($brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name'))
    @php($contactEmailPrimary = \App\Models\PropertyPortalSetting::getValue('contact_email_primary', '') ?: 'hello@primeestate.com')
    @php($contactEmailSupport = \App\Models\PropertyPortalSetting::getValue('contact_email_support', '') ?: 'support@primeestate.com')
    @php($contactPhone = \App\Models\PropertyPortalSetting::getValue('contact_phone', '') ?: '1-800-555-0199')
    @php($contactWhatsapp = \App\Models\PropertyPortalSetting::getValue('contact_whatsapp', '') ?: '+18005550199')
    @php($contactAddress = \App\Models\PropertyPortalSetting::getValue('contact_address', '') ?: "123 Estate Blvd, Suite 400\nMetropolis, NY 10012")
    @php($contactMapEmbedUrl = \App\Models\PropertyPortalSetting::getValue('contact_map_embed_url', '') ?: 'https://maps.google.com/maps?q=123%20Estate%20Blvd%20Suite%20400%20Metropolis%20NY%2010012&t=&z=13&ie=UTF8&iwloc=&output=embed')
    @php($whatsAppDigits = preg_replace('/\D+/', '', $contactWhatsapp) ?: '18005550199')
    <!-- Header -->
    <div class="relative border-b border-gray-200">
        <div class="absolute inset-0 overflow-hidden">
            <img
                src="https://images.unsplash.com/photo-1467269204594-9661b134dd2b?auto=format&fit=crop&w=2200&q=80"
                alt="Modern property skyline"
                class="w-full h-full object-cover brightness-[0.75]"
            >
            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/65 via-slate-900/55 to-slate-950/65"></div>
        </div>
        <div class="relative w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-24 text-center">
            <div class="inline-block max-w-3xl rounded-2xl bg-slate-950/25 px-6 py-5 sm:px-8 sm:py-6 ring-1 ring-white/20 backdrop-blur-[1px]">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-200 mb-4">Contact {{ $brandName }}</p>
                <h1 class="text-5xl md:text-6xl font-black text-white tracking-tight mb-6 drop-shadow-[0_4px_14px_rgba(15,23,42,0.95)]">Get in Touch</h1>
                <p class="text-xl text-slate-100 max-w-2xl mx-auto font-medium drop-shadow-[0_2px_8px_rgba(15,23,42,0.9)]">Have questions about our properties or management services? Our dedicated account managers are here to help.</p>
            </div>
        </div>
    </div>

    <!-- Main Section -->
    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-24">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-20">
            <!-- Details -->
            <div>
                <h2 class="text-3xl font-black text-gray-900 tracking-tight mb-10">Contact Information</h2>
                <div class="space-y-10 text-lg font-bold text-gray-700">
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0 text-emerald-600">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-gray-900 font-black tracking-wide uppercase text-sm mb-2 text-emerald-600">Headquarters</p>
                            <p class="leading-relaxed">{!! nl2br(e($contactAddress)) !!}</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0 text-emerald-600">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-gray-900 font-black tracking-wide uppercase text-sm mb-2 text-emerald-600">Email Address</p>
                            <p class="leading-relaxed">{{ $contactEmailPrimary }}<br>{{ $contactEmailSupport }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0 text-emerald-600">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <div>
                            <p class="text-gray-900 font-black tracking-wide uppercase text-sm mb-2 text-emerald-600">Phone</p>
                            <p class="leading-relaxed">{{ $contactPhone }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0 text-emerald-600">
                            <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.5 0 .16 5.34.16 11.9c0 2.1.55 4.14 1.6 5.95L0 24l6.32-1.66a11.84 11.84 0 0 0 5.73 1.47h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.16-3.44-8.43Z"/></svg>
                        </div>
                        <div>
                            <p class="text-gray-900 font-black tracking-wide uppercase text-sm mb-2 text-emerald-600">WhatsApp</p>
                            <a href="https://wa.me/{{ $whatsAppDigits }}" target="_blank" rel="noopener noreferrer" class="leading-relaxed text-emerald-600 hover:text-emerald-700">{{ $contactWhatsapp }}</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <div class="bg-white rounded-[2rem] border border-gray-100 p-8 shadow-[0_8px_30px_rgb(0,0,0,0.06)]">
                <form action="{{ route('public.thank_you') }}" method="GET" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">First Name</label>
                            <input type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 py-4 bg-gray-50 focus:bg-white transition-colors outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Last Name</label>
                            <input type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 py-4 bg-gray-50 focus:bg-white transition-colors outline-none" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                        <input type="email" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 py-4 bg-gray-50 focus:bg-white transition-colors outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Message</label>
                        <textarea rows="5" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 py-4 bg-gray-50 focus:bg-white transition-colors outline-none" required></textarea>
                    </div>
                    <button type="submit" class="w-full flex justify-center items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-lg py-5 rounded-2xl transition-all hover:-translate-y-1 shadow-xl shadow-emerald-600/30">
                        Send Message
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 pb-24">
        <div class="rounded-3xl overflow-hidden border border-gray-200 shadow-sm">
            <iframe
                title="{{ $brandName }} office location"
                src="{{ $contactMapEmbedUrl }}"
                class="w-full h-80"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
        </div>
    </div>
</x-public-layout>
