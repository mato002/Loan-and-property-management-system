<x-loan-layout>
    <x-loan.page title="New salary advance" subtitle="">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.advances.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-xl overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.advances.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="employee_id" class="block text-xs font-semibold text-slate-600 mb-1">Employee</label>
                    <select id="employee_id" name="employee_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select…</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->employee_number }} · {{ $e->full_name }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="currency" class="block text-xs font-semibold text-slate-600 mb-1">Currency</label>
                    <input id="currency" name="currency" value="{{ old('currency', 'KES') }}" maxlength="8" class="w-full rounded-lg border-slate-200 text-sm uppercase" />
                    @error('currency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="requested_on" class="block text-xs font-semibold text-slate-600 mb-1">Requested on</label>
                    <input id="requested_on" name="requested_on" type="date" value="{{ old('requested_on', now()->toDateString()) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('requested_on')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
