<x-loan-layout>
    <x-loan.page
        title="My salary advance"
        subtitle="Request or track your own salary advance against payroll policy."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.account.show') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to account</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Recent requests</h2>
                <button type="button" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm opacity-60 cursor-not-allowed pointer-events-none" disabled aria-disabled="true">New request</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Ref</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Submitted</th>
                            <th class="px-5 py-3">Payroll month</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ([
                            ['SAD-2025-014', 'Ksh 35,000', '2025-03-10', 'March 2025', 'Paid (net payroll)'],
                            ['SAD-2025-008', 'Ksh 20,000', '2025-02-04', 'February 2025', 'Paid (net payroll)'],
                        ] as [$ref, $amt, $sub, $month, $status])
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $ref }}</td>
                                <td class="px-5 py-3 text-slate-700 tabular-nums">{{ $amt }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $sub }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $month }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50">
                <p class="text-xs text-slate-500">Sample data — wire to payroll / HR when modules are live.</p>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
