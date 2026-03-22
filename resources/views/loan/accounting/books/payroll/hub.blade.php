<x-loan-layout>
    <div class="min-h-[calc(100vh-5rem)] bg-[#f2ede4] py-8 sm:py-12 px-4">
        <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md border border-slate-200/90 overflow-hidden">
            <div class="px-6 sm:px-10 pt-8 pb-5 border-b border-slate-200">
                <div class="flex items-center gap-4">
                    <a
                        href="{{ route('loan.accounting.books') }}"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-300 text-[#1a2f42] hover:bg-slate-50 transition-colors"
                        title="Back to books"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <h1 class="font-serif text-2xl sm:text-[1.65rem] font-semibold text-[#1a2f42] tracking-tight">Employee Payroll</h1>
                </div>
            </div>

            <div class="p-6 sm:p-10">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <a
                        href="{{ $currentPayrollCreateUrl }}"
                        class="flex gap-4 p-5 rounded-lg border border-slate-300 bg-white hover:border-[#1a2f42]/40 hover:shadow-sm transition-all group text-left"
                    >
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-[#1a2f42]">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v3m0 0v3m0-3h3m-3 0H9" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-serif text-base font-bold text-[#1a2f42] group-hover:underline decoration-[#1a2f42]/40">{{ $currentMonthTitle }}</h2>
                            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">Setup &amp; generate current month payroll</p>
                        </div>
                    </a>

                    <a
                        href="{{ route('loan.accounting.payroll.index') }}"
                        class="flex gap-4 p-5 rounded-lg border border-slate-300 bg-white hover:border-[#1a2f42]/40 hover:shadow-sm transition-all group text-left"
                    >
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-[#1a2f42]">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5h4l5 5v8" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-serif text-base font-bold text-[#1a2f42] group-hover:underline decoration-[#1a2f42]/40">Created Payrolls</h2>
                            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">View prepared payroll &amp; deductions for every month</p>
                        </div>
                    </a>

                    <a
                        href="{{ route('loan.accounting.payroll.payslips.index') }}"
                        class="flex gap-4 p-5 rounded-lg border border-slate-300 bg-white hover:border-[#1a2f42]/40 hover:shadow-sm transition-all group text-left"
                    >
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-[#1a2f42]">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 8l4 4m0 0l-4 4m4-4H9" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-serif text-base font-bold text-[#1a2f42] group-hover:underline decoration-[#1a2f42]/40">Payslips</h2>
                            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">View payslips generated from payroll months</p>
                        </div>
                    </a>

                    <a
                        href="{{ route('loan.accounting.payroll.settings.statutory') }}"
                        class="flex gap-4 p-5 rounded-lg border border-slate-300 bg-white hover:border-[#1a2f42]/40 hover:shadow-sm transition-all group text-left"
                    >
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-[#1a2f42]">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-serif text-base font-bold text-[#1a2f42] group-hover:underline decoration-[#1a2f42]/40">Statutory Deductions</h2>
                            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">Review and configure statutory deductions e.g. NSSF</p>
                        </div>
                    </a>

                    <a
                        href="{{ route('loan.accounting.payroll.settings.other_deductions') }}"
                        class="flex gap-4 p-5 rounded-lg border border-slate-300 bg-white hover:border-[#1a2f42]/40 hover:shadow-sm transition-all group text-left"
                    >
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-[#1a2f42]">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-serif text-base font-bold text-[#1a2f42] group-hover:underline decoration-[#1a2f42]/40">Other Salary Deductions</h2>
                            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">Configure other salary deductions e.g. welfare</p>
                        </div>
                    </a>

                    <a
                        href="{{ route('loan.accounting.payroll.settings.bonuses') }}"
                        class="flex gap-4 p-5 rounded-lg border border-slate-300 bg-white hover:border-[#1a2f42]/40 hover:shadow-sm transition-all group text-left"
                    >
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-[#1a2f42]">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-serif text-base font-bold text-[#1a2f42] group-hover:underline decoration-[#1a2f42]/40">Bonuses &amp; Allowances</h2>
                            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">Configure additional payroll income e.g. incentives</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-loan-layout>
