@php $appName = config('app.name', 'Property ERP'); @endphp
<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Payment vouchers"
    subtitle="Outgoing payments — record a voucher here, or import an EZEN listing. Remittances become landlord payouts; other rows become expenses."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    table-min-width="1480px"
    empty-title="No payment vouchers yet"
    empty-hint="Record a voucher, or upload an EZEN listing under Settings → Register imports."
>
    <x-slot name="secondary">
        <div class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Outgoing payments register</p>
            <p class="mt-1 text-sm text-slate-600">These are not tenant receipts. Rent remittance rows become paid landlord payouts; airtime, utilities, commission, and tax rows become expense entries. Re-run the import after adding missing landlords to post unmatched remittances.</p>
            <a href="{{ route('property.settings.register_imports') }}" class="mt-3 inline-flex text-sm font-semibold text-teal-800 hover:underline">Open register imports →</a>
        </div>
        <form method="post" action="{{ route('property.accounting.payables.payment_vouchers.assign_export', absolute: false) }}" enctype="multipart/form-data" data-turbo="false" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm max-w-3xl space-y-3">
            @csrf
            <p class="text-sm font-semibold text-slate-900">Property export</p>
            <p class="text-sm text-slate-600">Upload an EZEN voucher listing that was filtered to one property. Every voucher in the file is linked to that property. A line that names one unit, such as LUGAS M12, is linked to that unit.</p>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Property</label>
                    <select name="property_id" required class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[16rem]">
                        <option value="">Select property…</option>
                        @foreach ($properties ?? [] as $property)
                            <option value="{{ $property->id }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Export file</label>
                    <input type="file" name="register_file" required accept=".xls,.xlsx,.csv,.txt" class="mt-1 block text-sm text-slate-600">
                </div>
                <button type="submit" class="rounded-lg bg-teal-800 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-900">Link vouchers</button>
            </div>
        </form>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('property.accounting.payables.payment_vouchers.create', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Record voucher</a>
        @include('property.agent.partials.export_dropdown', [
            'route' => 'property.accounting.payables.payment_vouchers',
            'query' => request()->except(['export', 'format', 'page']),
        ])
        <a href="{{ route('property.accounting.payables.landlord_payouts') }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Landlord payouts</a>
        <a href="{{ route('property.accounting.entries') }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Journal entries</a>
    </x-slot>

    <x-slot name="toolbar">
        @if (session('status'))
            <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        <form method="get" action="{{ route('property.accounting.payables.payment_vouchers') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[12rem]">
                    <option value="">All properties</option>
                    <option value="unassigned" @selected(($filters['property_id'] ?? '') === 'unassigned')>Unassigned</option>
                    @foreach ($properties ?? [] as $property)
                        <option value="{{ $property->id }}" @selected((string) ($filters['property_id'] ?? '') === (string) $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                <select name="property_unit_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[10rem]" @disabled(! ctype_digit((string) ($filters['property_id'] ?? '')))>
                    <option value="">{{ ($filters['property_id'] ?? '') !== '' && ($filters['property_id'] ?? '') !== 'unassigned' ? 'All units' : 'Pick a property first' }}</option>
                    @foreach ($filterUnits ?? [] as $unit)
                        <option value="{{ $unit->id }}" @selected((string) ($filters['property_unit_id'] ?? '') === (string) $unit->id)>{{ $unit->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Voucher #, ref, payee…" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[14rem]">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                <select name="category" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[10rem]">
                    <option value="">All categories</option>
                    @foreach (['remittance' => 'Rent remittance', 'commission' => 'Commission', 'tax' => 'Tax / statutory', 'expense' => 'Expense'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[10rem]">
                    <option value="">All statuses</option>
                    @foreach (['remittance_posted' => 'Posted as payout', 'expense_posted' => 'Posted as expense', 'unmatched' => 'Unmatched payee', 'imported' => 'Imported only'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 mt-5">Apply</button>
            <a href="{{ route('property.accounting.payables.payment_vouchers') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 mt-5">Reset</a>
        </form>
    </x-slot>

    <x-slot name="footer">
        @isset($paginator)
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        @endisset
    </x-slot>
</x-property.workspace>
