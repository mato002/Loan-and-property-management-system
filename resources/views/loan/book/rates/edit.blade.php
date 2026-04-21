<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.collection_rates.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <form method="post" action="{{ route('loan.book.collection_rates.update', $rate) }}" class="px-5 py-6 space-y-4">
                @csrf
                @method('patch')
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">Branch</label>
                    <input id="branch" name="branch" value="{{ old('branch', $rate->branch) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="year" class="block text-xs font-semibold text-slate-600 mb-1">Year</label>
                        <input id="year" name="year" type="number" min="2000" max="2100" value="{{ old('year', $rate->year) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('year')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="month" class="block text-xs font-semibold text-slate-600 mb-1">Month</label>
                        <input id="month" name="month" type="number" min="1" max="12" value="{{ old('month', $rate->month) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('month')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="target_amount" class="block text-xs font-semibold text-slate-600 mb-1">Target amount</label>
                    <input id="target_amount" name="target_amount" type="number" step="0.01" min="0" value="{{ old('target_amount', $rate->target_amount) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('target_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $rate->notes) }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Update target</button>
                </div>
            </form>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-800">Current target</h3>
                <div class="mt-3 rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs text-slate-700 space-y-1">
                    <p><span class="text-slate-500">Branch:</span> <span class="font-semibold">{{ $rate->branch }}</span></p>
                    <p><span class="text-slate-500">Period:</span> <span class="font-semibold">{{ $rate->year }}-{{ str_pad((string) $rate->month, 2, '0', STR_PAD_LEFT) }}</span></p>
                    <p><span class="text-slate-500">Amount:</span> <span class="font-semibold tabular-nums">{{ number_format((float) $rate->target_amount, 2) }}</span></p>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
