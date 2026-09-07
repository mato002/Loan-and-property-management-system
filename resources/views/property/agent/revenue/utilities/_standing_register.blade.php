@php
    $standingCharges = $standingCharges ?? collect();
    $standingMonthlyTotal = (float) ($standingMonthlyTotal ?? 0);
    $standingLeaseCount = (int) ($standingLeaseCount ?? 0);
    $standingExportQuery = array_merge(request()->query(), ['export' => 'standing', 'ops_tab' => 'standing']);
@endphp
<div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm space-y-3 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Standing charges register</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Recurring extras on each active lease (garbage, service charge, and similar). This is the live register — not the same as monthly posted charge lines below.
            </p>
        </div>
        <a href="{{ route('property.revenue.utilities', $standingExportQuery, false) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50 min-h-[40px]">
            Export CSV
        </a>
    </div>
    <p class="text-sm text-slate-700">
        <span class="font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($standingMonthlyTotal) }}</span>
        <span class="text-slate-500"> / month</span>
        <span class="text-slate-400"> · </span>
        {{ $standingLeaseCount }} {{ \Illuminate\Support\Str::plural('lease', $standingLeaseCount) }}
        @if ($standingCharges instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <span class="text-slate-400"> · </span>{{ $standingCharges->total() }} lines
        @endif
    </p>
    <x-property.responsive.table-wrapper>
        <table class="property-erp-table min-w-full border-collapse text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Tenant</th>
                    <th class="px-3 py-2">Account</th>
                    <th class="px-3 py-2">Property / unit</th>
                    <th class="px-3 py-2">Charge</th>
                    <th class="px-3 py-2">Monthly amount</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($standingCharges as $row)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                        <td class="px-3 py-2 font-medium">
                            @if ((int) ($row['tenant_id'] ?? 0) > 0)
                                <a href="{{ route('property.tenants.show', ['tenant' => $row['tenant_id']], false) }}" data-turbo-frame="property-main" class="text-indigo-700 hover:underline">{{ $row['tenant_name'] ?? '—' }}</a>
                            @else
                                {{ $row['tenant_name'] ?? '—' }}
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums text-slate-600">{{ $row['account_number'] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row['property_name'] ?? '—' }} / {{ $row['unit_label'] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row['type_label'] ?? '—' }}</td>
                        <td class="px-3 py-2 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['amount'] ?? 0)) }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('property.leases.show', ['lease' => $row['lease_id']], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-600 hover:underline">Lease</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No standing extras on active leases. Add them on the lease (utility expenses) or import the billing schedule.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-property.responsive.table-wrapper>
    @if ($standingCharges instanceof \Illuminate\Contracts\Pagination\Paginator && $standingCharges->hasPages())
        <div class="pt-2">
            {{ $standingCharges->links() }}
        </div>
    @endif
</div>
