<x-loan-layout>
    <x-loan.page title="Edit {{ $row->reference }}" subtitle="">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.requisitions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-xl overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.requisitions.update', $row) }}" class="px-5 py-6 space-y-4">
                @csrf
                @method('patch')
                <div>
                    <label for="title" class="block text-xs font-semibold text-slate-600 mb-1">Title</label>
                    <input id="title" name="title" value="{{ old('title', $row->title) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="purpose" class="block text-xs font-semibold text-slate-600 mb-1">Purpose / details</label>
                    <textarea id="purpose" name="purpose" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('purpose', $row->purpose) }}</textarea>
                    @error('purpose')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', $row->amount) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="currency" class="block text-xs font-semibold text-slate-600 mb-1">Currency</label>
                    <input id="currency" name="currency" value="{{ old('currency', $row->currency) }}" maxlength="8" class="w-full rounded-lg border-slate-200 text-sm uppercase" />
                    @error('currency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $row->notes) }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
