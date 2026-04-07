<x-property-layout>
    <x-slot name="header">Dashboard</x-slot>

    <x-property.page
        title="Dashboard"
        subtitle="Portfolio snapshot — counts, cash movement, maintenance intake, and year-to-date billing vs collections ({{ $chartYear }})."
    >
        <div class="mb-6 rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm">
            <p class="text-lg font-semibold text-slate-900">Quick start checklist</p>
            <p class="mt-1 text-sm text-slate-600">If you’re new here: setup portfolio → onboard tenant → allocate unit → bill rent → collect payment.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.properties.list') }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Properties
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.properties.units') }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Units
                    <i class="fa-solid fa-door-open" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.tenants.directory') }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Tenants
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.tenants.leases') }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Lease (allocate)
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.invoices') }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Invoices
                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.payments') }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Payments
                    <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div
            id="property-dashboard-charts"
            class="hidden"
            data-year="{{ $chartYear }}"
            data-labels='@json($chartLabels)'
            data-invoices='@json($chartInvoices)'
            data-payments='@json($chartPayments)'
        ></div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach ($kpis as $kpi)
                <div class="rounded-xl overflow-hidden border border-slate-200/90 dark:border-slate-600/80 bg-white dark:bg-gray-800/90 shadow-sm hover:shadow-md transition-shadow">
                    <div class="h-1 {{ $kpi['bar'] }}"></div>
                    <div class="p-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white leading-tight">{{ $kpi['value'] }}</p>
                            <p class="text-sm font-medium text-slate-600 dark:text-slate-400 mt-1">{{ $kpi['label'] }}</p>
                        </div>
                        <div class="shrink-0 w-11 h-11 rounded-xl bg-slate-100 dark:bg-slate-700/80 flex items-center justify-center text-slate-500 dark:text-slate-300">
                            <i class="fa-solid {{ $kpi['icon'] }} text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <a
                        href="{{ route($kpi['route']) }}"
                        class="flex items-center justify-center gap-2 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 hover:text-emerald-800 dark:hover:text-emerald-300 transition-colors"
                    >
                        More info
                        <i class="fa-solid fa-arrow-right text-sm" aria-hidden="true"></i>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                        Monthly invoices issued
                    </h2>
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $chartYear }}</span>
                </div>
                <div class="h-64 w-full">
                    <canvas id="dashboard-chart-invoices" aria-label="Invoices by month chart"></canvas>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-money-bill-transfer text-teal-600 dark:text-teal-400" aria-hidden="true"></i>
                        Monthly payments received
                    </h2>
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $chartYear }}</span>
                </div>
                <div class="h-64 w-full">
                    <canvas id="dashboard-chart-payments" aria-label="Payments by month chart"></canvas>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-heart-pulse text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                    System health
                </h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Mail config</p>
                    <p class="mt-1 text-sm">
                        @if ($mailConfigured)
                            <span class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400"><i class="fa-solid fa-circle-check"></i> OK</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-rose-700 dark:text-rose-400"><i class="fa-solid fa-triangle-exclamation"></i> Incomplete</span>
                        @endif
                    </p>
                    @if (! $mailConfigured && ($lastArrearsError ?? '') !== '')
                        <p class="mt-1 text-xs text-rose-700 dark:text-rose-400">Last error: {{ \Illuminate\Support\Str::limit($lastArrearsError, 80) }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">SMS wallet</p>
                    <p class="mt-1 text-sm text-slate-800 dark:text-slate-200">{{ $smsWalletBalance }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @if (!empty($smsProvider['ok']) && isset($smsProvider['balance']))
                            Provider balance: <span class="font-semibold">{{ number_format((float) $smsProvider['balance'], 2) }}</span>
                        @elseif (!empty($smsProvider['error']))
                            <span class="text-amber-700 dark:text-amber-400">Provider: {{ \Illuminate\Support\Str::limit($smsProvider['error'], 80) }}</span>
                        @else
                            Check provider balance if using provider/both mode.
                        @endif
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Arrears activity (today)</p>
                    <p class="mt-1 text-sm"><span class="font-semibold">{{ $remindersSentToday }}</span> sent · <span class="font-semibold">{{ $remindersFailedToday }}</span> failed</p>
                    <a href="{{ route('property.revenue.arrears') }}" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:underline">Open arrears</a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-slate-500" aria-hidden="true"></i>
                        Recent arrears reminders (today: sent {{ $remindersSentToday }}, failed {{ $remindersFailedToday }})
                    </h2>
                    <a href="{{ route('property.communications.messages') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Messages</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">When</th>
                                <th class="px-4 py-3">Channel</th>
                                <th class="px-4 py-3">To</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse (($recentArrearsReminders ?? []) as $m)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300 whitespace-nowrap text-xs">{{ $m['when'] }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $m['channel'] }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $m['to'] }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $m['status'] }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($m['error'], 60) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No arrears reminders logged yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-file-contract text-slate-500" aria-hidden="true"></i>
                        Lease activations (this month)
                    </h2>
                    <a href="{{ route('property.tenants.leases') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Leases</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Lease #</th>
                                <th class="px-4 py-3">Tenant</th>
                                <th class="px-4 py-3">Unit</th>
                                <th class="px-4 py-3">Start</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse (($recentLeaseActivations ?? []) as $l)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">#{{ $l['id'] }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $l['tenant'] }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $l['unit'] }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300 whitespace-nowrap text-xs">{{ $l['start'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No activations this month.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-flag text-slate-500" aria-hidden="true"></i>
                    Takeover checklist · Occupied but no active lease
                </h2>
                <a href="{{ route('property.properties.units') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Unit status</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Property</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3 w-32">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse (($occupiedNoLease ?? []) as $u)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $u['property'] }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $u['unit'] }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ $u['action_url'] }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 text-white text-xs font-semibold px-3 py-1.5 hover:bg-emerald-700 transition-colors">Add lease</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">All occupied units have active leases — great!</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-wrench text-slate-500" aria-hidden="true"></i>
                        Recent maintenance requests
                    </h2>
                    <a href="{{ route('property.maintenance.requests') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Summary</th>
                                <th class="px-4 py-3 whitespace-nowrap">Unit</th>
                                <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                <th class="px-4 py-3 whitespace-nowrap">Status</th>
                                <th class="px-4 py-3 w-24"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse ($recentRequests as $row)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300 max-w-xs">{{ $row['summary'] }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $row['unit'] }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap tabular-nums">{{ $row['reported'] }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $row['status'] }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ $row['url'] }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 text-white text-xs font-semibold px-3 py-1.5 hover:bg-emerald-700 transition-colors">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No requests yet — log one from Maintenance.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-slate-500" aria-hidden="true"></i>
                        Recent payments
                    </h2>
                    <a href="{{ route('property.revenue.payments') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Ref</th>
                                <th class="px-4 py-3">Tenant</th>
                                <th class="px-4 py-3 whitespace-nowrap">Amount</th>
                                <th class="px-4 py-3 whitespace-nowrap">Channel</th>
                                <th class="px-4 py-3 whitespace-nowrap">Received</th>
                                <th class="px-4 py-3 w-24"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse ($recentPayments as $row)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ $row['ref'] }}</td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $row['tenant'] }}</td>
                                    <td class="px-4 py-3 font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row['amount'] }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $row['channel'] }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap text-xs">{{ $row['date'] }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ $row['url'] }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 text-white text-xs font-semibold px-3 py-1.5 hover:bg-emerald-700 transition-colors">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No payments yet — record from Revenue → Payments.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-tie text-slate-500" aria-hidden="true"></i>
                    Recent landlord links
                </h2>
                <a href="{{ route('property.landlords.index') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Property</th>
                            <th class="px-4 py-3">Landlord</th>
                            <th class="px-4 py-3 whitespace-nowrap">Ownership</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse (($recentLandlordLinks ?? []) as $row)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $row['property'] }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $row['landlord'] }}</td>
                                <td class="px-4 py-3 font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row['ownership'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No landlord links yet — open Manage Properties to link a landlord.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-amber-200/80 dark:border-amber-900/40 bg-amber-50/50 dark:bg-amber-950/20 p-5">
                <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
                    Attention
                </h2>
                <ul class="mt-3 space-y-2 text-sm text-amber-950/90 dark:text-amber-100/90">
                    <li class="flex justify-between gap-2"><span>Overdue invoices</span><span class="font-semibold tabular-nums">{{ $overdueCount }}</span></li>
                    <li class="flex justify-between gap-2"><span>Active work orders</span><span class="font-semibold tabular-nums">{{ $jobsActive }}</span></li>
                    <li class="flex justify-between gap-2"><span>Landlord users</span><span class="font-semibold tabular-nums">{{ $landlords }}</span></li>
                    <li class="flex justify-between gap-2"><span>Linked landlords</span><span class="font-semibold tabular-nums">{{ $linkedLandlords ?? 0 }}</span></li>
                    <li class="flex justify-between gap-2"><span>Properties with no landlord</span><span class="font-semibold tabular-nums">{{ $propertiesWithoutLandlord ?? 0 }}</span></li>
                    <li class="flex justify-between gap-2"><span>Maintenance spend (MTD)</span><span class="font-semibold tabular-nums">{{ $maintenanceMtd }}</span></li>
                </ul>
                <a href="{{ route('property.landlords.index') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-amber-800 dark:text-amber-300 hover:underline">
                    Open landlord workspace
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 p-5 lg:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Arrears buckets (open balance)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">By days past due — ties to Revenue → Arrears.</p>
                <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 py-3">
                        <dt class="text-[10px] uppercase font-semibold text-slate-500 dark:text-slate-400">7d</dt>
                        <dd class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white mt-1">{{ $arrears7 }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 py-3">
                        <dt class="text-[10px] uppercase font-semibold text-slate-500 dark:text-slate-400">14d</dt>
                        <dd class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white mt-1">{{ $arrears14 }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 py-3">
                        <dt class="text-[10px] uppercase font-semibold text-slate-500 dark:text-slate-400">30+d</dt>
                        <dd class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white mt-1">{{ $arrears30 }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('property.revenue.arrears') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        Open arrears workspace
                    </a>
                    <span class="text-slate-300 dark:text-slate-600">|</span>
                    <span class="text-sm text-slate-600 dark:text-slate-400">Occupancy: <strong class="text-slate-900 dark:text-white">{{ $occupancyDisplay }}</strong></span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <a href="{{ route('property.revenue.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-2.5 text-sm font-semibold shadow-sm hover:bg-emerald-700 transition-colors">
                <i class="fa-solid fa-coins" aria-hidden="true"></i>
                Revenue
            </a>
            <a href="{{ route('property.settings.roles') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80 transition-colors">
                <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                Property users
            </a>
            <a href="{{ route('property.tenants.directory') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80 transition-colors">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Tenants
            </a>
            <a href="{{ route('property.performance.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80 transition-colors">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                Analytics
            </a>
        </div>
    </x-property.page>
</x-property-layout>
