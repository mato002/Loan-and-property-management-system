<x-loan-layout>
    <x-loan.page title="Payroll: {{ $accounting_payroll_period->label ?? $accounting_payroll_period->period_start->format('M Y') }}" subtitle="{{ $accounting_payroll_period->period_start->format('Y-m-d') }} – {{ $accounting_payroll_period->period_end->format('Y-m-d') }} · {{ $accounting_payroll_period->status }}">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.payroll.hub') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payroll home</a>
            <a href="{{ route('loan.accounting.payroll.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">All periods</a>
            <a href="{{ route('loan.accounting.payroll.edit', $accounting_payroll_period) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit period</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl p-5 mb-6">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Add employee line</h3>
            <form method="post" action="{{ route('loan.accounting.payroll.lines.store', $accounting_payroll_period) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end">
                @csrf
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-slate-600">Employee</label>
                    <select name="employee_id" required class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select…</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->employee_number }} · {{ $e->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Gross</label><input name="gross_pay" type="number" step="0.01" min="0" required class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Deductions</label><input name="deductions" type="number" step="0.01" min="0" value="0" class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Net</label><input name="net_pay" type="number" step="0.01" min="0" required class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Payslip #</label><input name="payslip_number" class="mt-1 w-full rounded-lg border-slate-200 text-sm font-mono"/></div>
                <div class="sm:col-span-2 lg:col-span-6"><button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Add line</button></div>
            </form>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">Employee</th>
                        <th class="px-5 py-3 text-right">Gross</th>
                        <th class="px-5 py-3 text-right">Deductions</th>
                        <th class="px-5 py-3 text-right">Net</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($accounting_payroll_period->lines as $line)
                        <tr>
                            <td class="px-5 py-3">{{ $line->employee->full_name }} <span class="text-xs text-slate-500 font-mono">{{ $line->employee->employee_number }}</span></td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $line->gross_pay, 2) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $line->deductions, 2) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-medium">{{ number_format((float) $line->net_pay, 2) }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('loan.accounting.payroll.lines.payslip', [$accounting_payroll_period, $line]) }}" target="_blank" class="text-blue-600 font-medium text-sm mr-2">Payslip</a>
                                <a href="{{ route('loan.accounting.payroll.lines.edit', [$accounting_payroll_period, $line]) }}" class="text-indigo-600 font-medium text-sm mr-2">Edit</a>
                                <form method="post" action="{{ route('loan.accounting.payroll.lines.destroy', [$accounting_payroll_period, $line]) }}" class="inline" data-swal-confirm="Remove line?">@csrf @method('delete')<button type="submit" class="text-red-600 text-sm font-medium">Delete</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No lines yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-loan.page>
</x-loan-layout>
