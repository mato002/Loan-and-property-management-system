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
                <div class="h-48 sm:h-64 w-full min-w-0 overflow-hidden">
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
                <div class="h-48 sm:h-64 w-full min-w-0 overflow-hidden">
                    <canvas id="dashboard-chart-payments" aria-label="Payments by month chart"></canvas>
                </div>
            </div>
        </x-property.responsive.compact-card-grid>

        @php
            $healthStats = [
                [
                    'label' => 'Mail config',
                    'value' => $mailConfigured
                        ? 'OK'
                        : 'Incomplete',
                    'hint' => (! $mailConfigured && ($lastArrearsError ?? '') !== '')
                        ? 'Last error: '.\Illuminate\Support\Str::limit($lastArrearsError, 60)
                        : ($mailConfigured ? 'SMTP ready' : 'Check settings'),
                    'emphasis' => ! $mailConfigured,
                    'tone' => $mailConfigured ? 'emerald' : 'rose',
                ],
                [
                    'label' => 'SMS wallet',
                    'value' => $smsWalletBalance,
                    'hint' => ! empty($smsProvider['ok']) && isset($smsProvider['balance'])
                        ? 'Provider: '.number_format((float) $smsProvider['balance'], 2)
                        : (! empty($smsProvider['error'])
                            ? \Illuminate\Support\Str::limit($smsProvider['error'], 48)
                            : 'Wallet / provider mode'),
                ],
                [
                    'label' => 'Arrears today',
                    'value' => $remindersSentToday.' sent',
                    'hint' => $remindersFailedToday.' need action ┬╖ tap list below',
                ],
            ];
        @endphp
        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 shadow-sm">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-heart-pulse text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                    System health
                </h2>
                <a href="{{ route('property.revenue.arrears') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline shrink-0">Arrears</a>
            </div>
            <x-property.responsive.stat-card-grid :stats="$healthStats" />
        </div>

        @include('property.agent.communications.partials.sms_wallet_dashboard_panel')

        <x-property.responsive.compact-card-grid class="gap-3 sm:gap-4">
            <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm !p-0">
                <div class="px-3 sm:px-4 md:px-5 py-2.5 sm:py-3 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-slate-500" aria-hidden="true"></i>
                        <span class="leading-snug">Recent rent reminders (today: sent {{ $remindersSentToday }}, need action {{ $remindersFailedToday }})</span>
                    </h2>
                    <a href="{{ route('property.communications.messages') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline shrink-0">Messages</a>
                </div>
                <div class="property-table-scroll -mx-3 px-3 sm:mx-0 sm:px-0 overflow-x-auto">
                    <table class="min-w-[640px] w-full text-sm">
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
            <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm !p-0">
                <div class="px-3 sm:px-4 md:px-5 py-2.5 sm:py-3 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-file-contract text-slate-500" aria-hidden="true"></i>
                        Lease activations (this month)
                    </h2>
                    <a href="{{ route('property.tenants.leases') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline shrink-0">Leases</a>
                </div>
                <div class="property-table-scroll -mx-3 px-3 sm:mx-0 sm:px-0 overflow-x-auto">
                    <table class="min-w-[520px] w-full text-sm">
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
        </x-property.responsive.compact-card-grid>

        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm !p-0">
            <div class="px-3 sm:px-4 md:px-5 py-2.5 sm:py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-flag text-slate-500" aria-hidden="true"></i>
                    Takeover checklist ┬╖ Occupied but no active lease
                </h2>
                <a href="{{ route('property.properties.units') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Unit status</a>
            </div>
            <div class="property-table-scroll -mx-3 px-3 sm:mx-0 sm:px-0 overflow-x-auto">
                <table class="min-w-[480px] w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Property</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3 w-32">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse (($occupiedNoLease ?? []) as $u)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 property-row-alert-attention">
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $u['property'] }}</td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $u['unit'] }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ $u['action_url'] }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 text-white text-xs font-semibold px-3 py-1.5 hover:bg-emerald-700 transition-colors">Add lease</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">All occupied units have active leases ΓÇö great!</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-property.responsive.compact-card-grid class="gap-3 sm:gap-4">
            <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm !p-0">
                <div class="px-3 sm:px-4 md:px-5 py-2.5 sm:py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                    <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-wrench text-slate-500" aria-hidden="true"></i>
                        Recent maintenance requests
                    </h2>
                    <a href="{{ route('property.maintenance.requests') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">View all</a>
                </div>
                <div class="property-table-scroll -mx-3 px-3 sm:mx-0 sm:px-0 overflow-x-auto">
                    <table class="min-w-[520px] w-full text-sm">
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
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No requests yet ΓÇö log one from Maintenance.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm !p-0">
                <div class="px-3 sm:px-4 md:px-5 py-2.5 sm:py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                    <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-slate-500" aria-hidden="true"></i>
                        Recent payments
                    </h2>
                    <a href="{{ route('property.revenue.payments') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">View all</a>
                </div>
                <div class="property-table-scroll -mx-3 px-3 sm:mx-0 sm:px-0 overflow-x-auto">
                    <table class="min-w-[560px] w-full text-sm">
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
                                    <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No payments yet ΓÇö record from Revenue ΓåÆ Payments.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-property.responsive.compact-card-grid>

        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 overflow-hidden shadow-sm !p-0">
            <div class="px-3 sm:px-4 md:px-5 py-2.5 sm:py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
                <h2 class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-tie text-slate-500" aria-hidden="true"></i>
                    Recent landlord links
                </h2>
                <a href="{{ route('property.landlords.index') }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">View all</a>
            </div>
            <div class="property-table-scroll -mx-3 px-3 sm:mx-0 sm:px-0 overflow-x-auto">
                <table class="min-w-[480px] w-full text-sm">
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
                                <td colspan="3" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No landlord links yet ΓÇö open Manage Properties to link a landlord.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-property.responsive.compact-card-grid :lg-cols="3" class="gap-3 sm:gap-4">
            <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-amber-200/80 dark:border-amber-900/40 bg-amber-50/50 dark:bg-amber-950/20">
                <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
                    Attention
                </h2>
                <ul class="mt-2 sm:mt-3 space-y-1.5 sm:space-y-2 text-xs sm:text-sm text-amber-950/90 dark:text-amber-100/90">
                    <li class="flex justify-between gap-2"><span>Overdue invoices</span><span class="font-semibold tabular-nums">{{ $overdueCount }}</span></li>
                    <li class="flex justify-between gap-2"><span>Active work orders</span><span class="font-semibold tabular-nums">{{ $jobsActive }}</span></li>
                    <li class="flex justify-between gap-2"><span>Landlord accounts</span><span class="font-semibold tabular-nums">{{ $landlords }}</span></li>
                    <li class="flex justify-between gap-2"><span>On a property</span><span class="font-semibold tabular-nums">{{ $linkedLandlords ?? 0 }}</span></li>
                    <li class="flex justify-between gap-2"><span>Not linked yet</span><span class="font-semibold tabular-nums">{{ $unlinkedLandlordUsers ?? 0 }}</span></li>
                    <li class="flex justify-between gap-2"><span>Properties with no landlord</span><span class="font-semibold tabular-nums">{{ $propertiesWithoutLandlord ?? 0 }}</span></li>
                    <li class="flex justify-between gap-2"><span>Maintenance spend (MTD)</span><span class="font-semibold tabular-nums">{{ $maintenanceMtd }}</span></li>
                </ul>
                <a href="{{ route('property.landlords.index') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-amber-800 dark:text-amber-300 hover:underline">
                    Open landlord workspace
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 lg:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Arrears buckets (open balance)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">By days past due ΓÇö ties to Revenue ΓåÆ Arrears.</p>
                <x-property.responsive.stat-card-grid
                    class="mt-3"
                    dense
                    :stats="[
                        ['label' => '7 days', 'value' => (string) $arrears7, 'hint' => 'Open balance'],
                        ['label' => '14 days', 'value' => (string) $arrears14, 'hint' => 'Open balance'],
                        ['label' => '30+ days', 'value' => (string) $arrears30, 'hint' => 'Open balance', 'emphasis' => true, 'tone' => 'rose'],
                    ]"
                />
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('property.revenue.arrears') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        Open arrears workspace
                    </a>
                    <span class="text-slate-300 dark:text-slate-600">|</span>
                    <span class="text-sm text-slate-600 dark:text-slate-400">Occupancy: <strong class="text-slate-900 dark:text-white">{{ $occupancyDisplay }}</strong></span>
                </div>
            </div>
        </x-property.responsive.compact-card-grid>

        <x-property.responsive.quick-action-grid class="pt-1">
            <a href="{{ route('property.revenue.index') }}" data-turbo-frame="property-main" class="quick-action-btn bg-emerald-600 text-white hover:bg-emerald-700">
                <i class="fa-solid fa-coins" aria-hidden="true"></i>
                Collections
            </a>
            @if ((auth()->user()->is_super_admin ?? false) === true)
                <a href="{{ route('property.settings.roles') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80">
                    <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                    Users
                </a>
            @endif
            <a href="{{ route('property.tenants.directory') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Tenants
            </a>
            <a href="{{ route('property.performance.index') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                Reports
            </a>
        </x-property.responsive.quick-action-grid>