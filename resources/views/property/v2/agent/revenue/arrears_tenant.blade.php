@php
    $tenantPhoneRaw = (string) ($tenant->phone ?? '');
    $tenantPhoneDigits = preg_replace('/\D+/', '', $tenantPhoneRaw) ?: '';
    if ($tenantPhoneDigits !== '') {
        if (str_starts_with($tenantPhoneDigits, '0') && strlen($tenantPhoneDigits) >= 10) {
            $tenantPhoneE164 = '+254' . substr($tenantPhoneDigits, 1);
        } elseif (str_starts_with($tenantPhoneDigits, '254')) {
            $tenantPhoneE164 = '+' . $tenantPhoneDigits;
        } else {
            $tenantPhoneE164 = '+' . $tenantPhoneDigits;
        }
    } else {
        $tenantPhoneE164 = '';
    }
@endphp

<x-property.workspace
    title="Arrears · {{ $tenant->name }}"
    subtitle="All unpaid invoices for this tenant. Send targeted reminders or escalate."
    back-route="property.revenue.arrears"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-footer-row="$tableFooterRow ?? null"
    empty-title="No outstanding invoices"
    empty-hint="This tenant has no unpaid invoices right now."
>
    <x-slot name="actions">
        <div class="flex flex-wrap items-end gap-2">
            <form id="arrears-reminder-form" method="post" action="{{ route('property.revenue.arrears.reminders', absolute: false) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600">Template</label>
                    <select name="template_key" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="friendly">Friendly reminder</option>
                        <option value="firm">Firm follow-up</option>
                        <option value="final">Final notice</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Channel</label>
                    <select name="channel" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="sms">SMS only</option>
                        <option value="email">Email only</option>
                        <option value="both" selected>SMS + Email</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Send to</label>
                    <select id="arrears-target-mode" name="target_mode" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="all" @selected(old('target_mode', 'selected') === 'all')>All unpaid (this tenant)</option>
                        <option value="selected" @selected(old('target_mode', 'selected') === 'selected')>Selected rows</option>
                        <option value="single" @selected(old('target_mode') === 'single')>Single invoice</option>
                    </select>
                </div>
                <div id="arrears-single-wrap" class="@if(old('target_mode') !== 'single') hidden @endif">
                    <label class="block text-xs font-medium text-slate-600">Invoice</label>
                    <select name="single_invoice_id" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[20rem]">
                        <option value="">Select one...</option>
                        @foreach (($reminderTargets ?? []) as $target)
                            <option value="{{ $target['id'] }}" @selected((int) old('single_invoice_id') === (int) $target['id'])>{{ $target['label'] }}</option>
                        @endforeach
                    </select>
                    @error('single_invoice_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <input type="hidden" name="selected_invoice_ids_raw" id="arrears-selected-invoice-ids" value="">
                <input type="hidden" id="arrears-tenant-invoice-ids" value="{{ implode(',', collect($reminderTargets ?? [])->pluck('id')->all()) }}">
                <button id="arrears-send-btn" type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Send reminders</button>
            </form>
        </div>
        @error('selected_invoice_ids')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </x-slot>

    <x-slot name="table_actions">
        @if (!empty($tableRows))
            <x-property.bulk-action-bar
                mode="selection"
                row-checkbox-selector=".arrears-invoice-pick"
                sync-form="arrears-reminder-form"
                sync-input="arrears-selected-invoice-ids"
            />
        @endif
    </x-slot>

    <x-slot name="toolbar">
        <div class="flex flex-wrap items-stretch gap-3 w-full">
            <div class="flex-1 min-w-[260px] rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-slate-500">Tenant</p>
                <p class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ $tenant->name }}</p>
                <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-400">
                    @if ($tenantPhoneE164 !== '')
                        <a href="tel:{{ $tenantPhoneE164 }}" class="text-indigo-600 hover:text-indigo-700">📞 {{ $tenant->phone }}</a>
                    @else
                        <span>📞 —</span>
                    @endif
                    @if (! empty($tenant->email))
                        <a href="mailto:{{ $tenant->email }}" class="text-indigo-600 hover:text-indigo-700">✉ {{ $tenant->email }}</a>
                    @endif
                    @if (! empty($tenant->account_number))
                        <span>Acct: {{ $tenant->account_number }}</span>
                    @endif
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total balance</p>
                <p class="text-base font-semibold text-rose-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($summary['total_balance'] ?? 0)) }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $summary['invoice_count'] ?? 0 }} unpaid invoice(s)</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-slate-500">Oldest due</p>
                <p class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ $summary['oldest_due'] ?? '—' }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $summary['aging_label'] ?? ($summary['days_late'] ?? 0) }} · {{ $summary['workflow'] ?? 'Reminder' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-gray-800">
                <p class="text-xs uppercase tracking-wide text-slate-500">Last contact</p>
                <p class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ $summary['last_contact'] ?? '—' }}</p>
                <a href="{{ route('property.tenants.notices', ['tenant_id' => $tenant->id, 'view' => 1], absolute: false) }}" class="mt-1 inline-block text-xs text-indigo-600 hover:text-indigo-700">Open notices →</a>
            </div>
        </div>
        <form method="get" action="{{ route('property.revenue.arrears.tenant', ['tenant' => $tenant->id], absolute: false) }}" class="flex flex-wrap items-end gap-2 w-full mt-3">
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="due_date" @selected(($filters['sort'] ?? 'due_date') === 'due_date')>Sort: Due date</option>
                <option value="balance" @selected(($filters['sort'] ?? '') === 'balance')>Sort: Amount</option>
                <option value="invoice_no" @selected(($filters['sort'] ?? '') === 'invoice_no')>Sort: Invoice</option>
                <option value="updated_at" @selected(($filters['sort'] ?? '') === 'updated_at')>Sort: Last update</option>
                <option value="id" @selected(($filters['sort'] ?? '') === 'id')>Sort: ID</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="asc" @selected(($filters['dir'] ?? 'asc') === 'asc')>Asc</option>
                <option value="desc" @selected(($filters['dir'] ?? '') === 'desc')>Desc</option>
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.revenue.arrears', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">← Back to all arrears</a>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.revenue.arrears.tenant', array_merge(request()->query(), ['tenant' => $tenant->id, 'export' => 'csv']), false),
                'xlsUrl' => route('property.revenue.arrears.tenant', array_merge(request()->query(), ['tenant' => $tenant->id, 'export' => 'xls']), false),
                'pdfUrl' => route('property.revenue.arrears.tenant', array_merge(request()->query(), ['tenant' => $tenant->id, 'export' => 'pdf']), false),
            ])
        </form>
    </x-slot>

    <x-slot name="footer">
        <p class="font-medium text-slate-700 dark:text-slate-300">Quick tip</p>
        <p class="mt-1">Pick the invoices you want to remind about and choose <span class="font-medium">Selected rows</span> in the dropdown above. Use <span class="font-medium">Single invoice</span> for one-off chases.</p>
    </x-slot>

    <script>
        (function () {
            const form = document.getElementById('arrears-reminder-form');
            const mode = document.getElementById('arrears-target-mode');
            const singleWrap = document.getElementById('arrears-single-wrap');
            const selectedInput = document.getElementById('arrears-selected-invoice-ids');
            const btn = document.getElementById('arrears-send-btn');
            if (!form || !mode || !selectedInput) return;

            const tenantIdsField = document.getElementById('arrears-tenant-invoice-ids');
            const tenantIds = (tenantIdsField ? (tenantIdsField.value || '') : '').split(',').filter((v) => v !== '');

            const toggleSingle = () => {
                if (!singleWrap) return;
                singleWrap.classList.toggle('hidden', mode.value !== 'single');
                if (btn) {
                    btn.textContent = mode.value === 'all'
                        ? "Send to all of this tenant's unpaid"
                        : (mode.value === 'selected' ? 'Send to selected' : 'Send to single');
                }
            };
            mode.addEventListener('change', toggleSingle);
            toggleSingle();

            form.addEventListener('submit', function () {
                if (mode.value === 'all') {
                    // "All unpaid" on the detail page means all open invoices for THIS tenant,
                    // not the whole workspace. Submit as `selected` with the tenant's invoice IDs.
                    selectedInput.value = tenantIds.join(',');
                    mode.value = 'selected';
                    return;
                }
            });
        })();
    </script>
</x-property.workspace>
