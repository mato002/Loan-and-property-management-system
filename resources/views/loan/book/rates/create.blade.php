<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.collection_rates.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <form method="post" action="{{ route('loan.book.collection_rates.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">Branch</label>
                    <input id="branch" name="branch" value="{{ old('branch') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="year" class="block text-xs font-semibold text-slate-600 mb-1">Year</label>
                        <input id="year" name="year" type="number" min="2000" max="2100" value="{{ old('year', (int) now()->format('Y')) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('year')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="month" class="block text-xs font-semibold text-slate-600 mb-1">Month (1–12)</label>
                        <input id="month" name="month" type="number" min="1" max="12" value="{{ old('month', (int) now()->format('n')) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('month')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="target_amount" class="block text-xs font-semibold text-slate-600 mb-1">Target amount</label>
                    <input id="target_amount" name="target_amount" type="number" step="0.01" min="0" value="{{ old('target_amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('target_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save target</button>
                </div>
            </form>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-800">Target guidance</h3>
                <ul class="mt-3 space-y-2 text-xs text-slate-600">
                    <li>Set one branch target per month for cleaner reporting.</li>
                    <li>Use notes for assumptions (seasonality, campaigns).</li>
                    <li>Targets feed Collection MTD performance context.</li>
                </ul>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
