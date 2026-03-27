<x-property.workspace
    :title="'Landlord Statement: '.$landlord->name"
    :subtitle="'Printable period snapshot · '.$periodLabel"
    back-route="property.landlords.index"
    :stats="[]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="_top" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to profile</a>
        <button type="button" onclick="window.print()" class="inline-flex rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Print</button>
    </x-slot>

    <style>
        @media print {
            .statement-no-print { display: none !important; }
        }
    </style>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ $landlord->name }}</h2>
                <p class="text-sm text-slate-600">{{ $landlord->email }}</p>
            </div>
            <div class="text-sm text-slate-600">
                <p><span class="font-medium text-slate-900">Period:</span> {{ $periodLabel }}</p>
                <p><span class="font-medium text-slate-900">Generated:</span> {{ now()->format('Y-m-d H:i') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mt-4">
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Properties</p>
                <p class="text-base font-semibold text-slate-900">{{ $totals['properties'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Ownership %</p>
                <p class="text-base font-semibold text-slate-900">{{ number_format((float) ($totals['ownership_sum'] ?? 0), 2) }}%</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Owner share</p>
                <p class="text-base font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['owner_share'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Pending share</p>
                <p class="text-base font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['pending_share'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Your earnings</p>
                <p class="text-base font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['agent_earning'] ?? 0)) }}</p>
            </div>
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Property Breakdown</h3>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Property</th>
                    <th class="px-4 py-3">Ownership %</th>
                    <th class="px-4 py-3">Owner share</th>
                    <th class="px-4 py-3">Pending share</th>
                    <th class="px-4 py-3">Agent earning</th>
                    <th class="px-4 py-3">Last collection</th>
                </tr>
            </thead>
            <tbody>
                @forelse($propertyBreakdown as $row)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ $row['property_name'] }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $row['ownership_percent'], 2) }}%</td>
                        <td class="px-4 py-3">{{ \App\Services\Property\PropertyMoney::kes((float) $row['owner_share']) }}</td>
                        <td class="px-4 py-3">{{ \App\Services\Property\PropertyMoney::kes((float) $row['pending_share']) }}</td>
                        <td class="px-4 py-3">{{ \App\Services\Property\PropertyMoney::kes((float) $row['agent_earning']) }}</td>
                        <td class="px-4 py-3">{{ !empty($row['last_paid_at']) ? \Illuminate\Support\Carbon::parse((string) $row['last_paid_at'])->format('Y-m-d') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No linked properties.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>

