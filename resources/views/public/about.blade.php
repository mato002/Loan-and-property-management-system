<x-public-layout>
    @php($brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name'))
    <!-- Hero Statement -->
    <div class="bg-indigo-600 py-24 sm:py-32 relative overflow-hidden">
        <div class="absolute inset-0 bg-indigo-900/20"></div>
        <div class="relative w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 text-center">
            <h1 class="text-5xl md:text-7xl font-black text-white tracking-tight mb-8">About {{ $brandName }}</h1>
            <p class="text-xl md:text-2xl text-indigo-100 max-w-4xl mx-auto font-medium leading-normal">We are revolutionizing the property management industry by bringing absolute transparency, speed, and design to the real estate market.</p>
        </div>
    </div>

    <!-- Content Story -->
    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-24">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div>
                <h2 class="text-4xl font-black text-gray-900 tracking-tight mb-8">Our Mission</h2>
                <div class="prose prose-lg text-gray-600">
                    <p class="mb-6 leading-relaxed">Founded in 2026, {{ $brandName }} was built on a simple premise: managing properties shouldn't be a nightmare. We observed landlords drowning in paperwork and tenants frustrated by slow maintenance responses.</p>
                    <p class="mb-6 leading-relaxed">We built a digital-first platform bridging the gap between property owners and renters, replacing outdated ledgers with stunning dashboards, automated flows, and complete visibility.</p>
                    <p class="leading-relaxed">Whether you are finding your next dream home, or bringing your entire portfolio loop under our management system, we guarantee an enterprise-grade experience without the traditional overhead friction.</p>
                </div>
                <div class="mt-10 flex gap-4">
                    <a href="{{ route('public.contact') }}" class="bg-indigo-600 text-white font-bold py-3.5 px-8 rounded-xl hover:bg-indigo-700 transition">Get In Touch</a>
                    <a href="{{ route('public.properties') }}" class="bg-white border border-gray-200 text-gray-700 font-bold py-3.5 px-8 rounded-xl hover:bg-gray-50 transition">View Listings</a>
                </div>
            </div>
            <div class="rounded-3xl overflow-hidden shadow-2xl relative">
                <div class="absolute inset-0 bg-indigo-600/10 mix-blend-multiply z-10"></div>
                <img src="https://images.unsplash.com/photo-1573164713988-8665fc963095?auto=format&fit=crop&w=1200&q=80" alt="Office team" class="w-full h-full object-cover">
            </div>
        </div>
    </div>

    <div class="bg-gray-50 border-y border-gray-200">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-20">
            <h2 class="text-3xl font-black text-gray-900 tracking-tight text-center mb-12">Meet Our Team</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white border border-gray-100 rounded-2xl p-6 text-center shadow-sm">
                    <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=400&q=80" alt="Managing director" class="w-20 h-20 rounded-full mx-auto object-cover mb-4">
                    <p class="text-lg font-black text-gray-900">James Otieno</p>
                    <p class="text-sm text-indigo-600 font-bold">Managing Director</p>
                </div>
                <div class="bg-white border border-gray-100 rounded-2xl p-6 text-center shadow-sm">
                    <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=400&q=80" alt="Head of lettings" class="w-20 h-20 rounded-full mx-auto object-cover mb-4">
                    <p class="text-lg font-black text-gray-900">Ann Wanjiku</p>
                    <p class="text-sm text-indigo-600 font-bold">Head of Lettings</p>
                </div>
                <div class="bg-white border border-gray-100 rounded-2xl p-6 text-center shadow-sm">
                    <img src="https://images.unsplash.com/photo-1568602471122-7832951cc4c5?auto=format&fit=crop&w=400&q=80" alt="Client success lead" class="w-20 h-20 rounded-full mx-auto object-cover mb-4">
                    <p class="text-lg font-black text-gray-900">David Kimani</p>
                    <p class="text-sm text-indigo-600 font-bold">Client Success Lead</p>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
