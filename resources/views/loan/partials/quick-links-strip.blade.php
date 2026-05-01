{{-- Compact jump links; shown under page titles via <x-loan.page> --}}
@php
    $links = [
        ['label' => 'Dashboard', 'route' => 'loan.dashboard'],
        ['label' => 'Clients', 'route' => 'loan.clients.index'],
        ['label' => 'Applications', 'route' => 'loan.book.applications.index'],
        ['label' => 'Loans', 'route' => 'loan.book.loans.index'],
        ['label' => 'Disbursements', 'route' => 'loan.book.disbursements.index'],
        ['label' => 'Payments', 'route' => 'loan.payments.processed'],
        ['label' => 'Pay-in report', 'route' => 'loan.payments.report'],
        ['label' => 'Unposted', 'route' => 'loan.payments.unposted'],
        ['label' => 'Accounting', 'route' => 'loan.accounting.books'],
    ];
@endphp
<div class="flex w-full min-w-0 items-center gap-1 rounded-xl border border-slate-200/90 bg-white px-2 py-2 shadow-sm">
    <span class="mr-1 shrink-0 text-[10px] font-bold uppercase tracking-wider text-slate-400">Quick</span>
    @foreach ($links as $item)
        @if (\Illuminate\Support\Facades\Route::has($item['route']))
            <a
                href="{{ route($item['route']) }}"
                class="inline-flex min-w-0 flex-1 items-center justify-center truncate rounded-md px-1.5 py-1 text-[11px] font-semibold text-slate-600 transition-colors hover:bg-slate-100 hover:text-[#2f4f4f]"
                title="{{ $item['label'] }}"
            >
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
</div>
