@php
    $oldLines = old('lines', [
        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
    ]);
    $defaultMap = [
        'accounts_receivable' => 'Accounts Receivable',
        'rental_income' => 'Rental Income',
        'cash_bank' => 'Cash / Bank',
        'maintenance_expense' => 'Maintenance Expense',
        'accounts_payable' => 'Accounts Payable',
    ];
@endphp
<x-property.workspace
    title="Journal entry management"
    subtitle="Controlled double-entry posting with reversal-only correction."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No journal entries"
    empty-hint="Post your first balanced journal below."
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.entries.export', request()->query()),
            'pdfUrl' => route('property.accounting.entries.export', array_merge(request()->query(), ['format' => 'pdf'])),
        ])
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.entries') }}" class="w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div>
                    <label class="block text-xs font-medium text-slate-600">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Source</label>
                    <select name="source" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All</option>
                        <option value="manual" @selected(($filters['source'] ?? '') === 'manual')>Manual</option>
                        <option value="system" @selected(($filters['source'] ?? '') === 'system')>System</option>
                        @foreach(($sourceOptions ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach(($statusOptions ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Account</label>
                    <select name="account_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach(($accounts ?? collect()) as $acc)
                            <option value="{{ $acc->id }}" @selected((int)($filters['account_id'] ?? 0) === (int)$acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Property</label>
                    <select name="property_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach(($properties ?? collect()) as $property)
                            <option value="{{ $property->id }}" @selected((int)($filters['property_id'] ?? 0) === (int)$property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-6">
                    <label class="block text-xs font-medium text-slate-600">Search</label>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, description, source..." class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.accounting.entries') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </x-slot>

    <x-slot name="above">
        <form method="post" action="{{ route('property.accounting.settings.account_map.save') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3 max-w-5xl">
            @csrf
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Auto-posting account mapping</h3>
                <button type="button" id="mapping-reset-defaults" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Reset to default</button>
            </div>
            <p class="text-xs text-slate-500">Only active chart accounts can be mapped.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($defaultMap as $mapKey => $defaultLabel)
                    <div>
                        <label class="block text-xs font-medium text-slate-600">{{ ucwords(str_replace('_', ' ', $mapKey)) }}</label>
                        <select
                            name="{{ $mapKey }}"
                            required
                            data-default-label="{{ $defaultLabel }}"
                            class="mapping-select mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        >
                            <option value="">Select account</option>
                            @foreach(($accounts ?? collect()) as $acc)
                                <option
                                    value="{{ $acc->id }}"
                                    data-label="{{ $acc->name }}"
                                    @selected((old($mapKey) && (int) old($mapKey) === (int) $acc->id) || (!old($mapKey) && (($accountMap[$mapKey] ?? '') === $acc->name)))
                                >
                                    {{ $acc->code }} - {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Save mapping</button>
        </form>

        <form method="post" action="{{ route('property.accounting.entries.store') }}" id="journal-builder-form" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3 max-w-6xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900">Create journal entry</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Date</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Reference</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Auto if blank" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Property (optional)</label>
                    <select name="property_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">General</option>
                        @foreach(($properties ?? collect()) as $property)
                            <option value="{{ $property->id }}" @selected((string) old('property_id') === (string) $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-xs font-medium text-slate-600">Description</label>
                    <input type="text" name="description" value="{{ old('description') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Journal narrative" />
                </div>
            </div>

            @error('lines')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror
            <div class="overflow-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-sm" id="journal-lines-table">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-slate-600">
                            <th class="px-3 py-2">Account</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2">Debit</th>
                            <th class="px-3 py-2">Credit</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody id="journal-lines-body">
                        @foreach($oldLines as $i => $line)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">
                                    <select name="lines[{{ $i }}][account_id]" required class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                                        <option value="">Select account</option>
                                        @foreach(($accounts ?? collect()) as $acc)
                                            <option value="{{ $acc->id }}" @selected((string)($line['account_id'] ?? '') === (string)$acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? '' }}" class="line-debit w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-right" />
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? '' }}" class="line-credit w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-right" />
                                </td>
                                <td class="px-3 py-2">
                                    <button type="button" class="remove-line rounded-lg border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Remove</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="add-line" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Add line</button>
                <p class="text-xs text-slate-500">At least two lines required. Debit and credit must balance.</p>
            </div>

            <div id="journal-totals" class="sticky bottom-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="grid gap-2 sm:grid-cols-3 text-sm">
                    <p><span class="font-semibold">Total Debit:</span> <span id="total-debit">KES 0.00</span></p>
                    <p><span class="font-semibold">Total Credit:</span> <span id="total-credit">KES 0.00</span></p>
                    <p><span class="font-semibold">Difference:</span> <span id="total-diff">KES 0.00</span></p>
                </div>
                <p id="balance-hint" class="mt-2 text-xs font-semibold text-rose-700">Unbalanced entry.</p>
            </div>

            <button type="submit" id="submit-journal" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-400">Post journal</button>
        </form>
    </x-slot>

    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset

    <script>
        (function () {
            const currency = new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' });
            const body = document.getElementById('journal-lines-body');
            const addBtn = document.getElementById('add-line');
            const submitBtn = document.getElementById('submit-journal');
            const totalDebitEl = document.getElementById('total-debit');
            const totalCreditEl = document.getElementById('total-credit');
            const totalDiffEl = document.getElementById('total-diff');
            const balanceHint = document.getElementById('balance-hint');
            const mappingResetBtn = document.getElementById('mapping-reset-defaults');
            const mappingSelects = document.querySelectorAll('.mapping-select');
            const accountOptionsHtml = @json(($accounts ?? collect())->map(fn ($acc) => '<option value="'.$acc->id.'">'.$acc->code.' - '.e($acc->name).'</option>')->implode(''));

            const ensureRowHint = (row) => {
                let hint = row.querySelector('.line-hint');
                if (!hint) {
                    hint = document.createElement('p');
                    hint.className = 'line-hint mt-1 text-[11px] font-medium';
                    row.querySelector('td:last-child')?.appendChild(hint);
                }
                return hint;
            };

            const validateRows = () => {
                const rows = Array.from(body.querySelectorAll('tr'));
                let nonEmptyRows = 0;
                let hasInvalid = false;

                rows.forEach((row) => {
                    const account = row.querySelector('select')?.value || '';
                    const debit = parseFloat(row.querySelector('.line-debit')?.value || '0');
                    const credit = parseFloat(row.querySelector('.line-credit')?.value || '0');
                    const hasDebit = Number.isFinite(debit) && debit > 0;
                    const hasCredit = Number.isFinite(credit) && credit > 0;
                    const hasAmount = hasDebit || hasCredit;
                    const isBlank = !account && !hasAmount;
                    const hint = ensureRowHint(row);

                    hint.textContent = '';
                    hint.classList.remove('text-rose-700', 'text-emerald-700');

                    if (isBlank) {
                        return;
                    }

                    nonEmptyRows += 1;
                    if (!account) {
                        hasInvalid = true;
                        hint.textContent = 'Select an account.';
                        hint.classList.add('text-rose-700');
                        return;
                    }
                    if (hasDebit && hasCredit) {
                        hasInvalid = true;
                        hint.textContent = 'Use only one side: debit OR credit.';
                        hint.classList.add('text-rose-700');
                        return;
                    }
                    if (!hasAmount) {
                        hasInvalid = true;
                        hint.textContent = 'Enter a positive debit or credit amount.';
                        hint.classList.add('text-rose-700');
                        return;
                    }

                    hint.textContent = 'Line valid.';
                    hint.classList.add('text-emerald-700');
                });

                return { hasInvalid, nonEmptyRows };
            };

            const removeBlankRows = () => {
                const rows = Array.from(body.querySelectorAll('tr'));
                rows.forEach((row) => {
                    const account = row.querySelector('select')?.value || '';
                    const debit = parseFloat(row.querySelector('.line-debit')?.value || '0');
                    const credit = parseFloat(row.querySelector('.line-credit')?.value || '0');
                    const hasAmount = (Number.isFinite(debit) && debit > 0) || (Number.isFinite(credit) && credit > 0);
                    if (!account && !hasAmount && body.querySelectorAll('tr').length > 2) {
                        row.remove();
                    }
                });
                reindexRows();
            };

            const computeTotals = () => {
                let debit = 0;
                let credit = 0;
                body.querySelectorAll('tr').forEach((row) => {
                    const d = parseFloat(row.querySelector('.line-debit')?.value || '0');
                    const c = parseFloat(row.querySelector('.line-credit')?.value || '0');
                    debit += Number.isFinite(d) ? d : 0;
                    credit += Number.isFinite(c) ? c : 0;
                });
                const diff = debit - credit;
                const rowState = validateRows();
                totalDebitEl.textContent = currency.format(debit);
                totalCreditEl.textContent = currency.format(credit);
                totalDiffEl.textContent = currency.format(diff);

                const isBalanced = Math.abs(diff) < 0.0001 && (rowState?.nonEmptyRows || 0) >= 2 && !rowState?.hasInvalid;
                submitBtn.disabled = !isBalanced;
                if (isBalanced) {
                    balanceHint.textContent = 'Balanced entry. Ready to post.';
                    balanceHint.classList.remove('text-rose-700');
                    balanceHint.classList.add('text-emerald-700');
                } else {
                    balanceHint.textContent = 'Unbalanced entry.';
                    balanceHint.classList.remove('text-emerald-700');
                    balanceHint.classList.add('text-rose-700');
                }
            };

            const reindexRows = () => {
                body.querySelectorAll('tr').forEach((row, idx) => {
                    row.querySelectorAll('select,input').forEach((input) => {
                        const name = input.getAttribute('name') || '';
                        const next = name.replace(/lines\[\d+\]/, `lines[${idx}]`);
                        input.setAttribute('name', next);
                    });
                });
            };

            const addLine = () => {
                const idx = body.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.className = 'border-t border-slate-100';
                tr.innerHTML = `
                    <td class="px-3 py-2"><select name="lines[${idx}][account_id]" required class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"><option value="">Select account</option>${accountOptionsHtml}</select></td>
                    <td class="px-3 py-2"><input type="text" name="lines[${idx}][description]" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" /></td>
                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[${idx}][debit]" class="line-debit w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-right" /></td>
                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[${idx}][credit]" class="line-credit w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-right" /></td>
                    <td class="px-3 py-2"><button type="button" class="remove-line rounded-lg border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Remove</button></td>
                `;
                body.appendChild(tr);
                computeTotals();
            };

            if (addBtn) {
                addBtn.addEventListener('click', addLine);
            }
            body.addEventListener('input', computeTotals);
            body.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-line')) return;
                if (body.querySelectorAll('tr').length <= 2) return;
                target.closest('tr')?.remove();
                reindexRows();
                computeTotals();
            });

            document.getElementById('journal-builder-form')?.addEventListener('submit', (event) => {
                removeBlankRows();
                computeTotals();
                if (submitBtn.disabled) {
                    event.preventDefault();
                    balanceHint.textContent = 'Fix validation errors and ensure totals balance before posting.';
                    balanceHint.classList.remove('text-emerald-700');
                    balanceHint.classList.add('text-rose-700');
                }
            });

            if (mappingResetBtn) {
                mappingResetBtn.addEventListener('click', () => {
                    mappingSelects.forEach((select) => {
                        const def = select.getAttribute('data-default-label');
                        const option = Array.from(select.options).find((opt) => opt.getAttribute('data-label') === def);
                        select.value = option ? option.value : '';
                    });
                });
            }

            computeTotals();
        })();
    </script>
</x-property.workspace>
