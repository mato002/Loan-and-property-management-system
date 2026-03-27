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

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.06)] overflow-hidden">
            <form action="{{ route('public.apply.store') }}" method="POST">
                @csrf
                <div class="px-8 py-10 space-y-8">
                    <div>
                        <h3 class="text-2xl font-black text-gray-900 border-b border-gray-100 pb-4 mb-6">Quick Application</h3>
                        <p class="text-sm text-gray-500 mb-6">Only the essentials. We will contact you fast via phone/WhatsApp.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="full_name" class="block text-sm font-bold text-gray-700 mb-2">Full Name</label>
                                <input id="full_name" name="full_name" type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" required>
                            </div>
                            <div class="md:col-span-2">
                                <label for="phone" class="block text-sm font-bold text-gray-700 mb-2">Phone Number</label>
                                <input id="phone" name="phone" type="tel" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" placeholder="e.g. 07XXXXXXXX" required>
                            </div>
                            <div class="md:col-span-2">
                                <label for="email" class="block text-sm font-bold text-gray-700 mb-2">Email <span class="text-gray-400 font-medium">(Optional)</span></label>
                                <input id="email" name="email" type="email" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" placeholder="name@example.com">
                            </div>

                            @if ($applyUnit ?? null)
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Property</label>
                                    <input type="text" value="{{ $applyUnit->property->name }} — Unit {{ $applyUnit->label }}" class="w-full rounded-2xl border-gray-300 shadow-sm py-4 bg-gray-100 text-gray-700" readonly>
                                    <input type="hidden" name="property_unit_id" value="{{ $applyUnit->id }}">
                                </div>
                            @else
                                <div class="md:col-span-2">
                                    <label for="property" class="block text-sm font-bold text-gray-700 mb-2">Property</label>
                                    <input id="property" name="property" type="text" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none" placeholder="Property name or unit reference" required>
                                </div>
                            @endif

                            <div class="md:col-span-2">
                                <label for="move_in_date" class="block text-sm font-bold text-gray-700 mb-2">Move-in Date <span class="text-gray-400 font-medium">(Optional)</span></label>
                                <input id="move_in_date" name="move_in_date" type="date" class="w-full rounded-2xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-4 bg-gray-50 focus:bg-white outline-none">
                            </div>
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
