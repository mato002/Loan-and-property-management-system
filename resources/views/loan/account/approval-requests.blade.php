<x-loan-layout>
    <x-loan.page
        title="My approval requests"
        subtitle="Items waiting on you or escalated for your sign-off."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.account.show') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to account</a>
        </x-slot>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Awaiting my action</h2>
                    <p class="text-xs text-slate-500 mt-1">Loans, restructures, and limits routed to you</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ([
                        ['Ksh 420,000 · SME term (Westlands)', 'Credit committee', 'Due today'],
                        ['Limit increase · Client #8821', 'Branch manager', 'Due tomorrow'],
                        ['Write-off memo · PAR 180+', 'Risk', '3 days left'],
                    ] as [$title, $stage, $due])
                        <li class="px-5 py-4 hover:bg-slate-50/80">
                            <p class="text-sm font-medium text-indigo-600">{{ $title }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $stage }} · <span class="tabular-nums">{{ $due }}</span></p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Submitted by me</h2>
                    <p class="text-xs text-slate-500 mt-1">Track progress on your own submissions</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ([
                        ['Leave · Apr 2–5', 'Pending HR', 'Submitted Mar 18'],
                        ['Petty cash top-up', 'Pending finance', 'Submitted Mar 17'],
                    ] as [$title, $status, $submitted])
                        <li class="px-5 py-4 hover:bg-slate-50/80">
                            <p class="text-sm font-medium text-slate-900">{{ $title }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $status }} · {{ $submitted }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
