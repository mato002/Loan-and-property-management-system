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
<div class="flex items-center gap-1.5 overflow-x-auto rounded-xl border border-slate-200/90 bg-white px-3 py-2 shadow-sm">
    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mr-1 shrink-0">Quick</span>
    @foreach ($links as $item)
        @if (\Illuminate\Support\Facades\Route::has($item['route']))
            <a href="{{ route($item['route']) }}" class="inline-flex shrink-0 items-center rounded-md px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-[#2f4f4f] transition-colors">{{ $item['label'] }}</a>
        @endif
    @endforeach
</div>
