@php
    $commissionPct = (float) \App\Models\PropertyPortalSetting::getValue('commission_default_percent', '10');
    $payableTableRows = $rows->getCollection()->map(function ($row) use ($commissionPct) {
        $commission = ((float) $row->amount_due) * ($commissionPct / 100);
        $net = max(0, (float) $row->amount_due - $commission);
        $lastPayout = \App\Models\PmLandlordPayout::query()
            ->where('status', 'paid')
            ->whereHas('items', fn ($q) => $q->where('landlord_id', $row->user_id))
            ->orderByDesc('paid_at')
            ->value('paid_at');

        $actions = '<div class="flex gap-2">'
            .'<a class="text-indigo-600 hover:text-indigo-700" href="'.e(route('property.landlords.statement', ['landlord' => $row->user_id], false)).'" data-turbo-frame="property-main">View statement</a>'
            .'<a class="text-emerald-700 hover:text-emerald-800" href="'.e(route('property.accounting.payables.landlord_settlements', ['property_id' => $row->property_id, 'landlord_id' => $row->user_id], false)).'" data-turbo-frame="property-main">Settlement</a>'
            .'<a class="text-slate-700 hover:text-slate-900" href="'.e(route('property.reports.landlord.balance_summary', [], false)).'" data-turbo-frame="property-main">Export</a>'
            .'</div>';

        return [
            optional($row->user)->name ?? '—',
            optional($row->property)->name ?? '—',
            \App\Services\Property\PropertyMoney::kes((float) $row->amount_due),
            \App\Services\Property\PropertyMoney::kes($commission),
            \App\Services\Property\PropertyMoney::kes($net),
            $lastPayout ? \Carbon\Carbon::parse($lastPayout)->format('Y-m-d') : '—',
            new \Illuminate\Support\HtmlString($actions),
        ];
    })->all();
@endphp
<x-property.workspace
    title="Landlord payables"
    subtitle="Core trust liability view: what is due to each landlord."
    back-route="property.accounting.index"
    :stats="[['label' => 'Landlord payable rows', 'value' => (string) ($rows->total() ?? 0), 'hint' => 'Net due > 0']]"
    :columns="['Landlord', 'Property', 'Amount Due', 'Commission', 'Net Payable', 'Last Payout', 'Actions']"
    :table-rows="$payableTableRows"
>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.landlord_payables') }}" class="flex gap-2">
            <select name="property_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Property: All</option>
                @foreach (($properties ?? collect()) as $p)
                    <option value="{{ $p->id }}" @selected((int) ($filters['property_id'] ?? 0) === (int) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <input type="search" name="landlord" value="{{ $filters['landlord'] ?? '' }}" placeholder="Landlord…" class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-44" />
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Apply</button>
        </form>
    </x-slot>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        What needs action: review landlord balances weekly and initiate payouts for approved payable amounts.
    </div>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>
