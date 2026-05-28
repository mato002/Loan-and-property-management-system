<x-public-layout
    :page-title="$publicPageTitle ?? 'Apply for a Rental'"
    :page-description="$publicPageDescription ?? null"
    :page-robots="$publicPageRobots ?? 'noindex,nofollow'"
>
    <section class="bg-gradient-to-b from-slate-50 to-white border-b border-gray-100">
        <div class="public-container py-8 sm:py-12 text-center max-w-3xl mx-auto">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600 mb-2">Quick application</p>
            <h1 class="public-section-title">Apply for a rental</h1>
            <p class="public-section-subtitle mx-auto">
                @if ($applyUnit ?? null)
                    Applying for <strong class="text-gray-900">{{ $applyUnit->property->name }} — Unit {{ $applyUnit->label }}</strong>. We'll contact you within 24 hours.
                @else
                    Submit your details and our team will match you with available units.
                @endif
            </p>
        </div>
    </section>

    <div class="public-container py-8 sm:py-12">
        <div class="max-w-2xl mx-auto bg-white rounded-2xl border border-gray-100 shadow-[0_12px_40px_rgb(0,0,0,0.06)] overflow-hidden">
            <form action="{{ route('public.apply.store') }}" method="POST">
                @csrf
                <div class="p-5 sm:p-8 space-y-5">
                    <div>
                        <label for="full_name" class="block text-xs font-bold text-gray-700 mb-1">Full name</label>
                        <input id="full_name" name="full_name" type="text" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-bold text-gray-700 mb-1">Phone number</label>
                        <input id="phone" name="phone" type="tel" placeholder="07XXXXXXXX" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                    </div>
                    <div>
                        <label for="email" class="block text-xs font-bold text-gray-700 mb-1">Email <span class="text-gray-400 font-medium">(optional)</span></label>
                        <input id="email" name="email" type="email" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    @if ($applyUnit ?? null)
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Property</label>
                            <input type="text" value="{{ $applyUnit->property->name }} — Unit {{ $applyUnit->label }}" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 bg-gray-50 text-sm" readonly>
                            <input type="hidden" name="property_unit_id" value="{{ $applyUnit->id }}">
                        </div>
                    @else
                        <div>
                            <label for="property" class="block text-xs font-bold text-gray-700 mb-1">Property reference</label>
                            <input id="property" name="property" type="text" placeholder="Building name or unit reference" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                        </div>
                    @endif

                    <div>
                        <label for="move_in_date" class="block text-xs font-bold text-gray-700 mb-1">Preferred move-in date</label>
                        <input id="move_in_date" name="move_in_date" type="date" class="w-full min-h-[2.75rem] rounded-xl border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>
                <div class="bg-gray-50 px-5 sm:px-8 py-5 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <label class="flex items-start gap-2 text-xs text-gray-700">
                        <input id="terms" type="checkbox" required class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        I confirm this information is accurate.
                    </label>
                    <button type="submit" class="public-btn public-btn-primary w-full sm:w-auto !px-8">Submit application</button>
                </div>
            </form>
        </div>
    </div>
</x-public-layout>
