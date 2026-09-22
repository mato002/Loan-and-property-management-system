@php
    $appName = $appName ?? config('app.name', 'Property ERP');
    $catalog = $catalog ?? [];
    $selectedType = $selectedType ?? array_key_first($catalog);
    $lastImportResult = is_array($lastImportResult ?? null)
        ? $lastImportResult
        : (is_array(session('register_import_last_result')) ? session('register_import_last_result') : null);
    $lastSummary = is_array($lastImportResult['summary'] ?? null) ? $lastImportResult['summary'] : [];
    $lastWarnings = is_array($lastImportResult['warnings'] ?? null) ? $lastImportResult['warnings'] : [];
    $lastErrors = is_array($lastImportResult['errors'] ?? null) ? $lastImportResult['errors'] : [];
    $countKeys = [
        'parsed' => 'Parsed',
        'tenants_created' => 'Tenants created',
        'tenants_updated' => 'Tenants updated',
        'leases_created' => 'Leases created',
        'leases_updated' => 'Leases updated',
        'leases_terminated' => 'Leases terminated',
        'units_linked' => 'Units linked',
        'imported' => 'Imported',
        'register_upserted' => 'Register upserted',
        'matched' => 'Matched',
        'unmatched' => 'Unmatched',
    ];
@endphp
<x-property.workspace
    title="Register imports"
    subtitle="Bulk-load tenants &amp; leases, receipt listings, vouchers, bills, and opening balances into {{ $appName }}. Prefer Dry run first so counts match before writing."
    back-route="property.settings.index"
