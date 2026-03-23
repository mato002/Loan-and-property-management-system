<x-public-layout>
    <!-- Header -->
    <div class="bg-gray-50 border-b border-gray-200">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-20 text-center">
            <h1 class="text-5xl md:text-6xl font-black text-gray-900 tracking-tight mb-6">Get in Touch</h1>
            <p class="text-xl text-gray-500 max-w-2xl mx-auto font-medium">Have questions about our properties or management services? Our dedicated account managers are here to help.</p>
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
                        <div class="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0 text-indigo-600">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-gray-900 font-black tracking-wide uppercase text-sm mb-2 text-indigo-600">Headquarters</p>
                            <p class="leading-relaxed">123 Estate Blvd, Suite 400<br>Metropolis, NY 10012</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0 text-indigo-600">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-gray-900 font-black tracking-wide uppercase text-sm mb-2 text-indigo-600">Email Address</p>
                            <p class="leading-relaxed">hello@primeestate.com<br>support@primeestate.com</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0 text-indigo-600">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <div>
                            <p class="text-gray-900 font-black tracking-wide uppercase text-sm mb-2 text-indigo-600">Phone</p>
                            <p class="leading-relaxed">1-800-555-0199</p>
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
                            <input type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white transition-colors outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Last Name</label>
                            <input type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white transition-colors outline-none" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                        <input type="email" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white transition-colors outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Message</label>
                        <textarea rows="5" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white transition-colors outline-none" required></textarea>
                    </div>
                    <button type="submit" class="w-full flex justify-center items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-lg py-5 rounded-2xl transition-all hover:-translate-y-1 shadow-xl shadow-indigo-600/30">
                        Send Message
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-public-layout>
