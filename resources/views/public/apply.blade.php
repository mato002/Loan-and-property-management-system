<x-public-layout>
    <div class="bg-gray-50 border-b border-gray-200">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h1 class="text-4xl font-black text-gray-900 tracking-tight mb-4">Rental Application</h1>
            <p class="text-lg text-gray-500">
                @if ($applyUnit ?? null)
                    You are applying for <span class="font-bold text-gray-900">{{ $applyUnit->property->name }} — Unit {{ $applyUnit->label }}</span>. Please complete all fields accurately.
                @else
                    Complete the form below. If you opened this page from a listing, the unit reference may be missing — go back and use <strong>Apply Online</strong> from the listing detail page.
                @endif
            </p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.06)] overflow-hidden">
            <form action="{{ route('public.thank_you') }}" method="GET">
                <div class="px-8 py-10 space-y-10">
                    
                    <!-- Section 1 -->
                    <div>
                        <h3 class="text-2xl font-black text-gray-900 border-b border-gray-100 pb-4 mb-6">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">First Name</label>
                                <input type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Last Name</label>
                                <input type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                                <input type="email" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" required>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2 -->
                    <div>
                        <h3 class="text-2xl font-black text-gray-900 border-b border-gray-100 pb-4 mb-6">Employment & Income</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 mb-2">Current Employer</label>
                                <input type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Job Title</label>
                                <input type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Monthly Income ($)</label>
                                <input type="number" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" required>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3 -->
                    <div>
                        <h3 class="text-2xl font-black text-gray-900 border-b border-gray-100 pb-4 mb-6">Documents</h3>
                        <div class="border-2 border-dashed border-gray-300 rounded-3xl p-10 text-center hover:bg-gray-50 cursor-pointer transition-colors bg-white">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <p class="text-gray-900 font-bold mb-1">Click to upload your ID & Pay Stubs</p>
                            <p class="text-sm text-gray-500 font-medium">PDF, PNG, or JPG up to 10MB</p>
                        </div>
                    </div>

                </div>
                <div class="bg-gray-50 px-8 py-6 border-t border-gray-100 flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="terms" type="checkbox" required class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="terms" class="ml-3 block text-sm font-medium text-gray-900">
                            I verify this information is accurate.
                        </label>
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-black px-10 py-5 rounded-xl shadow-xl shadow-indigo-600/30 transition-transform hover:-translate-y-1">
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-public-layout>