>
    <x-slot name="actions">
        <a href="{{ route('property.tenants.index') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Tenants</a>
        <a href="{{ route('property.revenue.statements.index') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Bank / M-Pesa statements</a>
        <a href="{{ route('property.revenue.receipts') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Receipts</a>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($lastImportResult)
        <div id="last-import-result" class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm scroll-mt-24">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Last import result</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $lastImportResult['label'] ?? 'Import' }}
                        · {{ ! empty($lastImportResult['dry_run']) ? 'Dry run' : 'Written' }}
                        @if (! empty($lastImportResult['ran_at']))
                            · {{ $lastImportResult['ran_at'] }}
                        @endif
                    </p>
                </div>
                <p class="text-xs text-slate-500">Stays on this page after you close the success popup. Replaced when you run another import.</p>
            </div>

            @if (! empty($lastImportResult['message']))
                <p class="mt-3 text-sm text-slate-700">{{ $lastImportResult['message'] }}</p>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($countKeys as $key => $label)
                    @if (isset($lastSummary[$key]) && ! is_array($lastSummary[$key]))
                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                            {{ $label }}: <span class="ml-1 tabular-nums font-semibold">{{ $lastSummary[$key] }}</span>
                        </span>
                    @endif
                @endforeach
                @if (count($lastWarnings) > 0)
                    <span class="inline-flex items-center rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">
                        Warnings: {{ count($lastWarnings) }}
                    </span>
                @endif
                @if (count($lastErrors) > 0)
                    <span class="inline-flex items-center rounded-lg bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-900">
                        Errors: {{ count($lastErrors) }}
                    </span>
                @endif
            </div>

            @if (count($lastWarnings) > 0)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold">Warnings ({{ count($lastWarnings) }})</h3>
                        <a href="#last-import-warnings" class="text-xs font-semibold text-amber-800 hover:underline">Jump to list</a>
                    </div>
                    <ul id="last-import-warnings" class="mt-2 max-h-72 overflow-y-auto list-disc space-y-1 pl-5 text-sm">
                        @foreach ($lastWarnings as $warning)
                            <li class="break-words">{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($lastErrors) > 0)
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-950">
                    <h3 class="text-sm font-semibold">Errors ({{ count($lastErrors) }})</h3>
                    <ul class="mt-2 max-h-72 overflow-y-auto list-disc space-y-1 pl-5 text-sm">
                        @foreach ($lastErrors as $error)
                            <li class="break-words">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <div
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        x-data="{ type: @js($selectedType) }"
    >
        <h2 class="text-sm font-semibold text-slate-900">Upload a register file</h2>
        <p class="mt-1 text-sm text-slate-600">
            Prefer <strong>Dry run</strong> first. Imports are scoped to your {{ $appName }} agent workspace.
        </p>

        <form method="POST" action="{{ route('property.settings.register_imports.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Import type</label>
                <select name="import_type" x-model="type" class="h-10 w-full max-w-xl rounded-lg border border-slate-300 px-3 text-sm">
                    @foreach ($catalog as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                <template x-for="(meta, key) in @js($catalog)" :key="key">
                    <p class="mt-2 text-xs text-slate-500" x-show="type === key" x-text="meta.description" x-cloak></p>
                </template>
                <p class="mt-2 text-xs text-amber-800" x-show="type === 'tenants_leases'" x-cloak>
                    Properties must already exist with matching codes (e.g. A00039A). Run Dry run first — the summary shows tenants/leases created vs updated, plus any unmatched property/unit warnings.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">File</label>
                    <input type="file" name="register_file" required accept=".csv,.txt,.pdf,.xls,.xlsx" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-white">
                </div>
                <div x-show="type === 'statement_balances'" x-cloak>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Optional landlord take-on CSV</label>
                    <input type="file" name="secondary_file" accept=".csv,.txt,.xls,.xlsx" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-white">
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Property code filter (optional)</label>
                    <input type="text" name="property" value="{{ old('property') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="e.g. A00039A">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Row limit (optional)</label>
                    <input type="number" name="limit" value="{{ old('limit') }}" min="1" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" placeholder="All rows">
                </div>
                <div x-show="type === 'payment_vouchers'" x-cloak>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Voucher category</label>
                    <select name="category" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="">All</option>
                        <option value="remittance">Remittance</option>
                        <option value="commission">Commission</option>
                        <option value="tax">Tax</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div x-show="type === 'vendor_bills'" x-cloak>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Bill status filter</label>
                    <select name="status" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="">All</option>
                        <option value="paid">Paid</option>
                        <option value="partial">Partial</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-sm text-slate-700">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300" checked>
                    Dry run (parse only)
                </label>
                <label class="inline-flex items-center gap-2" x-show="['rent_receipts','payment_vouchers'].includes(type)" x-cloak>
                    <input type="checkbox" name="register_only" value="1" class="rounded border-slate-300">
                    Register only (no payments / payouts)
                </label>
                <label class="inline-flex items-center gap-2" x-show="type === 'rent_receipts'" x-cloak>
                    <input type="checkbox" name="include_already_paid" value="1" class="rounded border-slate-300">
                    Include already-paid tenants
                </label>
                <label class="inline-flex items-center gap-2" x-show="['payment_vouchers','rental_invoices'].includes(type)" x-cloak>
                    <input type="checkbox" name="post_gl" value="1" class="rounded border-slate-300">
                    Post GL
                </label>
                <label class="inline-flex items-center gap-2" x-show="type === 'rental_invoices'" x-cloak>
                    <input type="checkbox" name="include_deposits" value="1" class="rounded border-slate-300">
                    Include deposit invoice rows
                </label>
                <label class="inline-flex items-center gap-2" x-show="['deposits','billing_schedule','tenants_leases'].includes(type)" x-cloak>
                    <input type="checkbox" name="no_update" value="1" class="rounded border-slate-300">
                    <span x-text="type === 'tenants_leases' ? 'Do not update existing tenants / leases (create only)' : 'Skip rows that already have values'"></span>
                </label>
                <label class="inline-flex items-center gap-2" x-show="type === 'statement_balances'" x-cloak>
                    <input type="checkbox" name="sync_invoices" value="1" class="rounded border-slate-300">
                    Create carry-forward invoices
                </label>
            </div>

            <button type="submit" class="inline-flex rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
                Run import
            </button>
        </form>
    </div>

    <div class="mt-6 grid gap-3 md:grid-cols-2">
        @foreach ($catalog as $key => $meta)
            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                <p class="text-sm font-semibold text-slate-900">{{ $meta['label'] }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ $meta['description'] }}</p>
            </div>
        @endforeach
    </div>
</x-property.workspace>
