<x-property.workspace
    title="Payroll payslip ledger"
    subtitle="Reconcile payroll runs, journal postings, and employee payment settlement."
    back-route="property.accounting.payroll"
    :stats="$stats"
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.payroll.payslips.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'q' => $filters['q'] ?? null]),
            'pdfUrl' => route('property.accounting.payroll.payslips.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'q' => $filters['q'] ?? null, 'format' => 'pdf']),
        ])
        <a href="{{ route('property.accounting.payroll') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back to payroll</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payroll.payslips') }}" class="grid gap-2 w-full md:grid-cols-6">
            <input type="month" name="period" value="{{ $filters['period'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0" />
            <select name="employee_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="0">All employees</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int)($filters['employee_id'] ?? 0) === (int)$employee->id)>{{ $employee->full_name ?: ('Employee #'.$employee->id) }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All statuses</option>
                @foreach(['draft','approved','posted','paid','reversed'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="payroll_run_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="0">All payroll runs</option>
                @foreach($runOptions as $run)
                    <option value="{{ $run->id }}" @selected((int)($filters['payroll_run_id'] ?? 0) === (int)$run->id)>#{{ $run->id }} {{ $run->label }}</option>
                @endforeach
            </select>
            <select name="payment_status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All payments</option>
                <option value="paid" @selected(($filters['payment_status'] ?? '') === 'paid')>Paid</option>
                <option value="unpaid" @selected(($filters['payment_status'] ?? '') === 'unpaid')>Unpaid</option>
            </select>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search payslip/ref/employee…" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
        </form>
    </x-slot>

    <x-slot name="above">
        @if (empty($tableRows))
            <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-6 text-sm text-slate-600 dark:text-slate-300 bg-white dark:bg-gray-800/60">
                <p class="font-semibold text-slate-800 dark:text-slate-100">No payslips yet.</p>
                <p class="mt-1">Payroll flow: run payroll -> approve -> post to accounting -> mark paid -> reconcile cash.</p>
                <a href="{{ route('property.accounting.payroll') }}" class="inline-flex mt-3 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-medium text-white hover:bg-indigo-700">Run Payroll</a>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4 shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 text-left">
                            @foreach($columns as $column)
                                <th class="py-2 pr-3 font-medium text-slate-600 dark:text-slate-300">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tableRows as $row)
                            <tr class="border-b border-slate-100 dark:border-slate-700/60 align-top">
                                @foreach($row as $cell)
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
    </x-slot>
    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset
</x-property.workspace>
