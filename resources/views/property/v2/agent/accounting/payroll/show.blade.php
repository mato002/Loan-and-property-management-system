<x-property.workspace :compact-list="false"
    title="Payroll Run #{{ $period->id }}"
    subtitle="{{ $period->label }} | Status: {{ ucfirst($period->status) }}"
    back-route="property.accounting.payroll"
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
            <div class="grid gap-3 md:grid-cols-4 text-sm">
                <div><span class="text-slate-500">Created by:</span> {{ $period->createdByUser?->name ?? '—' }}</div>
                <div><span class="text-slate-500">Approved by:</span> {{ $period->approvedByUser?->name ?? '—' }}</div>
                <div><span class="text-slate-500">Posted by:</span> {{ $period->postedByUser?->name ?? '—' }}</div>
                <div><span class="text-slate-500">Reversed by:</span> {{ $period->reversedByUser?->name ?? '—' }}</div>
                <div><span class="text-slate-500">Linked Journal Batch:</span>
                    @if($period->journal_batch_id)
                        <a class="text-indigo-600 hover:text-indigo-700" href="{{ route('property.accounting.entries.show', ['batch' => $period->journal_batch_id]) }}">#{{ $period->journal_batch_id }}</a>
                    @else
                        —
                    @endif
                </div>
                <div><span class="text-slate-500">Reversal Journal:</span>
                    @if($period->reversal_journal_batch_id)
                        <a class="text-indigo-600 hover:text-indigo-700" href="{{ route('property.accounting.entries.show', ['batch' => $period->reversal_journal_batch_id]) }}">#{{ $period->reversal_journal_batch_id }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
            <div class="flex justify-end mb-3">
                <form method="post" action="{{ route('property.accounting.payroll.payslips.email_all', ['period' => $period->id]) }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-700">
                        Send all payslips via email
                    </button>
                </form>
            </div>
            <h3 class="text-sm font-semibold mb-3 text-slate-900 dark:text-white">Employee breakdown</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 pr-2">Employee</th><th class="py-2 pr-2">Basic</th><th class="py-2 pr-2">Allowances</th><th class="py-2 pr-2">Deductions</th><th class="py-2 pr-2">Net</th><th class="py-2 pr-2">Payslip</th><th class="py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($period->lines as $line)
                        <tr class="border-b border-slate-100 dark:border-slate-700/60">
                            <td class="py-2 pr-2">{{ $line->employee?->full_name ?: ('Employee #'.$line->employee_id) }}</td>
                            <td class="py-2 pr-2">{{ \App\Services\Property\PropertyMoney::kes((float)$line->basic_pay) }}</td>
                            <td class="py-2 pr-2">{{ \App\Services\Property\PropertyMoney::kes((float)$line->allowances) }}</td>
                            <td class="py-2 pr-2">{{ \App\Services\Property\PropertyMoney::kes((float)$line->deductions) }}</td>
                            <td class="py-2 pr-2">{{ \App\Services\Property\PropertyMoney::kes((float)$line->net_pay) }}</td>
                            <td class="py-2 pr-2">#{{ $line->payslip_number ?: $line->id }}</td>
                            <td class="py-2">
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <a class="text-indigo-600 hover:text-indigo-700 font-medium" href="{{ route('property.accounting.payroll.lines.payslip.show', ['period' => $period->id, 'line' => $line->id]) }}">Preview</a>
                                    <a class="text-slate-700 hover:text-slate-900 font-medium" href="{{ route('property.accounting.payroll.lines.payslip.download', ['period' => $period->id, 'line' => $line->id]) }}">Download</a>
                                    <form method="post" action="{{ route('property.accounting.payroll.lines.payslip.email', ['period' => $period->id, 'line' => $line->id]) }}">
                                        @csrf
                                        <button type="submit" class="text-emerald-700 hover:text-emerald-800 font-medium">Send email</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                Total Gross: {{ \App\Services\Property\PropertyMoney::kes($totals['gross']) }} |
                Total Deductions: {{ \App\Services\Property\PropertyMoney::kes($totals['deductions']) }} |
                Total Net: {{ \App\Services\Property\PropertyMoney::kes($totals['net']) }}
            </div>
        </div>
    </x-slot>
</x-property.workspace>
