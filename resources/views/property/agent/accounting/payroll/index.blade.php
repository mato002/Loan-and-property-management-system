@php
    $payrollFormHasErrors = $errors->has('period_month')
        || $errors->has('period_year')
        || $errors->has('notes')
        || $errors->has('lines')
        || collect($errors->keys())->contains(fn ($key) => is_string($key) && str_starts_with($key, 'lines.'));
@endphp
<x-property.workspace
    title="Payroll processing and control"
    subtitle="Run, approve, post, and reverse payroll with full accounting and audit controls."
    back-route="property.accounting.index"
    :stats="$stats"
>
    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from(['showRunPayrollForm' => $payrollFormHasErrors]) !!}"></x-slot>

    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            data-property-modal-open="showRunPayrollForm" @click="showRunPayrollForm = true"
        >
            <i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>
            <span>Run payroll</span>
        </button>
        <a href="{{ route('property.accounting.payroll.payslips') }}" class="inline-flex justify-center items-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payslip ledger</a>
        <a href="{{ route('property.accounting.payroll.settings') }}" class="inline-flex justify-center items-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payroll settings</a>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showRunPayrollForm"
            close="showRunPayrollForm = false"
            name="payroll-run"
            title="Run payroll"
            max-width="4xl"
        >
            <form method="post" action="{{ route('property.accounting.payroll.store') }}" id="payroll-run-form" class="space-y-3">
                @csrf
                <div class="grid gap-3 md:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label>
                        <input type="number" min="1" max="12" name="period_month" value="{{ old('period_month', now()->month) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" required />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Year</label>
                        <input type="number" min="2000" max="2200" name="period_year" value="{{ old('period_year', now()->year) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" required />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 dark:border-slate-700">
                                <th class="py-2 pr-2">Employee</th><th class="py-2 pr-2">Basic</th><th class="py-2 pr-2">Allowances</th><th class="py-2 pr-2">Deductions</th><th class="py-2">Net</th>
                            </tr>
                        </thead>
                        <tbody id="payroll-lines-body">
                            @for ($i = 0; $i < max(3, count(old('lines', []))); $i++)
                                <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                    <td class="py-2 pr-2">
                                        <select name="lines[{{ $i }}][employee_id]" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5">
                                            <option value="">Select employee</option>
                                            @foreach ($employees as $emp)
                                                <option value="{{ $emp->id }}" @selected((int) old("lines.$i.employee_id") === (int) $emp->id)>{{ $emp->full_name ?: ('Employee #'.$emp->id) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-2 pr-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][basic_pay]" value="{{ old("lines.$i.basic_pay", 0) }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 payroll-basic" /></td>
                                    <td class="py-2 pr-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][allowances]" value="{{ old("lines.$i.allowances", 0) }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 payroll-allowances" /></td>
                                    <td class="py-2 pr-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][deductions]" value="{{ old("lines.$i.deductions", 0) }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1.5 payroll-deductions" /></td>
                                    <td class="py-2"><input type="text" readonly value="0.00" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-gray-900/60 px-2 py-1.5 payroll-net" /></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <div class="text-sm text-slate-600 dark:text-slate-300">
                    Total Gross: <span id="grossTotal">0.00</span> |
                    Total Deductions: <span id="deductionsTotal">0.00</span> |
                    Total Net: <span id="netTotal">0.00</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" name="action" value="save_draft" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white">Save as Draft</button>
                    <button type="submit" name="action" value="approve" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Approve Payroll</button>
                    <button type="submit" name="action" value="post" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Post to Accounting</button>
                </div>
            </form>
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payroll') }}" class="w-full flex flex-wrap items-end gap-2">
            <input type="month" name="period" value="{{ $filters['period'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            <select name="status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">All statuses</option>
                @foreach (['draft','approved','posted','reversed'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
            <select name="employee_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="0">All employees</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected((int) ($filters['employee_id'] ?? 0) === (int) $emp->id)>{{ $emp->full_name ?: ('Employee #'.$emp->id) }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
        </form>
    </x-slot>

    <x-slot name="secondary">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Payroll runs</h3>
            @if (empty($tableRows))
                <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-5 text-sm text-slate-600 dark:text-slate-300">
                    <p class="font-medium">No payroll runs yet.</p>
                    <p class="mt-1">Run payroll to generate employee salary postings.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-slate-200 dark:border-slate-700">
                                @foreach ($columns as $column)
                                    <th class="py-2 pr-3 font-medium text-slate-600 dark:text-slate-300">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tableRows as $row)
                                <tr class="border-b border-slate-100 dark:border-slate-700/60 align-top">
                                    @foreach ($row as $cell)
                                        <td class="py-2 pr-3 text-slate-800 dark:text-slate-100">
                                            @if ($cell instanceof \Illuminate\Support\HtmlString)
                                                {!! $cell !!}
                                            @else
                                                {{ $cell }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <form method="post" action="{{ route('property.accounting.payroll.employee.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-5xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Generate payslip</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payroll run / Period</label>
                    <select name="accounting_payroll_period_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select period</option>
                        @foreach($runOptions as $runOpt)
                            <option value="{{ $runOpt->id }}">{{ $runOpt->label }} ({{ $runOpt->status }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Employee</label>
                    <select name="employee_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select employee</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name ?: ('Employee #'.$emp->id) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Basic pay</label>
                    <input type="number" step="0.01" min="0" name="basic_pay" value="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Allowances</label>
                    <input type="number" step="0.01" min="0" name="allowances" value="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Deductions</label>
                    <input type="number" step="0.01" min="0" name="deductions" value="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300 mt-6">
                    <input type="checkbox" name="send_email" value="1" class="rounded border-slate-300 dark:border-slate-600" />
                    Send via email
                </label>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Generate Payslip</button>
                <a href="{{ route('property.accounting.payroll.payslips') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm">Preview / Download from Payslip Ledger</a>
            </div>
        </form>
    </x-slot>

    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset
</x-property.workspace>

<script>
(() => {
    const form = document.getElementById('payroll-run-form');
    if (!form) return;
    const body = form.querySelector('#payroll-lines-body');
    const recalc = () => {
        let gross = 0, ded = 0, net = 0;
        body?.querySelectorAll('tr').forEach((row) => {
            const basic = parseFloat(row.querySelector('.payroll-basic')?.value || '0');
            const allowances = parseFloat(row.querySelector('.payroll-allowances')?.value || '0');
            const deductions = parseFloat(row.querySelector('.payroll-deductions')?.value || '0');
            const lineGross = basic + allowances;
            const lineNet = lineGross - deductions;
            const netEl = row.querySelector('.payroll-net');
            if (netEl) netEl.value = lineNet.toFixed(2);
            gross += lineGross;
            ded += deductions;
            net += lineNet;
        });
        const grossEl = document.getElementById('grossTotal');
        const dedEl = document.getElementById('deductionsTotal');
        const netEl = document.getElementById('netTotal');
        if (grossEl) grossEl.textContent = gross.toFixed(2);
        if (dedEl) dedEl.textContent = ded.toFixed(2);
        if (netEl) netEl.textContent = net.toFixed(2);
    };
    form.addEventListener('input', (e) => {
        if (e.target.matches('.payroll-basic, .payroll-allowances, .payroll-deductions')) recalc();
    });
    recalc();
})();
</script>
