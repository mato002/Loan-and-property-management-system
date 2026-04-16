<x-loan-layout>
    <x-loan.page
        title="My salary advance"
        subtitle="Requests linked to your staff record (matched by email). Finance approves and settles in the accounting module."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.account.show') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to account</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Recent requests</h2>
                @if ($canOpenAccounting)
                    <a href="{{ route('loan.accounting.advances.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">New request</a>
                @else
                    <button type="button" class="inline-flex items-center justify-center rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-500 cursor-not-allowed" disabled title="Only accountant, manager, or admin can create advances in the accounting module">New request</button>
                @endif
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
                        @forelse ($advances as $advance)
                            @php
                                $ref = 'SAD-'.str_pad((string) $advance->id, 5, '0', STR_PAD_LEFT);
                                $payrollMonth = $advance->settled_on
                                    ? $advance->settled_on->format('F Y')
                                    : $advance->requested_on?->format('F Y');
                                $statusLabel = match ($advance->status) {
                                    'pending' => 'Pending approval',
                                    'approved' => 'Approved',
                                    'rejected' => 'Rejected',
                                    'settled' => 'Settled (payroll)',
                                    default => ucfirst((string) $advance->status),
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs">
                                    @if ($canOpenAccounting)
                                        <a href="{{ route('loan.accounting.advances.edit', $advance) }}" class="text-indigo-600 font-medium hover:underline">{{ $ref }}</a>
                                    @else
                                        <span class="text-slate-800 font-medium">{{ $ref }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-700 tabular-nums">{{ number_format((float) $advance->amount, 0) }} {{ $advance->currency }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $advance->requested_on?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $payrollMonth ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $statusLabel }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-slate-500 text-sm">No salary advances are linked to your email yet. After seeding, ensure an <span class="font-medium">employees</span> row uses the same email as your login.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50">
                <p class="text-xs text-slate-500">Data comes from <span class="font-mono text-slate-600">accounting_salary_advances</span> for employees whose email matches your user.</p>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
