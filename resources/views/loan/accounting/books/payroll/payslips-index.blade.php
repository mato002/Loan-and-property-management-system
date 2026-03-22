<x-loan-layout>
    <x-loan.page title="Payslips" subtitle="Open a payslip from any payroll period.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.payroll.hub') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payroll home</a>
            <a href="{{ route('loan.accounting.payroll.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Created payrolls</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

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
