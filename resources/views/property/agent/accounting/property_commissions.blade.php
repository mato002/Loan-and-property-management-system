@php
    use App\Support\Property\WorkspaceRowAlert;
@endphp
<x-property.workspace
    title="Property commissions"
    subtitle="Management fees earned on collected rent, by property and period — commission amount, VAT, and payout reference."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="[]"
    :table-rows="[]"
    :show-search="false"
    :compact-list="true"
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'route' => 'property.accounting.payables.property_commissions',
            'query' => request()->except(['export', 'format', 'page']),
        ])
        <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Print</button>
        <a
            href="{{ route('property.accounting.payables.landlord_payment_fees', request()->only(['property_id', 'landlord_id'])) }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >Landlord payment &amp; fees</a>
        <a
            href="{{ route('property.settings.commission') }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >Fee %</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.property_commissions') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label>
                <select name="month" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[8rem]">
                    <option value="0" @selected((int) ($filters['month'] ?? 0) === 0)>All months</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" @selected((int) ($filters['month'] ?? 0) === $m)>{{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Year</label>
                <select name="year" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected((int) ($filters['year'] ?? now()->year) === (int) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[11rem]">
                    <option value="">All properties</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected((int) ($filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->code ? '['.$property->code.'] ' : '' }}{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord</label>
                <select name="landlord_id" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[11rem]">
                    <option value="">All landlords</option>
                    @foreach ($landlords as $landlord)
                        <option value="{{ $landlord->id }}" @selected((int) ($filters['landlord_id'] ?? 0) === (int) $landlord->id)>{{ $landlord->name }}</option>
                    @endforeach
                </select>
            </div>
            @if (count($cities) > 0)
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Region / city</label>
                    <select name="city" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[9rem]">
                        <option value="">All regions</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">On</label>
                <select name="on" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                    <option value="" @selected(($filters['on'] ?? '') === '')>All</option>
                    <option value="rent" @selected(($filters['on'] ?? '') === 'rent')>Rent only</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Property or landlord…" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm w-44" />
            </div>
            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm mt-5">
                <input type="checkbox" name="show_zero" value="1" @checked($filters['show_zero'] ?? false) class="rounded border-slate-300" />
                Show zero rows
            </label>
            <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800 mt-5">Search</button>
            <a href="{{ route('property.accounting.payables.property_commissions') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 mt-5">Reset</a>
        </form>
    </x-slot>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
        <p>
            Period: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $period_label }}</span>
            · VAT on commission: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ number_format((float) $vat_percent, 2) }}%</span>
        </p>
        @if ((int) ($filters['month'] ?? 0) > 0)
            <a
                href="{{ route('property.accounting.payables.property_commissions', array_merge(request()->except(['month', 'export', 'format']), ['month' => 0, 'year' => $filters['year'] ?? now()->year])) }}"
                class="font-medium text-indigo-700 hover:text-indigo-800"
            >Month by month summary</a>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-sm">
        <table class="property-erp-table min-w-[1180px] w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-800/80 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-3 py-3">Property</th>
                    <th class="px-3 py-3">On</th>
                    <th class="px-3 py-3">Date prepared</th>
                    <th class="px-3 py-3">Period</th>
                    <th class="px-3 py-3 text-right">Collected rent</th>
                    <th class="px-3 py-3 text-right">Fee %</th>
                    <th class="px-3 py-3 text-right">Commission Amt</th>
                    <th class="px-3 py-3 text-right">Commission VAT</th>
                    <th class="px-3 py-3 text-right">Total Commission</th>
                    <th class="px-3 py-3">Invoice #</th>
                    <th class="px-3 py-3">Invoice date</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $cellStyle = WorkspaceRowAlert::cellStyle($row['tone'] ?? '');
                        $propertyLabel = trim(($row['property_code'] !== '' ? '['.$row['property_code'].'] ' : '').($row['property_name'] ?? ''));
                        $statusBadge = match ($row['status'] ?? '') {
                            'posted' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                            'approved' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
                            'draft' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                            'accrued' => 'bg-yellow-100 text-yellow-900 dark:bg-yellow-900/40 dark:text-yellow-100',
                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
                        };
                    @endphp
                    <tr class="border-t border-slate-100 dark:border-slate-800 {{ WorkspaceRowAlert::trClass($row['tone'] ?? '') }}" @if ($cellStyle !== '') data-row-tone="{{ $row['tone'] }}" @endif>
                        <td class="px-3 py-3 font-medium text-slate-900 dark:text-white" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>
                            <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'month' => $row['period_month']]) }}" class="text-indigo-700 hover:text-indigo-800 dark:text-indigo-300">{{ $propertyLabel }}</a>
                            <div class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ $row['landlord_name'] }}@if ($row['city'] !== '') · {{ $row['city'] }}@endif</div>
                        </td>
                        <td class="px-3 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $row['on'] }}</td>
                        <td class="px-3 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $row['date_prepared'] }}</td>
                        <td class="px-3 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $row['period'] }}</td>
                        <td class="px-3 py-3 text-right tabular-nums" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ \App\Services\Property\PropertyMoney::kes((float) $row['collected']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ number_format((float) $row['rate_pct'], 2) }}%</td>
                        <td class="px-3 py-3 text-right tabular-nums font-semibold" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ \App\Services\Property\PropertyMoney::kes((float) $row['commission_amount']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ \App\Services\Property\PropertyMoney::kes((float) $row['commission_vat']) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums font-semibold" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ \App\Services\Property\PropertyMoney::kes((float) $row['total_commission']) }}</td>
                        <td class="px-3 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>
                            @if (! empty($row['payout_id']))
                                <a href="{{ route('property.accounting.payables.landlord_payouts', ['status' => $row['payout_status'] ?? '']) }}" class="text-indigo-700 hover:underline">{{ $row['invoice_no'] }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $row['invoice_date'] !== '' ? $row['invoice_date'] : '—' }}</td>
                        <td class="px-3 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge }}">{{ ucfirst((string) $row['status']) }}</span>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>
                            <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'month' => $row['period_month']]) }}" class="text-xs font-medium text-indigo-700 hover:text-indigo-800">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                            No property commissions for {{ $period_label }}. Try another month, or enable “Show zero rows”.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
