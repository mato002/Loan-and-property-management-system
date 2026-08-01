<x-property.workspace
    title="Arrears management"
    subtitle="Overdue invoices with open balance — aging from due date."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats ?? $statsPrimary ?? []"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No arrears cases"
    empty-hint="When due date passes and balance remains, rows appear here automatically."
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
                        <option value="all" @selected(old('target_mode', 'all') === 'all')>All arrears</option>
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
            <div class="flex items-end gap-2">
                <button type="button" id="arrears-select-all" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Select all on page</button>
                <button type="button" id="arrears-clear" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear selection</button>
            </div>
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

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.arrears', get_defined_vars())
    </x-slot>
    <x-slot name="footer">
        @isset($paginator)
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} tenant(s) in arrears
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
        <p class="font-medium text-slate-700 dark:text-slate-300">Tip</p>
        <p class="mt-1">Each row rolls up all overdue invoices for that tenant. Click <span class="font-medium">View invoices</span> to see line-level detail and pick specific invoices for targeted reminders.</p>
    </x-slot>
    <script>
        (function () {
            const form = document.getElementById('arrears-reminder-form');
            const mode = document.getElementById('arrears-target-mode');
            const singleWrap = document.getElementById('arrears-single-wrap');
            const selectedInput = document.getElementById('arrears-selected-invoice-ids');
            const btn = document.getElementById('arrears-send-btn');
            const selectAll = document.getElementById('arrears-select-all');
            const clearBtn = document.getElementById('arrears-clear');
            if (!form || !mode || !selectedInput) return;

            const toggleSingle = () => {
                if (!singleWrap) return;
                singleWrap.classList.toggle('hidden', mode.value !== 'single');
                if (btn) {
                    btn.textContent = mode.value === 'all'
                        ? 'Send reminders to all arrears'
                        : (mode.value === 'selected' ? 'Send to selected' : 'Send to single');
                }
            };

            mode.addEventListener('change', toggleSingle);
            toggleSingle();

            form.addEventListener('submit', function () {
                const ids = Array.from(document.querySelectorAll('.arrears-invoice-pick:checked'))
                    .map((el) => (el.value || '').toString().trim())
                    .filter((v) => v !== '');
                selectedInput.value = ids.join(',');
            });

            const getCheckboxes = () => Array.from(document.querySelectorAll('.arrears-invoice-pick'));
            if (selectAll) {
                selectAll.addEventListener('click', function () {
                    getCheckboxes().forEach((el) => { el.checked = true; });
                });
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    getCheckboxes().forEach((el) => { el.checked = false; });
                });
            }
        })();
    </script>
</x-property.workspace>
