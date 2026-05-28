@php
    $formId = $formId ?? null;
    $mode = $mode ?? 'invoices';
    $rangeLabel = $rangeLabel ?? ($mode === 'payments' ? ($receivedRangeLabel ?? 'this month') : ($billingRangeLabel ?? 'this month'));
@endphp

<p class="text-xs text-slate-500 dark:text-slate-400 w-full md:hidden">
    @if ($mode === 'payments')
        Summary uses payments <span class="font-medium text-slate-700 dark:text-slate-300">received</span> in
        <span class="font-medium">{{ $rangeLabel }}</span>.
    @else
        Filters invoices by <span class="font-medium text-slate-700 dark:text-slate-300">issue date</span>
        (<span class="font-medium">{{ $rangeLabel }}</span>).
    @endif
</p>

@php
    $rangeMonthOptions = [
        ['value' => '1', 'label' => '1 month'],
        ['value' => '2', 'label' => '2 months'],
        ['value' => '3', 'label' => '3 months'],
        ['value' => '6', 'label' => '6 months'],
        ['value' => '12', 'label' => '12 months'],
    ];
    if ($mode === 'payments') {
        array_unshift($rangeMonthOptions, ['value' => '0', 'label' => 'All dates']);
    }
@endphp
<x-property.filter-field
    type="select"
    name="range_months"
    label="Quick range" :options="$rangeMonthOptions"
    :value="(string) ($filters['range_months'] ?? 1)"
/>

<x-property.filter-field
    type="month"
    name="range_end"
    label="Ending month" :value="$filters['range_end'] ?? now()->format('Y-m')"
/>

<x-property.filter-field type="date" name="from" label="From" :value="$filters['from'] ?? ''" />
<x-property.filter-field type="date" name="to" label="To" :value="$filters['to'] ?? ''" />
