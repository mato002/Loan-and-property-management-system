<x-property.workspace
    title="Arrears"
    subtitle="Every invoice with an open balance — overdue and not yet due — for follow-up and reminders."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="[]"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-footer-row="$tableFooterRow ?? null"
    empty-title="No outstanding invoices"
    empty-hint="When an invoice has an open balance, it appears here for follow-up."
>
    <x-slot name="above">
        <form
            method="get"
            action="{{ route('property.revenue.arrears', absolute: false) }}"
            data-turbo-frame="property-main"
            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
        >
            <div>
                <p class="text-sm font-semibold text-slate-900">Due date range</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Summary cards use unpaid invoices with due dates in:
                    <span class="font-medium text-slate-700">{{ $dueRangeLabel ?? 'all dates' }}</span>.
                    Choose <span class="font-medium">All open</span> to include every unpaid invoice regardless of due date.
                </p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Quick range</label>
                    <select name="range_months" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                        <option value="0" @selected((int) ($filters['range_months'] ?? 0) === 0)>All open (no limit)</option>
                        @foreach ([1 => '1 month', 2 => '2 months', 3 => '3 months', 6 => '6 months', 12 => '12 months'] as $n => $label)
                            <option value="{{ $n }}" @selected((int) ($filters['range_months'] ?? 0) === $n)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Ending month</label>
                    <input type="month" name="range_end" value="{{ $filters['range_end'] ?? now()->format('Y-m') }}" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                </div>
                <span class="hidden pb-2 text-xs text-slate-400 sm:inline">or exact due dates</span>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Due from</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Due to</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                </div>
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Apply dates</button>
            </div>
            <p class="text-[11px] text-slate-500">
                Leave <span class="font-medium">Due from / Due to</span> empty when using a quick range. Changing the quick range clears custom dates.
            </p>
        </form>
        <script>
            (function () {
                const form = document.querySelector('form[action*="revenue/arrears"]');
                if (!form) return;
                const rangeMonths = form.querySelector('[name="range_months"]');
                const rangeEnd = form.querySelector('[name="range_end"]');
                const from = form.querySelector('[name="from"]');
                const to = form.querySelector('[name="to"]');
                const clearCustom = () => {
                    if (from) from.value = '';
                    if (to) to.value = '';
                };
                rangeMonths?.addEventListener('change', clearCustom);
                rangeEnd?.addEventListener('change', clearCustom);
            })();
        </script>

        @if (count($statsPrimary ?? []) > 0)
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Portfolio summary</p>
                <x-property.responsive.stat-card-grid :stats="collect($statsPrimary)->map(fn ($s) => array_merge($s, ['tone' => ! empty($s['emphasis']) ? 'rose' : 'emerald']))->all()" />
            </div>
        @endif

        @if (count($statsAging ?? []) > 0)
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Aging breakdown (overdue amounts)</p>
                <x-property.responsive.stat-card-grid :stats="$statsAging" />
            </div>
        @endif

        @if (count($statsTable ?? []) > 0)
            <x-property.responsive.stat-card-grid :stats="$statsTable" />
        @endif
    </x-slot>

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
                        <option value="all" @selected(old('target_mode', 'all') === 'all')>All unpaid</option>
                        <option value="selected" @selected(old('target_mode') === 'selected')>Selected rows</option>
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
                <button id="arrears-send-btn" type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Send reminders</button>
            </form>
            <form method="post" action="{{ route('property.revenue.arrears.reminders.test_email', absolute: false) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                    Send test email to me
                </button>
        </form>
        </div>
        @error('arrears_test_email')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
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
        @include('property.agent.partials.filter_toolbars.arrears', ['filters' => $filters])
    </x-slot>
    <x-slot name="footer">
        @if ((int) ($filters['range_months'] ?? 0) > 0 || ! empty($filters['from'] ?? '') || ! empty($filters['to'] ?? ''))
            <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                <span class="font-medium">Due date filter:</span> {{ $dueRangeLabel ?? '' }}.
                Unpaid invoices outside this due-date window are hidden from the summary and list.
                Use <span class="font-medium">All open</span> in the date bar to see the full portfolio.
            </p>
        @endif
        @isset($paginator)
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} tenant(s) with unpaid invoices
                    @if ($paginator->total() > $paginator->count())
                        <span class="text-slate-500">— table totals include all {{ $paginator->total() }} filtered tenants</span>
                    @endif
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
        <p class="font-medium text-slate-700 dark:text-slate-300">Tip</p>
        <p class="mt-1">Each row rolls up all unpaid invoices for that tenant. Click <span class="font-medium">View invoices</span> to see line-level detail and pick specific invoices for targeted reminders.</p>
    </x-slot>
    <script>
        (function () {
            const form = document.getElementById('arrears-reminder-form');
            const mode = document.getElementById('arrears-target-mode');
            const singleWrap = document.getElementById('arrears-single-wrap');
            const selectedInput = document.getElementById('arrears-selected-invoice-ids');
            const btn = document.getElementById('arrears-send-btn');
            if (!form || !mode || !selectedInput) return;

            const toggleSingle = () => {
                if (!singleWrap) return;
                singleWrap.classList.toggle('hidden', mode.value !== 'single');
                if (btn) {
                    btn.textContent = mode.value === 'all'
                        ? 'Send reminders to all unpaid'
                        : (mode.value === 'selected' ? 'Send to selected' : 'Send to single');
                }
            };

            mode.addEventListener('change', toggleSingle);
            toggleSingle();

        })();
    </script>
</x-property.workspace>
