<x-loan-layout>
    <x-loan.page title="Payslips" subtitle="Open a payslip from any payroll period.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.payroll.hub') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payroll home</a>
            <a href="{{ route('loan.accounting.payroll.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Created payrolls</a>
            @include('loan.accounting.partials.export_buttons')
        </x-slot>
        @include('loan.accounting.partials.flash')

        <form method="get" class="mb-4">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Employee</label>
                    <select name="employee_id" class="h-10 min-w-[18rem] rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach(($employees ?? []) as $emp)
                            <option value="{{ $emp->id }}" @selected((int) ($employeeId ?? 0) === (int) $emp->id)>
                                {{ trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')) }} ({{ $emp->employee_number ?? '—' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Period</label>
                    <select name="accounting_payroll_period_id" class="h-10 min-w-[18rem] rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach(($periods ?? []) as $p)
                            <option value="{{ $p->id }}" @selected((int) ($periodId ?? 0) === (int) $p->id)>
                                {{ $p->period_start->format('Y-m-d') }} → {{ $p->period_end->format('Y-m-d') }}{{ $p->label ? ' · '.$p->label : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>

                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.accounting.payroll.payslips.index') }}" class="h-10 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">Employee</th>
                        <th class="px-5 py-3">Payroll period</th>
                        <th class="px-5 py-3 text-right">Net pay</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($lines as $line)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-5 py-3">
                                <span class="font-medium text-slate-900">{{ $line->employee?->full_name ?? '—' }}</span>
                                @if ($line->employee?->employee_number)
                                    <span class="text-slate-500 text-xs ml-1">· {{ $line->employee->employee_number }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-600 whitespace-nowrap">
                                {{ $line->period->period_start->format('Y-m-d') }} → {{ $line->period->period_end->format('Y-m-d') }}
                                @if ($line->period->label)
                                    <span class="block text-xs text-slate-500">{{ $line->period->label }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-800">{{ number_format((float) $line->net_pay, 2) }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('loan.accounting.payroll.lines.payslip', [$line->period, $line]) }}"
                                    class="text-indigo-600 font-medium text-sm hover:underline"
                                    target="_blank"
                                    rel="noopener"
                                >View payslip</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-slate-500">No payroll lines yet. Create a period and add employees from Created Payrolls.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if ($lines->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $lines->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
