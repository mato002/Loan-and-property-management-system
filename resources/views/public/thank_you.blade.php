<x-public-layout>
    <div class="min-h-[70vh] flex flex-col items-center justify-center py-20 px-4">
        <div class="w-24 h-24 bg-green-100 text-green-500 rounded-full flex items-center justify-center mb-8 shadow-inner shadow-green-600/20">
            <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-4xl md:text-5xl font-black text-gray-900 tracking-tight mb-4 text-center">Success!</h1>
        <p class="text-xl text-gray-500 max-w-xl text-center mb-10 font-medium leading-relaxed">Your request has been securely submitted. One of our dedicated property managers will review it and reach out to you shortly.</p>
        
        <div class="flex flex-col sm:flex-row gap-4">
            <a href="{{ route('public.home') }}" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-center font-bold px-10 py-4 rounded-xl transition-colors shadow-sm">Return Home</a>
            <a href="{{ route('public.properties') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-center font-bold px-10 py-4 rounded-xl shadow-lg shadow-indigo-600/30 transition-all hover:-translate-y-0.5">Browse More Listings</a>
        </div>
    </div>
</x-public-layout>
