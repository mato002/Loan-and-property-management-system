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
                                <th class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        @php
                            $oldLines = old('lines', []);
                            $rowCount = max(2, is_array($oldLines) ? count($oldLines) : 0);
                        @endphp
                        <tbody id="journal-lines" class="divide-y divide-slate-100" data-next-index="{{ $rowCount }}">
                            @for ($i = 0; $i < $rowCount; $i++)
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
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" class="js-remove-line text-slate-400 hover:text-red-600" title="Remove">×</button>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="add-line" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Add fields</button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Post entry</button>
                </div>
                <p class="text-xs text-slate-500">Use at least one line with an amount. A line can be a debit, a credit, or equal debit and credit.</p>
            </form>
        </div>
    </x-loan.page>

    <template id="journal-line-template">
        <tr>
            <td class="px-3 py-2">
                <select name="lines[__INDEX__][accounting_chart_account_id]" class="w-full rounded-lg border-slate-200 text-xs sm:text-sm">
                    <option value="">—</option>
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>
                    @endforeach
                </select>
            </td>
            <td class="px-3 py-2">
                <input type="number" step="0.01" min="0" name="lines[__INDEX__][debit]" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
            </td>
            <td class="px-3 py-2">
                <input type="number" step="0.01" min="0" name="lines[__INDEX__][credit]" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
            </td>
            <td class="px-3 py-2">
                <input type="text" name="lines[__INDEX__][memo]" class="w-full rounded-lg border-slate-200 text-sm" />
            </td>
            <td class="px-3 py-2 text-right">
                <button type="button" class="js-remove-line text-slate-400 hover:text-red-600" title="Remove">×</button>
            </td>
        </tr>
    </template>

    <script>
        (function () {
            const tbody = document.getElementById('journal-lines');
            const addBtn = document.getElementById('add-line');
            const tpl = document.getElementById('journal-line-template');
            if (!tbody || !addBtn || !tpl) return;

            function nextIndex() {
                const cur = parseInt(tbody.dataset.nextIndex || '0', 10) || 0;
                tbody.dataset.nextIndex = String(cur + 1);
                return cur;
            }

            function addRow() {
                const idx = nextIndex();
                const html = tpl.innerHTML.replaceAll('__INDEX__', String(idx));
                tbody.insertAdjacentHTML('beforeend', html);
            }

            function onRemove(e) {
                const btn = e.target.closest('.js-remove-line');
                if (!btn) return;
                const tr = btn.closest('tr');
                if (!tr) return;

                // keep at least one row visible
                if (tbody.querySelectorAll('tr').length <= 1) {
                    tr.querySelectorAll('input').forEach(i => i.value = '');
                    tr.querySelectorAll('select').forEach(s => s.value = '');
                    return;
                }

                tr.remove();
            }

            addBtn.addEventListener('click', addRow);
            tbody.addEventListener('click', onRemove);
        })();
    </script>
</x-loan-layout>
