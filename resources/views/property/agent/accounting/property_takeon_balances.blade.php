@php
    $showTakeonModal = old('takeon_form') === 'takeon' || request()->query('open') === 'takeon';
    $showImportModal = old('takeon_form') === 'import';
@endphp
<x-property.workspace
    title="Property take-on balances"
    subtitle="Opening landlord ledger positions when properties were taken on — feeds Balance b/f in settlements and payment &amp; fees."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="[]"
    :table-rows="[]"
    :show-search="false"
    :compact-list="true"
>
    <x-slot name="pageModalsAttributes"
        x-data="{!! \Illuminate\Support\Js::from([
            'showTakeonModal' => $showTakeonModal,
            'showImportModal' => $showImportModal,
        ]) !!}"
    ></x-slot>

    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
            data-property-modal-open="showTakeonModal"
            @click="showTakeonModal = true"
        >
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>Add take-on</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-900 hover:bg-indigo-100"
            data-property-modal-open="showImportModal"
            @click="showImportModal = true"
        >
            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
            <span>Import take-on</span>
        </button>
        <a href="{{ route('property.accounting.payables.landlord_settlements') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Verify in settlements</a>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showTakeonModal"
            close="showTakeonModal = false"
            name="property-takeon-balance"
            title="Add property take-on balance"
            max-width="2xl"
        >
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Positive balance = amount owed to landlord. Negative = overdrawn / landlord owes agent. Posts to landlord ledger with the balance date.</p>
            <form method="post" action="{{ route('property.accounting.payables.property_takeon_balances.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="takeon_form" value="takeon" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                        <select name="property_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                            <option value="">Select property</option>
                            @foreach ($properties as $property)
                                <option value="{{ $property->id }}" @selected((int) old('property_id', $filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->code ? '['.$property->code.'] ' : '' }}{{ $property->name }}</option>
                            @endforeach
                        </select>
                        @error('property_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord</label>
                        <select name="landlord_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                            <option value="">Select landlord</option>
                            @foreach ($landlords as $landlord)
                                <option value="{{ $landlord->id }}" @selected((int) old('landlord_id', $filters['landlord_id'] ?? 0) === (int) $landlord->id)>{{ $landlord->name }}</option>
                            @endforeach
                        </select>
                        @error('landlord_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Balance (KES)</label>
                        <input type="number" name="balance" step="0.01" value="{{ old('balance') }}" required placeholder="e.g. -25020 or 5980" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
                        @error('balance')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Balance date</label>
                        <input type="date" name="balance_date" value="{{ old('balance_date', '2022-06-01') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
                        @error('balance_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Optional — e.g. Ezen take-on import" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50" @click="showTakeonModal = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Save &amp; post to ledger</button>
                </div>
            </form>
        </x-property.modal>

        <x-property.modal
            show="showImportModal"
            close="showImportModal = false"
            name="property-takeon-import"
            title="Import take-on balances"
            max-width="lg"
        >
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Upload a CSV with columns: <code class="text-xs">property_code</code>, <code class="text-xs">balance_date</code>, <code class="text-xs">balance</code>. Optional: <code class="text-xs">landlord_id</code>, <code class="text-xs">notes</code>.</p>
            <pre class="mb-4 overflow-x-auto rounded-lg bg-slate-100 dark:bg-slate-800 p-3 text-xs text-slate-700 dark:text-slate-300">property_code,balance_date,balance
M00029A,2022-06-01,-25020
M00004A,2022-05-31,5980
M00015A,2022-05-31,-9500</pre>
            <form method="post" action="{{ route('property.accounting.payables.property_takeon_balances.import') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="hidden" name="takeon_form" value="import" />
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">CSV file</label>
                    <input type="file" name="import_file" accept=".csv,.txt" required class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-800 hover:file:bg-indigo-100" />
                    @error('import_file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50" @click="showImportModal = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Import</button>
                </div>
            </form>
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.property_takeon_balances') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[11rem]">
                    <option value="">All properties</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((int) ($filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord</label>
                <select name="landlord_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[11rem]">
                    <option value="">All landlords</option>
                    @foreach ($landlords as $landlord)
                        <option value="{{ $landlord->id }}" @selected((int) ($filters['landlord_id'] ?? 0) === (int) $landlord->id)>{{ $landlord->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Property or landlord…" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm w-44" />
            </div>
            <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800 mt-5">Search</button>
            <a href="{{ route('property.accounting.payables.property_takeon_balances') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 mt-5">Reset</a>
        </form>
    </x-slot>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @if ($errors->any() && ! in_array(old('takeon_form'), ['takeon', 'import'], true))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/80 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" class="rounded border-slate-300" disabled aria-hidden="true" />
                        </th>
                        <th class="px-4 py-3">Property</th>
                        <th class="px-4 py-3">Landlord</th>
                        <th class="px-4 py-3">Balance date</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                        <th class="px-4 py-3">Notes</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr @class([
                            'border-t border-slate-100 dark:border-slate-800',
                            'bg-rose-50/60 dark:bg-rose-950/20' => ($row['balance_tone'] ?? '') === 'negative',
                            'bg-amber-50/40 dark:bg-amber-950/10' => ($row['balance_tone'] ?? '') === 'positive',
                        ])>
                            <td class="px-4 py-3">
                                <input type="checkbox" class="rounded border-slate-300" value="{{ $row['id'] }}" aria-label="Select row" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900 dark:text-white">{{ $row['display_property'] ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $row['landlord_name'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['balance_date_label'] ?? '—' }}</td>
                            <td @class([
                                'px-4 py-3 text-right tabular-nums font-semibold',
                                'text-rose-700 dark:text-rose-300' => ($row['balance_tone'] ?? '') === 'negative',
                                'text-emerald-800 dark:text-emerald-200' => ($row['balance_tone'] ?? '') === 'positive',
                            ])>{{ $row['balance_label'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ ($row['notes'] ?? '') !== '' ? $row['notes'] : '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id']]) }}" class="text-xs font-medium text-indigo-700 hover:text-indigo-800">Settlement</a>
                                    <form method="post" action="{{ route('property.accounting.payables.property_takeon_balances.destroy', $row['id']) }}" class="inline" onsubmit="return confirm('Remove this take-on balance and reverse the ledger entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-rose-700 hover:text-rose-800">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No property take-on balances recorded yet. Add manually or import from Ezen export.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-property.workspace>
