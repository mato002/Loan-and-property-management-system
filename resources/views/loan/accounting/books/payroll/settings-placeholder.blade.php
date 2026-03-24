<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$intro">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.payroll.hub') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payroll home</a>
            <a href="{{ route('loan.accounting.payroll.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payroll periods</a>
            <a href="{{ route('loan.accounting.payroll.payslips.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payslips</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="text-sm font-semibold text-slate-900">How to apply this setting in payroll runs</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    Use this section as the policy reference for your team. Apply the approved rates and amounts when adding employee lines in each payroll period.
                    The payroll run pages are already active and available for immediate use.
                </p>

                <ol class="mt-4 space-y-2 text-sm text-slate-700 list-decimal list-inside">
                    <li>Open <span class="font-medium">Payroll periods</span> and select the period to process.</li>
                    <li>Add or edit employee lines with gross pay, deductions, and net pay.</li>
                    <li>Generate payslips from the period or from the central payslips page.</li>
                    <li>Post approved payroll totals into accounting journals as part of month-end close.</li>
                </ol>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Quick actions</p>
                <div class="mt-3 grid gap-2">
                    <a href="{{ route('loan.accounting.payroll.create') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Create payroll period</a>
                    <a href="{{ route('loan.accounting.payroll.index') }}" class="inline-flex items-center justify-center rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100/60">Manage payroll periods</a>
                    <a href="{{ route('loan.accounting.payroll.payslips.index') }}" class="inline-flex items-center justify-center rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100/60">Open payslips</a>
                </div>
            </div>
        </div>

        <div class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-slate-900">Reference table</h3>
            <p class="mt-1 text-sm text-slate-600">Keep your approved rates and notes here for consistency across payroll periods.</p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Item</th>
                            <th class="px-4 py-3 text-left">Method</th>
                            <th class="px-4 py-3 text-left">Typical basis</th>
                            <th class="px-4 py-3 text-left">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr>
                            <td class="px-4 py-3 font-medium">Statutory deduction</td>
                            <td class="px-4 py-3">Fixed amount or percentage</td>
                            <td class="px-4 py-3">Gross pay</td>
                            <td class="px-4 py-3">Examples: NSSF, NHIF, PAYE.</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium">Other deduction</td>
                            <td class="px-4 py-3">Fixed amount</td>
                            <td class="px-4 py-3">Per employee agreement</td>
                            <td class="px-4 py-3">Examples: welfare, SACCO, staff loan recovery.</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium">Bonus or allowance</td>
                            <td class="px-4 py-3">Fixed amount or percentage</td>
                            <td class="px-4 py-3">Gross pay or attendance output</td>
                            <td class="px-4 py-3">Examples: transport, housing, performance bonus.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
            <p class="font-medium text-slate-900">Formula reminder</p>
            <p class="mt-2">Net Pay = Gross Pay + Allowances/Bonuses - Statutory Deductions - Other Deductions.</p>
        </div>
    </x-loan.page>
</x-loan-layout>
