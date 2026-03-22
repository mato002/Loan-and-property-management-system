<x-loan-layout>
    <x-loan.page title="Edit payroll line" subtitle="{{ $line->employee->full_name }}">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.payroll.show', $period) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl max-w-lg p-6 space-y-4">
            <form method="post" action="{{ route('loan.accounting.payroll.lines.update', [$period, $line]) }}">
                @csrf @method('patch')
                <div><label class="text-xs font-semibold text-slate-600">Gross</label><input name="gross_pay" type="number" step="0.01" min="0" value="{{ old('gross_pay', $line->gross_pay) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Deductions</label><input name="deductions" type="number" step="0.01" min="0" value="{{ old('deductions', $line->deductions) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Net</label><input name="net_pay" type="number" step="0.01" min="0" value="{{ old('net_pay', $line->net_pay) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Payslip #</label><input name="payslip_number" value="{{ old('payslip_number', $line->payslip_number) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm font-mono"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Notes</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $line->notes) }}</textarea></div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Update</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
