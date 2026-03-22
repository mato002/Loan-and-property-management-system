<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.mpesa_payouts') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-3xl">
            <form method="post" action="{{ route('loan.financial.mpesa_payouts.update', $batch) }}" class="px-5 py-6 space-y-4">
                @csrf
                @method('patch')
                <div>
                    <label for="reference" class="block text-xs font-semibold text-slate-600 mb-1">Reference</label>
                    <input id="reference" name="reference" value="{{ old('reference', $batch->reference) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="recipient_count" class="block text-xs font-semibold text-slate-600 mb-1">Recipients</label>
                        <input id="recipient_count" name="recipient_count" type="number" min="1" value="{{ old('recipient_count', $batch->recipient_count) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('recipient_count')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="total_amount" class="block text-xs font-semibold text-slate-600 mb-1">Total amount</label>
                        <input id="total_amount" name="total_amount" type="number" step="0.01" min="0.01" value="{{ old('total_amount', $batch->total_amount) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('total_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select id="status" name="status" class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['draft' => 'Draft', 'queued' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed'] as $val => $lab)
                            <option value="{{ $val }}" @selected(old('status', $batch->status) === $val)>{{ $lab }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $batch->notes) }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        Save changes
                    </button>
                    <a href="{{ route('loan.financial.mpesa_payouts') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
