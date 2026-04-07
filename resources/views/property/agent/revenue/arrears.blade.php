<x-property.workspace
    title="Arrears management"
    subtitle="Overdue invoices with open balance — aging from due date."
    back-route="property.revenue.index"
    :stats="$stats"
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
        <form method="get" action="{{ route('property.revenue.arrears', absolute: false) }}" class="flex flex-wrap items-end gap-2 w-full">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Search tenant, unit, invoice..." class="min-w-0 w-full sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="workflow" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Workflow: All</option>
                <option value="reminder" @selected(($filters['workflow'] ?? '') === 'reminder')>Reminder</option>
                <option value="follow-up" @selected(($filters['workflow'] ?? '') === 'follow-up')>Follow-up</option>
                <option value="escalated" @selected(($filters['workflow'] ?? '') === 'escalated')>Escalated</option>
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="due_date" @selected(($filters['sort'] ?? 'due_date') === 'due_date')>Sort: Due date</option>
                <option value="balance" @selected(($filters['sort'] ?? '') === 'balance')>Sort: Balance</option>
                <option value="updated_at" @selected(($filters['sort'] ?? '') === 'updated_at')>Sort: Last contact</option>
                <option value="invoice_no" @selected(($filters['sort'] ?? '') === 'invoice_no')>Sort: Invoice</option>
                <option value="id" @selected(($filters['sort'] ?? '') === 'id')>Sort: ID</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="asc" @selected(($filters['dir'] ?? 'asc') === 'asc')>Asc</option>
                <option value="desc" @selected(($filters['dir'] ?? '') === 'desc')>Desc</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.revenue.arrears', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.revenue.arrears', array_merge(request()->query(), ['export' => 'csv']), false),
                'xlsUrl' => route('property.revenue.arrears', array_merge(request()->query(), ['export' => 'xls']), false),
                'pdfUrl' => route('property.revenue.arrears', array_merge(request()->query(), ['export' => 'pdf']), false),
            ])
        </form>
    </x-slot>
    <x-slot name="footer">
        @isset($paginator)
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} arrears line(s)
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
        <p class="font-medium text-slate-700 dark:text-slate-300">Workflow ideas</p>
        <p class="mt-1">Map states: Current → Reminder → Call → Plan → Notice → Legal. Each transition should log user, channel, and template used.</p>
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
