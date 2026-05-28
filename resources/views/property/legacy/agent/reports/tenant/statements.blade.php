<x-property.workspace
    title="Tenant Statements"
    subtitle="Tenant Reports"
    back-route="property.reports.tenant"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No tenant statement records"
    empty-hint="Tenant statement data will appear once invoices and payments are recorded."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.reports.tenant.statements', array_merge(request()->query(), ['export' => 'csv']), false) }}"
            data-turbo="false"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >Export CSV</a>
        <a
            href="{{ route('property.reports.tenant.statements', array_merge(request()->query(), ['export' => 'xls']), false) }}"
            data-turbo="false"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >Export XLS</a>
        <a
            href="{{ route('property.reports.tenant.statements', array_merge(request()->query(), ['export' => 'pdf']), false) }}"
            data-turbo="false"
            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >Export PDF</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" class="flex flex-wrap items-end gap-2 w-full">
            <div>
                <label class="block text-xs font-medium text-slate-600">Tenant</label>
                <select
                    name="tenant_id"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[220px]"
                >
                    <option value="">All tenants</option>
                    @foreach ($tenantOptions as $tenantOption)
                        <option value="{{ $tenantOption->id }}" @selected((string) $tenantOption->id === (string) ($filters['tenant_id'] ?? ''))>
                            {{ $tenantOption->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Search</label>
                <input
                    type="search"
                    name="q"
                    value="{{ $filters['q'] ?? request('q') }}"
                    placeholder="Name, phone, email, account..."
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[240px]"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">From</label>
                <input
                    type="date"
                    name="from"
                    value="{{ $filters['from'] ?? request('from') }}"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">To</label>
                <input
                    type="date"
                    name="to"
                    value="{{ $filters['to'] ?? request('to') }}"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                />
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>
    </x-slot>

    @php
        $columnTotals = [];
        $summableColumns = ['invoices', 'payments', 'invoiced', 'collected', 'pending', 'outstanding'];
        if (is_array($columns ?? null) && is_array($tableRows ?? null) && count($tableRows) > 0) {
            foreach ($columns as $colIndex => $colLabel) {
                $normalized = mb_strtolower(trim((string) $colLabel));
                if (! in_array($normalized, $summableColumns, true)) {
                    continue;
                }
                $sum = 0.0;
                $isNumericColumn = false;
                foreach ($tableRows as $row) {
                    $cell = $row[$colIndex] ?? null;
                    if ($cell instanceof \Illuminate\Support\HtmlString) {
                        $cell = (string) $cell;
                    }
                    if (is_string($cell) || is_numeric($cell)) {
                        $val = is_numeric($cell) ? (float) $cell : (float) preg_replace('/[^\d.\-]/', '', (string) $cell);
                        if (is_finite($val) && $val !== 0.0) {
                            $isNumericColumn = true;
                            $sum += $val;
                        }
                    }
                }
                if ($isNumericColumn) {
                    $columnTotals[$colIndex] = $sum;
                }
            }
        }
    @endphp

    @if (!empty($columnTotals))
        <x-slot name="footer">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($columnTotals as $idx => $total)
                    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $columns[$idx] }}</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ number_format($total, 2) }}</div>
                    </div>
                @endforeach
            </div>
        </x-slot>
    @endif

    <div
        id="tenant-statement-modal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/60 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tenant-statement-modal-title"
    >
        <div class="w-full max-w-6xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 id="tenant-statement-modal-title" class="text-sm font-semibold text-slate-900">Tenant Statement</h3>
                <button
                    type="button"
                    data-statement-close="1"
                    class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                >Close</button>
            </div>
            <div class="h-[78vh]">
                <iframe id="tenant-statement-frame" src="about:blank" class="h-full w-full rounded-b-2xl"></iframe>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('tenant-statement-modal');
            const frame = document.getElementById('tenant-statement-frame');
            const title = document.getElementById('tenant-statement-modal-title');
            if (!modal || !frame || !title) return;

            const openButtons = document.querySelectorAll('[data-statement-open="1"]');
            const closeButtons = modal.querySelectorAll('[data-statement-close="1"]');

            const closeModal = function () {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                frame.setAttribute('src', 'about:blank');
            };

            openButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const url = btn.getAttribute('data-url') || 'about:blank';
                    const tenant = btn.getAttribute('data-tenant') || 'Tenant';
                    title.textContent = 'Tenant Statement - ' + tenant;
                    frame.setAttribute('src', url);
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            closeButtons.forEach(function (btn) {
                btn.addEventListener('click', closeModal);
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        })();
    </script>
</x-property.workspace>
