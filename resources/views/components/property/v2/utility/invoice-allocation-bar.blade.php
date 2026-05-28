@props([
    'amount' => 0.0,
    'paid' => 0.0,
    'invoiceNo' => null,
    'invoiceId' => null,
    'status' => null,
])

@php
    $amount = max(0.0, (float) $amount);
    $paid = max(0.0, min((float) $paid, $amount));
    $balance = max(0.0, $amount - $paid);
    $pct = $amount > 0 ? min(100, round(($paid / $amount) * 100)) : 0;
    $fillClass = $pct >= 100 ? '' : ($pct > 0 ? 'partial' : 'unpaid');
@endphp

<div {{ $attributes->merge(['class' => 'utility-invoice-panel']) }}>
    <div class="flex items-center justify-between gap-2">
        <span class="font-semibold text-slate-800">
            @if ($invoiceId && $invoiceNo)
                <a href="{{ route('property.revenue.invoices.show', $invoiceId, false) }}" class="text-indigo-600 hover:underline">{{ $invoiceNo }}</a>
            @elseif ($invoiceNo)
                {{ $invoiceNo }}
            @else
                Invoice
            @endif
        </span>
        @if ($status)
            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700">{{ $status }}</span>
        @endif
    </div>
    <div class="utility-allocation-bar" title="{{ $pct }}% allocated">
        <div class="utility-allocation-bar-fill {{ $fillClass }}" style="width: {{ $pct }}%"></div>
    </div>
    <div class="grid grid-cols-3 gap-2 text-[11px] tabular-nums">
        <div>
            <span class="text-slate-500 block">Billed</span>
            <span class="font-semibold text-slate-800">{{ \App\Services\Property\PropertyMoney::kes($amount) }}</span>
        </div>
        <div>
            <span class="text-slate-500 block">Paid</span>
            <span class="font-semibold text-emerald-700">{{ \App\Services\Property\PropertyMoney::kes($paid) }}</span>
        </div>
        <div class="text-right">
            <span class="text-slate-500 block">Balance</span>
            <span class="font-semibold {{ $balance > 0 ? 'text-amber-800' : 'text-emerald-700' }}">{{ \App\Services\Property\PropertyMoney::kes($balance) }}</span>
        </div>
    </div>
</div>
