<x-property-layout>
    <x-slot name="header">Payslip</x-slot>

    <style>
        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }
            body * {
                visibility: hidden !important;
            }
            .payslip-print, .payslip-print * {
                visibility: visible !important;
            }
            .payslip-print {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none !important;
                border: 0 !important;
                background: #fff !important;
            }
            .payslip-print p,
            .payslip-print td,
            .payslip-print th,
            .payslip-print div {
                color: #111827 !important;
            }
            .payslip-no-print {
                display: none !important;
            }
        }
    </style>

    <x-property.page
        title="Employee payslip"
        subtitle="Reference {{ $reference }} · {{ $entryDate }}"
    >
        <div class="flex flex-wrap gap-2 payslip-no-print">
            <a href="{{ route('property.accounting.payroll.payslips') }}" class="inline-flex rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back to payslips</a>
            <button type="button" onclick="window.print()" class="inline-flex rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Print</button>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-5 shadow-sm payslip-print">
            <div class="border-b border-slate-200 pb-3 mb-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        @if (! empty($companyLogoUrl))
                            <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }} logo" class="h-12 w-auto object-contain" />
                        @endif
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ $companyName }} Payroll</p>
                            <p class="text-xs text-slate-500">Official employee payment slip</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Period</p>
                        <p class="text-sm font-semibold text-slate-900">{{ \Illuminate\Support\Carbon::parse($entryDate)->format('F Y') }}</p>
                    </div>
                </div>
                <div class="mt-2 text-[11px] text-slate-500">
                    Reference: {{ $reference }} | Printed on {{ now()->format('Y-m-d H:i') }}
                </div>
            </div>

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

            <div class="mt-6 grid sm:grid-cols-2 gap-6">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Prepared by</p>
                    <div class="mt-6 border-t border-slate-300"></div>
                    <p class="mt-1 text-xs text-slate-500">Name / signature</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Received by</p>
                    <div class="mt-6 border-t border-slate-300"></div>
                    <p class="mt-1 text-xs text-slate-500">Employee signature</p>
                </div>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
