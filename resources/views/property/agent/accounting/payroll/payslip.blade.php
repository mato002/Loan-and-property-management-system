<x-property-layout>
    <x-slot name="header">Payslip</x-slot>

    <x-property.page
        title="Employee payslip"
        subtitle="Reference {{ $reference }} · {{ $entryDate }}"
    >
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('property.accounting.payroll.payslips') }}" class="inline-flex rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back to payslips</a>
            <button type="button" onclick="window.print()" class="inline-flex rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Print</button>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-5 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Employee</p>
                    <p class="text-base font-semibold text-slate-900 dark:text-white">{{ $employeeName }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Payslip reference</p>
                    <p class="text-base font-semibold text-slate-900 dark:text-white">{{ $reference }}</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <p class="text-xs text-slate-500">Basic pay</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ \App\Services\Property\PropertyMoney::kes($basicPay) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <p class="text-xs text-slate-500">Allowances</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ \App\Services\Property\PropertyMoney::kes($allowances) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <p class="text-xs text-slate-500">Deductions</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ \App\Services\Property\PropertyMoney::kes($deductions) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-300 dark:border-emerald-700 p-3">
                    <p class="text-xs text-slate-500">Net pay</p>
                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ \App\Services\Property\PropertyMoney::kes($netPay) }}</p>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-2 pr-3">Account</th>
                            <th class="py-2 pr-3">Type</th>
                            <th class="py-2">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr class="border-b border-slate-100 dark:border-slate-700/70">
                                <td class="py-2 pr-3">{{ $entry->account_name }}</td>
                                <td class="py-2 pr-3">{{ ucfirst($entry->entry_type) }}</td>
                                <td class="py-2">{{ \App\Services\Property\PropertyMoney::kes((float) $entry->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
