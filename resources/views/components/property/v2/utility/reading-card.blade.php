@props([
    'reading' => null,
    'anomalies' => [],
    'selectable' => false,
    'checkboxName' => 'reading_ids[]',
])

@php
    $r = $reading;
    if (! $r) return;
    $signals = is_array($anomalies) ? $anomalies : [];
    $topSeverity = collect($signals)->contains(fn ($s) => ($s['severity'] ?? '') === 'critical')
        ? 'critical'
        : (collect($signals)->contains(fn ($s) => ($s['severity'] ?? '') === 'warning') ? 'warning' : '');
    $cardClass = match ($topSeverity) {
        'critical' => 'has-anomaly-critical',
        'warning' => 'has-anomaly-warning',
        default => 'border-slate-200',
    };
    $invoice = $r->invoice;
@endphp

<article class="utility-reading-card {{ $cardClass }}">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-900 truncate">{{ $r->unit->property->name ?? '—' }} · {{ $r->unit->label ?? '—' }}</p>
            <p class="text-xs text-slate-500">{{ $r->billing_month }} · {{ ucfirst((string) $r->status) }}</p>
        </div>
        @if ($selectable)
            <input
                type="checkbox"
                name="{{ $checkboxName }}"
                value="{{ (int) $r->id }}"
                @disabled($r->pm_invoice_id !== null)
                class="mt-1 h-5 w-5 rounded border-slate-300 shrink-0"
            />
        @endif
    </div>

    <div class="mt-2 grid grid-cols-3 gap-2 text-xs tabular-nums">
        <div class="rounded-lg bg-slate-50 px-2 py-1.5">
            <span class="text-slate-500 block text-[10px]">Prev</span>
            <span class="font-semibold">{{ number_format((float) $r->previous_reading, 1) }}</span>
        </div>
        <div class="rounded-lg bg-slate-50 px-2 py-1.5">
            <span class="text-slate-500 block text-[10px]">Curr</span>
            <span class="font-semibold">{{ number_format((float) $r->current_reading, 1) }}</span>
        </div>
        <div class="rounded-lg bg-teal-50 px-2 py-1.5">
            <span class="text-teal-700 block text-[10px]">Used</span>
            <span class="font-bold text-teal-900">{{ number_format((float) $r->units_used, 1) }}</span>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-bold text-slate-900 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $r->amount) }}</span>
        @if ($signals !== [])
            <div class="flex flex-wrap gap-1">
                @foreach ($signals as $signal)
                    @include('property.agent.partials.utility_anomaly_badge', ['anomaly' => $signal])
                @endforeach
            </div>
        @endif
    </div>

    @if ($invoice)
        <div class="mt-2">
            <x-property.utility.invoice-allocation-bar
                :amount="(float) $invoice->amount"
                :paid="(float) $invoice->amount_paid"
                :invoice-no="(string) $invoice->invoice_no"
                :invoice-id="(int) $invoice->id"
                :status="ucfirst((string) $invoice->status)"
            />
        </div>
    @elseif ((string) $r->status === 'recorded')
        <p class="mt-2 text-[11px] text-amber-700 font-medium">Not yet invoiced</p>
    @endif

    @if ($r->is_estimated)
        <span class="mt-2 inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-800">Estimated</span>
    @endif
</article>
