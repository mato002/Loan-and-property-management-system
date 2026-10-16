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
        'statement_lines_linked' => 'Existing statement lines matched',
        'matched' => 'Matched',
        'unmatched' => 'Unmatched',
        'source_paid_rows' => 'Rows with source Paid values',
        'source_paid_total' => 'Source Paid total (reference only)',
        'charges_parsed' => 'Statement charges found',
        'payments_ignored_unverified' => 'Legacy receipts not posted',
    ];
@endphp
<x-property.workspace
    title="Register imports"
    subtitle="Import registers, receipts, and balances."
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
                <p class="text-xs text-slate-500">Latest run</p>
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
                <details class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950">
                    <summary class="cursor-pointer text-sm font-semibold">Review {{ count($lastWarnings) }} warning(s)</summary>
                    <ul class="mt-2 max-h-60 overflow-y-auto list-disc space-y-1 pl-5 text-sm">
                        @foreach ($lastWarnings as $warning)
                            <li class="break-words">{{ $warning }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if (count($lastErrors) > 0)
                <details class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-950">
                    <summary class="cursor-pointer text-sm font-semibold">Review {{ count($lastErrors) }} error(s)</summary>
                    <ul class="mt-2 max-h-60 overflow-y-auto list-disc space-y-1 pl-5 text-sm">
                        @foreach ($lastErrors as $error)
                            <li class="break-words">{{ $error }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    <div
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-6"
        x-data="{ open: @js($errors->any()), submitting: false, type: @js($selectedType), catalog: @js($catalog) }"
    >
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Import a register</h2>
            <p class="mt-1 text-sm text-slate-600">Choose a register type and upload its file.</p>
        </div>
        <button type="button" @click="open = true" class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 sm:mt-0">
            <span aria-hidden="true">＋</span> New import
        </button>

        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[8000] flex items-center justify-center bg-slate-950/55 p-3 sm:p-6" @keydown.escape.window="if (!submitting) open = false" @click.self="if (!submitting) open = false" role="dialog" aria-modal="true" aria-labelledby="register-import-title">
            <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="register-import-title" class="text-base font-semibold text-slate-900">New register import</h2>
                        <p class="mt-1 text-xs text-slate-500">Select the matching register type and file.</p>
                    </div>
                    <button type="button" @click="open = false" :disabled="submitting" class="rounded-lg px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close">&times;</button>
                </div>

                <form method="POST" action="{{ route('property.settings.register_imports.store') }}" enctype="multipart/form-data" class="max-h-[calc(100vh-9rem)] space-y-5 overflow-y-auto p-5 sm:p-6" @submit="submitting = true" data-register-import-form>
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-slate-600">Import type
                            <select name="import_type" x-model="type" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-normal text-slate-900">
                                @foreach ($catalog as $key => $meta)
                                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-slate-600">Register file <span class="text-rose-600">*</span>
                            <input type="file" name="register_file" required :accept="catalog[type]?.accept || '.csv,.txt,.pdf,.xls,.xlsx'" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 text-sm text-slate-600 file:mr-3 file:h-10 file:border-0 file:bg-teal-700 file:px-3 file:font-semibold file:text-white">
                        </label>
                    </div>

                    <p class="-mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" x-text="catalog[type]?.description || ''"></p>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block text-xs font-semibold text-slate-600">Property code
                            <input type="text" name="property" value="{{ old('property') }}" placeholder="All properties" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-normal">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600">Row limit
                            <input type="number" name="limit" value="{{ old('limit') }}" min="1" placeholder="All rows" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-normal">
                        </label>
                        @if (auth()->user()?->is_super_admin)
                            <label x-show="type === 'late_penalty_invoices'" x-cloak class="block text-xs font-semibold text-slate-600">Target agent user ID
                                <input type="number" name="target_agent_user_id" value="{{ $targetAgentUserId ?? 2 }}" min="1" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-normal">
                            </label>
                        @endif
                        <label x-show="type === 'payment_vouchers'" x-cloak class="block text-xs font-semibold text-slate-600">Voucher category
                            <select name="category" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-normal">
                                <option value="">All categories</option><option value="remittance">Remittance</option><option value="commission">Commission</option><option value="tax">Tax</option><option value="expense">Expense</option>
                            </select>
                        </label>
                        <label x-show="type === 'vendor_bills'" x-cloak class="block text-xs font-semibold text-slate-600">Bill status
                            <select name="status" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-normal">
                                <option value="">All statuses</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="unpaid">Unpaid</option>
                            </select>
                        </label>
                        <label x-show="type === 'statement_balances'" x-cloak class="block text-xs font-semibold text-slate-600">Landlord take-on file
                            <input type="file" name="secondary_file" accept=".csv,.txt,.xls,.xlsx" class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 text-xs text-slate-600 file:mr-2 file:h-10 file:border-0 file:bg-slate-700 file:px-2 file:text-white">
                        </label>
                    </div>

                    <div class="grid gap-x-5 gap-y-3 border-t border-slate-100 pt-4 text-sm text-slate-700 sm:grid-cols-2">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="dry_run" value="1" class="rounded border-slate-300" checked> Dry run only</label>
                        <label x-show="['rent_receipts','payment_vouchers'].includes(type)" x-cloak class="inline-flex items-center gap-2"><input type="checkbox" name="register_only" value="1" class="rounded border-slate-300"> Register only</label>
                        <label x-show="type === 'rent_receipts'" x-cloak class="inline-flex items-center gap-2"><input type="checkbox" name="include_already_paid" value="1" class="rounded border-slate-300"> Include already-paid tenants</label>
                        <label x-show="['payment_vouchers','rental_invoices','late_penalty_invoices'].includes(type)" x-cloak class="inline-flex items-center gap-2"><input type="checkbox" name="post_gl" value="1" class="rounded border-slate-300"> Post to general ledger</label>
                        <label x-show="type === 'tenant_statement'" x-cloak class="inline-flex items-center gap-2"><input type="checkbox" name="post_gl" value="1" class="rounded border-slate-300"> Post imported charges to general ledger</label>
                        <label x-show="type === 'rental_invoices'" x-cloak class="inline-flex items-center gap-2"><input type="checkbox" name="include_deposits" value="1" class="rounded border-slate-300"> Include deposit invoice rows</label>
                        <label x-show="['deposits','billing_schedule','tenants_leases'].includes(type)" x-cloak class="inline-flex items-center gap-2"><input type="checkbox" name="no_update" value="1" class="rounded border-slate-300"><span x-text="type === 'tenants_leases' ? 'Create only; do not update existing records' : 'Skip rows that already have values'"></span></label>
                        <label x-show="type === 'statement_balances'" x-cloak class="inline-flex items-center gap-2"><input type="checkbox" name="sync_invoices" value="1" class="rounded border-slate-300"> Create carry-forward invoices</label>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" @click="open = false" :disabled="submitting" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" :disabled="submitting" class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:cursor-wait disabled:opacity-70">
                            <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" d="M4 12a8 8 0 018-8v8H4z" fill="currentColor"></path></svg>
                            <span x-text="submitting ? 'Processing register…' : 'Run import'"></span>
                        </button>
                    </div>
                </form>

                <div x-show="submitting" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-white/95 p-6" role="status" aria-live="polite">
                    <div class="w-full max-w-sm text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-teal-50 text-teal-800">
                            <svg class="h-7 w-7 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
                        </div>
                        <p class="mt-4 text-base font-semibold text-slate-900">Processing register and money records</p>
                        <p class="mt-1 text-sm text-slate-600">Checking references, matching tenants, and saving the import. This may take a little while.</p>
                        <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-1/3 animate-pulse rounded-full bg-teal-700"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-property.workspace>
