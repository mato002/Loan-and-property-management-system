<x-property.workspace
    title="Accounting entries"
    subtitle="Record debits and credits for property operations."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No accounting entries"
    empty-hint="Start by recording your first journal row."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.accounting.entries.export', ['reversal' => $reversalFilter ?? null, 'source_key' => $sourceFilter ?? null, 'q' => request('q')]) }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Export CSV</a>
    </x-slot>

    <x-slot name="toolbar">
        <select name="reversal-filter" onchange="window.location=this.value" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="{{ route('property.accounting.entries', array_merge(request()->query(), ['reversal' => null])) }}" @selected(($reversalFilter ?? '') === '')>Reversals: All</option>
            <option value="{{ route('property.accounting.entries', array_merge(request()->query(), ['reversal' => 'only_reversals'])) }}" @selected(($reversalFilter ?? '') === 'only_reversals')>Reversals only</option>
            <option value="{{ route('property.accounting.entries', array_merge(request()->query(), ['reversal' => 'without_reversals'])) }}" @selected(($reversalFilter ?? '') === 'without_reversals')>Original entries only</option>
        </select>
        <select name="source-filter" onchange="window.location=this.value" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="{{ route('property.accounting.entries', array_merge(request()->query(), ['source_key' => null])) }}">Source: All</option>
            @foreach (($sourceOptions ?? collect()) as $source)
                <option value="{{ route('property.accounting.entries', array_merge(request()->query(), ['source_key' => $source])) }}" @selected(($sourceFilter ?? '') === $source)>{{ $source }}</option>
            @endforeach
        </select>
        <form method="get" action="{{ route('property.accounting.entries') }}" class="flex gap-2 w-full sm:w-auto">
            <input type="hidden" name="reversal" value="{{ request('reversal') }}">
            <input type="hidden" name="source_key" value="{{ request('source_key') }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search account/ref/description…" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-72" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Search</button>
        </form>
    </x-slot>

    <x-slot name="above">
        <form method="post" action="{{ route('property.accounting.settings.account_map.save') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-4xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Auto-posting account mapping</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">These account names are used automatically when invoices, payments, and maintenance costs post to accounting.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Accounts Receivable</label>
                    <input type="text" name="accounts_receivable" value="{{ old('accounts_receivable', $accountMap['accounts_receivable'] ?? 'Accounts Receivable') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rental Income</label>
                    <input type="text" name="rental_income" value="{{ old('rental_income', $accountMap['rental_income'] ?? 'Rental Income') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Cash / Bank</label>
                    <input type="text" name="cash_bank" value="{{ old('cash_bank', $accountMap['cash_bank'] ?? 'Cash / Bank') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Maintenance Expense</label>
                    <input type="text" name="maintenance_expense" value="{{ old('maintenance_expense', $accountMap['maintenance_expense'] ?? 'Maintenance Expense') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Accounts Payable</label>
                    <input type="text" name="accounts_payable" value="{{ old('accounts_payable', $accountMap['accounts_payable'] ?? 'Accounts Payable') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <button type="submit" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Save mapping</button>
        </form>

        <form method="post" action="{{ route('property.accounting.entries.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-4xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Record entry</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Date</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('entry_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (optional)</label>
                    <x-property.quick-create-select
                        name="property_id"
                        :required="false"
                        placeholder="General"
                        :options="collect($properties)->map(fn($p) => ['value' => $p->id, 'label' => $p->name, 'selected' => (string) old('property_id') === (string) $p->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create property',
                            'endpoint' => route('property.properties.store_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Property name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Prady Court'],
                                ['name' => 'code', 'label' => 'Code (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Auto if blank'],
                                ['name' => 'address_line', 'label' => 'Address (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Street / building'],
                                ['name' => 'city', 'label' => 'City (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Nairobi'],
                            ],
                        ]"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Account</label>
                    <input type="text" name="account_name" value="{{ old('account_name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Rental Income" />
                    @error('account_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                    <select name="category" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach ($categoryOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Entry type</label>
                    <select name="entry_type" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('entry_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reference</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Invoice no / payment ref / voucher" />
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                    <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional narrative">{{ old('description') }}</textarea>
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save entry</button>
        </form>
    </x-slot>
</x-property.workspace>


