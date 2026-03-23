<x-public-layout>
    <!-- Hero Statement -->
    <div class="bg-indigo-600 py-24 sm:py-32 relative overflow-hidden">
        <div class="absolute inset-0 bg-indigo-900/20"></div>
        <div class="relative w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 text-center">
            <h1 class="text-5xl md:text-7xl font-black text-white tracking-tight mb-8">About PrimeEstate</h1>
            <p class="text-xl md:text-2xl text-indigo-100 max-w-4xl mx-auto font-medium leading-normal">We are revolutionizing the property management industry by bringing absolute transparency, speed, and design to the real estate market.</p>
        </div>
    </div>

    <!-- Content Story -->
    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-24">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div>
                <h2 class="text-4xl font-black text-gray-900 tracking-tight mb-8">Our Mission</h2>
                <div class="prose prose-lg text-gray-600">
                    <p class="mb-6 leading-relaxed">Founded in 2026, PrimeEstate was built on a simple premise: managing properties shouldn't be a nightmare. We observed landlords drowning in paperwork and tenants frustrated by slow maintenance responses.</p>
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
</x-public-layout>
