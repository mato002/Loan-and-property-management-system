<x-property.workspace
    title="Landlord Statements"
    subtitle="Landlord Reports"
    back-route="property.reports.landlord"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No landlord statement records"
    empty-hint="Landlord statement data will appear once rent is collected."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.reports.landlord.statements', array_merge(request()->query(), ['export' => 'csv']), false) }}"
            data-turbo="false"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >Export CSV</a>
        <a
            href="{{ route('property.reports.landlord.statements', array_merge(request()->query(), ['export' => 'xls']), false) }}"
            data-turbo="false"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >Export XLS</a>
        <a
            href="{{ route('property.reports.landlord.statements', array_merge(request()->query(), ['export' => 'pdf']), false) }}"
            data-turbo="false"
            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >Export PDF</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" class="flex flex-wrap items-end gap-2 w-full">
            <div>
                <label class="block text-xs font-medium text-slate-600">Landlord</label>
                <select
                    name="landlord_id"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[220px]"
                >
                    <option value="">All landlords</option>
                    @foreach ($landlordOptions as $option)
                        <option value="{{ $option->id }}" @selected((string) $option->id === (string) ($filters['landlord_id'] ?? ''))>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Property</label>
                <input
                    type="search"
                    name="property"
                    value="{{ $filters['property'] ?? request('property') }}"
                    placeholder="Filter by property name…"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm min-w-[220px]"
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

    <div
        id="landlord-statement-modal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/60 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="landlord-statement-modal-title"
    >
        <div class="w-full max-w-6xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 id="landlord-statement-modal-title" class="text-sm font-semibold text-slate-900">Landlord Statement</h3>
                <button
                    type="button"
                    data-landlord-statement-close="1"
                    class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                >Close</button>
            </div>
            <div class="h-[78vh]">
                <iframe id="landlord-statement-frame" src="about:blank" class="h-full w-full rounded-b-2xl"></iframe>
            </div>
        </div>
    </div>

    <div
        id="landlord-expense-modal"
        class="fixed inset-0 z-[75] hidden items-center justify-center bg-slate-900/60 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="landlord-expense-modal-title"
    >
        <div class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 id="landlord-expense-modal-title" class="text-sm font-semibold text-slate-900">Expense Breakdown</h3>
                <div class="flex items-center gap-2">
                    <a
                        href="#"
                        data-expense-export-csv="1"
                        data-turbo="false"
                        class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    >Export CSV</a>
                    <a
                        href="#"
                        data-expense-export-pdf="1"
                        data-turbo="false"
                        class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    >Export PDF</a>
                    <button
                        type="button"
                        data-landlord-expense-close="1"
                        class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    >Close</button>
                </div>
            </div>
            <div class="max-h-[70vh] overflow-auto p-4">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2">Property</th>
                            <th class="px-3 py-2">Ownership %</th>
                            <th class="px-3 py-2">Utilities</th>
                            <th class="px-3 py-2">Maintenance</th>
                            <th class="px-3 py-2">Other</th>
                            <th class="px-3 py-2">Property expenses</th>
                            <th class="px-3 py-2">Owner share expense</th>
                        </tr>
                    </thead>
                    <tbody id="landlord-expense-modal-body">
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-slate-500">No expense rows.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-300 bg-slate-50/70 font-semibold text-slate-800">
                            <td class="px-3 py-2">Totals</td>
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">—</td>
                            <td class="px-3 py-2">—</td>
                            <td id="landlord-expense-total-property" class="px-3 py-2">KES 0.00</td>
                            <td id="landlord-expense-total-owner" class="px-3 py-2">KES 0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('landlord-statement-modal');
            const frame = document.getElementById('landlord-statement-frame');
            const title = document.getElementById('landlord-statement-modal-title');
            if (!modal || !frame || !title) return;

            const openButtons = document.querySelectorAll('[data-landlord-statement-open="1"]');
            const closeButtons = modal.querySelectorAll('[data-landlord-statement-close="1"]');

            const closeModal = function () {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                frame.setAttribute('src', 'about:blank');
            };

            openButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const url = btn.getAttribute('data-url') || 'about:blank';
                    const landlord = btn.getAttribute('data-landlord') || 'Landlord';
                    title.textContent = 'Landlord Statement - ' + landlord;
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

            const expenseModal = document.getElementById('landlord-expense-modal');
            const expenseTitle = document.getElementById('landlord-expense-modal-title');
            const expenseBody = document.getElementById('landlord-expense-modal-body');
            const expenseTotalProperty = document.getElementById('landlord-expense-total-property');
            const expenseTotalOwner = document.getElementById('landlord-expense-total-owner');
            const expenseExportCsv = expenseModal.querySelector('[data-expense-export-csv="1"]');
            const expenseExportPdf = expenseModal.querySelector('[data-expense-export-pdf="1"]');
            if (!expenseModal || !expenseTitle || !expenseBody || !expenseTotalProperty || !expenseTotalOwner) return;

            const expenseButtons = document.querySelectorAll('[data-expense-open="1"]');
            const expenseCloseButtons = expenseModal.querySelectorAll('[data-landlord-expense-close="1"]');

            const closeExpenseModal = function () {
                expenseModal.classList.add('hidden');
                expenseModal.classList.remove('flex');
                expenseBody.innerHTML = '<tr><td colspan="7" class="px-3 py-4 text-slate-500">No expense rows.</td></tr>';
                expenseTotalProperty.textContent = 'KES 0.00';
                expenseTotalOwner.textContent = 'KES 0.00';
            };

            expenseButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const landlord = btn.getAttribute('data-landlord') || 'Landlord';
                    const payload = btn.getAttribute('data-expenses') || '[]';
                    const row = btn.closest('tr');
                    let landlordId = '';
                    if (row) {
                        const openPage = row.querySelector('a[href*="landlord_id="]');
                        if (openPage) {
                            const url = new URL(openPage.getAttribute('href'), window.location.origin);
                            landlordId = url.searchParams.get('landlord_id') || '';
                        }
                    }

                    if (landlordId !== '') {
                        const base = new URL(window.location.href);
                        base.searchParams.set('expense_export_landlord_id', landlordId);
                        base.searchParams.set('expense_export', 'csv');
                        if (expenseExportCsv) expenseExportCsv.setAttribute('href', base.pathname + base.search);

                        base.searchParams.set('expense_export', 'pdf');
                        if (expenseExportPdf) expenseExportPdf.setAttribute('href', base.pathname + base.search);
                    } else {
                        if (expenseExportCsv) expenseExportCsv.setAttribute('href', '#');
                        if (expenseExportPdf) expenseExportPdf.setAttribute('href', '#');
                    }

                    let rows = [];
                    try {
                        rows = JSON.parse(payload);
                    } catch (e) {
                        rows = [];
                    }

                    expenseTitle.textContent = 'Expense Breakdown - ' + landlord;

                    if (!Array.isArray(rows) || rows.length === 0) {
                        expenseBody.innerHTML = '<tr><td colspan="7" class="px-3 py-4 text-slate-500">No expense rows in this period.</td></tr>';
                        expenseTotalProperty.textContent = 'KES 0.00';
                        expenseTotalOwner.textContent = 'KES 0.00';
                    } else {
                        let totalPropertyExpense = 0;
                        let totalOwnerShareExpense = 0;
                        expenseBody.innerHTML = rows.map(function (r) {
                            const propertyExpenseValue = Number(r.property_expense || 0);
                            const ownerShareExpenseValue = Number(r.owner_share_expense || 0);
                            const utilityExpenseValue = Number(r.utility_expense || 0);
                            const maintenanceExpenseValue = Number(r.maintenance_expense || 0);
                            const otherExpenseValue = Number(r.other_expense || 0);
                            totalPropertyExpense += propertyExpenseValue;
                            totalOwnerShareExpense += ownerShareExpenseValue;

                            const ownership = Number(r.ownership_percent || 0).toFixed(2) + '%';
                            const utilityExpense = 'KES ' + utilityExpenseValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const maintenanceExpense = 'KES ' + maintenanceExpenseValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const otherExpense = 'KES ' + otherExpenseValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const propertyExpense = 'KES ' + propertyExpenseValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const ownerShareExpense = 'KES ' + ownerShareExpenseValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            return '<tr class="border-t border-slate-100">'
                                + '<td class="px-3 py-2">' + (r.property || '—') + '</td>'
                                + '<td class="px-3 py-2">' + ownership + '</td>'
                                + '<td class="px-3 py-2">' + utilityExpense + '</td>'
                                + '<td class="px-3 py-2">' + maintenanceExpense + '</td>'
                                + '<td class="px-3 py-2">' + otherExpense + '</td>'
                                + '<td class="px-3 py-2">' + propertyExpense + '</td>'
                                + '<td class="px-3 py-2">' + ownerShareExpense + '</td>'
                                + '</tr>';
                        }).join('');

                        expenseTotalProperty.textContent = 'KES ' + totalPropertyExpense.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        expenseTotalOwner.textContent = 'KES ' + totalOwnerShareExpense.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    expenseModal.classList.remove('hidden');
                    expenseModal.classList.add('flex');
                });
            });

            expenseCloseButtons.forEach(function (btn) {
                btn.addEventListener('click', closeExpenseModal);
            });

            expenseModal.addEventListener('click', function (event) {
                if (event.target === expenseModal) {
                    closeExpenseModal();
                }
            });
        })();
    </script>
</x-property.workspace>

