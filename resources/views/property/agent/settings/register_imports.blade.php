@php
    $appName = $appName ?? config('app.name', 'Property ERP');
    $catalog = $catalog ?? [];
    $selectedType = $selectedType ?? array_key_first($catalog);
@endphp
<x-property.workspace
    title="Register imports"
    subtitle="Bulk-load receipt listings, vouchers, bills, and opening balances into {{ $appName }}. Works for any agency portfolio — formats from common property systems are supported."
    back-route="property.settings.index"
>
    <x-slot name="actions">
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
                <label class="inline-flex items-center gap-2" x-show="['deposits','billing_schedule'].includes(type)" x-cloak>
                    <input type="checkbox" name="no_update" value="1" class="rounded border-slate-300">
                    Skip rows that already have values
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
