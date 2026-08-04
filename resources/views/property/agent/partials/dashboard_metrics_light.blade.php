@if (!empty($financialKpis))
    <x-property.responsive.kpi-card-grid :kpis="$financialKpis" class="mb-3 sm:mb-4" />
@endif

<div
    id="property-dashboard-charts"
    class="hidden"
    data-year="{{ $chartYear }}"
    data-labels='@json($chartLabels)'
    data-invoices='@json($chartInvoices)'
    data-payments='@json($chartPayments)'
></div>

<x-property.responsive.compact-card-grid class="gap-3 sm:gap-4">
    <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 shadow-sm">
        <div class="flex items-center justify-between gap-2 mb-3 sm:mb-4">
            <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                Monthly invoices issued
            </h2>
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $chartYear }}</span>
        </div>
        <div class="h-48 sm:h-56 w-full min-w-0 overflow-hidden">
            <canvas id="dashboard-chart-invoices" aria-label="Invoices by month chart"></canvas>
        </div>
    </div>
    <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 shadow-sm">
        <div class="flex items-center justify-between gap-2 mb-3 sm:mb-4">
            <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-money-bill-transfer text-teal-600 dark:text-teal-400" aria-hidden="true"></i>
                Monthly payments received
            </h2>
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $chartYear }}</span>
        </div>
        <div class="h-48 sm:h-56 w-full min-w-0 overflow-hidden">
            <canvas id="dashboard-chart-payments" aria-label="Payments by month chart"></canvas>
        </div>
    </div>
</x-property.responsive.compact-card-grid>

<x-property.responsive.compact-card-grid class="gap-3 sm:gap-4 mt-3 sm:mt-4">
    <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm !p-0">
        <div class="px-3 sm:px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Recent payments</h2>
            <a href="{{ route('property.revenue.payments') }}" data-turbo-frame="property-main" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">View all</a>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
            @forelse ($recentPayments as $row)
                <li class="flex items-center justify-between gap-3 px-3 sm:px-4 py-2.5">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900 dark:text-white truncate">{{ $row['tenant'] }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row['date'] }}</p>
                    </div>
                    <span class="font-semibold tabular-nums text-slate-900 dark:text-white shrink-0">{{ $row['amount'] }}</span>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No payments yet.</li>
            @endforelse
        </ul>
    </div>
    <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm !p-0">
        <div class="px-3 sm:px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Recent maintenance</h2>
            <a href="{{ route('property.maintenance.requests') }}" data-turbo-frame="property-main" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">View all</a>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
            @forelse ($recentRequests as $row)
                <li class="px-3 sm:px-4 py-2.5">
                    <p class="font-medium text-slate-900 dark:text-white truncate">{{ $row['summary'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $row['unit'] }} · {{ $row['status'] }}</p>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">No requests yet.</li>
            @endforelse
        </ul>
    </div>
</x-property.responsive.compact-card-grid>

<div class="mt-3 sm:mt-4 flex flex-wrap gap-2">
    <a href="{{ route('property.revenue.arrears') }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">
        Arrears workspace
    </a>
    <a href="{{ route('property.performance.index') }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">
        Full reports
    </a>
</div>
