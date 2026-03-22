<x-loan-layout>
    <x-loan.page title="New journal entry" subtitle="Total debits must equal total credits. Leave unused lines at zero.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.journal.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.journal.store') }}" class="px-5 py-6 space-y-6">
                @csrf
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="entry_date" class="block text-xs font-semibold text-slate-600 mb-1">Entry date</label>
                        <input id="entry_date" name="entry_date" type="date" value="{{ old('entry_date', now()->toDateString()) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('entry_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="reference" class="block text-xs font-semibold text-slate-600 mb-1">Reference</label>
                        <input id="reference" name="reference" value="{{ old('reference') }}" class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                        @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-3">
                        <label for="description" class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                        <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                @error('lines')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                <div class="overflow-x-auto border border-slate-100 rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Account</th>
                                <th class="px-3 py-2 w-28">Debit</th>
                                <th class="px-3 py-2 w-28">Credit</th>
                                <th class="px-3 py-2">Memo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @for ($i = 0; $i < 6; $i++)
                                <tr>
                                    <td class="px-3 py-2">
                                        <select name="lines[{{ $i }}][accounting_chart_account_id]" class="w-full rounded-lg border-slate-200 text-xs sm:text-sm">
                                            <option value="">—</option>
                                            @foreach ($accounts as $a)
                                                <option value="{{ $a->id }}" @selected(old('lines.'.$i.'.accounting_chart_account_id') == $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('lines.'.$i)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" value="{{ old('lines.'.$i.'.debit') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" value="{{ old('lines.'.$i.'.credit') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" name="lines[{{ $i }}][memo]" value="{{ old('lines.'.$i.'.memo') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-slate-500">Use at least two lines with amounts. Each line is either a debit or a credit.</p>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Post entry</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
