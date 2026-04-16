<x-loan-layout>
    <x-loan.page
        title="My approval requests"
        subtitle="Loan applications awaiting credit action, plus finance queues you can open when your role allows."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#2f4f4f] bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Loan applications</a>
            <a href="{{ route('loan.account.show') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to account</a>
        </x-slot>

        @if (! $canOpenAccounting)
            <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-800">Accounting approvals</p>
                <p class="mt-1 text-slate-600">
                    Requisition and salary-advance queues are listed here when your role includes <span class="font-medium">accountant</span>, <span class="font-medium">manager</span>, or <span class="font-medium">admin</span> (same access as the accounting module). Your own submissions still appear under <strong>Submitted by me</strong> when applicable.
                </p>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Awaiting my action</h2>
                    <p class="text-xs text-slate-500 mt-1">Credit pipeline and finance approvals you can open</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($waitingOnMe as $row)
                        <li class="px-5 py-4 hover:bg-slate-50/80">
                            <a href="{{ $row['url'] }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['title'] }}</a>
                            <p class="text-xs text-slate-500 mt-1">{{ $row['meta'] }} · <span class="tabular-nums">{{ $row['when'] }}</span></p>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-sm text-slate-500 text-center">Nothing in this queue right now.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Submitted by me</h2>
                    <p class="text-xs text-slate-500 mt-1">Requisitions and salary advances tied to your user or staff record</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($submittedByMe as $row)
                        <li class="px-5 py-4 hover:bg-slate-50/80">
                            @if (! empty($row['url']))
                                <a href="{{ $row['url'] }}" class="text-sm font-medium text-slate-900 hover:text-indigo-700 hover:underline">{{ $row['title'] }}</a>
                            @else
                                <p class="text-sm font-medium text-slate-900">{{ $row['title'] }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Open in accounting requires accountant / manager / admin access.</p>
                            @endif
                            <p class="text-xs text-slate-500 mt-1">{{ $row['meta'] }} · {{ $row['when'] }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-sm text-slate-500 text-center">No submissions found for your account yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
