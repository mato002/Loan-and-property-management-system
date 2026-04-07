<x-public-layout>
    @php
        $brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: 'Gaitho Property Agency';
        $contactEmail = \App\Models\PropertyPortalSetting::getValue('contact_email_primary', '') ?: 'gaithoarthur17@gmail.com';
        $contactPhone = \App\Models\PropertyPortalSetting::getValue('contact_phone', '') ?: '0717018779';
    @endphp

    <section class="relative border-b border-gray-200 py-16 sm:py-20 overflow-hidden">
        <div class="absolute inset-0">
            <img
                src="https://images.unsplash.com/photo-1460317442991-0ec209397118?auto=format&fit=crop&w=2400&q=80"
                alt="Modern apartment exterior"
                class="w-full h-full object-cover"
            >
            <div class="absolute inset-0 bg-slate-900/70"></div>
        </div>
        <div class="relative w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 min-h-[440px] flex items-center justify-center">
            <div class="max-w-5xl text-center rounded-3xl bg-slate-950/35 backdrop-blur-[2px] px-6 py-10 sm:px-10 sm:py-12 border border-white/10 shadow-[0_20px_60px_rgba(2,6,23,0.45)]">
                <p class="text-gray-200 text-xs sm:text-sm font-semibold uppercase tracking-[0.24em] drop-shadow-[0_2px_8px_rgba(15,23,42,0.7)]">About us</p>
                <h1 class="mt-4 text-5xl sm:text-6xl lg:text-7xl font-black tracking-tight text-white leading-[0.95] drop-shadow-[0_6px_20px_rgba(15,23,42,0.95)]">{{ $brandName }}</h1>
                <p class="mt-5 text-xl sm:text-2xl text-gray-200 font-semibold drop-shadow-[0_2px_8px_rgba(15,23,42,0.85)]">Relax, we got you.</p>
                <p class="mt-6 text-lg sm:text-xl leading-relaxed text-white max-w-4xl mx-auto drop-shadow-[0_3px_10px_rgba(15,23,42,0.95)]">
                    We specialize in hands-free rental management for busy landlords. From rent collection to tenant follow-ups,
                    our team ensures your property works for you with less stress and consistent reporting.
                </p>
            </div>
        </div>
    </section>

    <section class="py-16 sm:py-20 bg-white">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 sm:p-8">
                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Why partner with {{ $brandName }}?</h2>
                    <p class="mt-4 text-slate-700 leading-relaxed">
                        We run day-to-day rental operations with accountability, timely updates, and practical support.
                        Landlords get transparency on performance while tenants receive faster communication and service.
                    </p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 sm:p-8">
                    <h3 class="text-xl font-black text-emerald-900">What makes us different</h3>
                    <ul class="mt-4 space-y-2 text-emerald-900">
                        <li>Personalized landlord service</li>
                        <li>Transparent earnings breakdown</li>
                        <li>Available on both mobile and laptop</li>
                        <li>Simple setup and no hidden charges</li>
                        <li>Timely payouts and local office support</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="py-14 sm:py-16 bg-slate-50 border-y border-slate-200">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20">
            <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">What we offer landlords</h2>
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-extrabold text-slate-900">Full rent collection and tracking</h3>
                    <p class="mt-2 text-sm text-slate-600">Stop chasing rent manually. We follow up and keep a clear record of inflows.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-extrabold text-slate-900">SMS and WhatsApp alerts</h3>
                    <p class="mt-2 text-sm text-slate-600">Tenants receive reminders and you stay updated from one connected workflow.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-extrabold text-slate-900">Monthly reports and statements</h3>
                    <p class="mt-2 text-sm text-slate-600">Review collections, expenses, balances, and overall performance at a glance.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-extrabold text-slate-900">Online landlord portal access</h3>
                    <p class="mt-2 text-sm text-slate-600">View property status, statements, and collection trends anywhere.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20">
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-8 sm:p-10 text-center shadow-sm">
                <h2 class="text-3xl sm:text-4xl font-black text-gray-900 tracking-tight">Let your property earn. We will do the work.</h2>
                <p class="mt-4 text-gray-700 text-base sm:text-lg">
                    Call or WhatsApp: <span class="font-bold text-gray-900">{{ $contactPhone }}</span>
                    <span class="hidden sm:inline"> · </span>
                    <br class="sm:hidden">
                    Email: <span class="font-bold text-gray-900">{{ $contactEmail }}</span>
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('public.contact') }}" class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-extrabold text-white hover:bg-emerald-700 transition">Contact us</a>
                    <a href="{{ route('public.properties') }}" class="rounded-xl border border-emerald-300 px-6 py-3 text-sm font-extrabold text-emerald-700 hover:bg-emerald-100 transition">View listings</a>
                </div>
            </div>
        </div>
    </section>
</x-public-layout>
