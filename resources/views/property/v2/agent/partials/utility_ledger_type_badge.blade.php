@php
    $tone = match ($entryType ?? '') {
        'water_invoice', 'penalty' => 'bg-amber-100 text-amber-900',
        'payment', 'credit_note', 'penalty_reversal', 'invoice_cancelled' => 'bg-emerald-100 text-emerald-900',
        'payment_reversal' => 'bg-red-100 text-red-800',
        'opening_balance' => 'bg-slate-200 text-slate-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $tone }}">{{ $label ?? 'Entry' }}</span>
