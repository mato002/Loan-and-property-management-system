@php
    $fmtKes = fn (float|int $n) => 'KES '.number_format((float) $n, 0);
    $fmtPct = fn (float|int $n) => number_format((float) $n, 1).'%';

    $today = now();
    $isAtRisk = ($liquidityFloorStatus ?? 'HEALTHY') === 'AT RISK';

    $mixStops = [];
    $running = 0.0;
    foreach (($collectionMix ?? []) as $segment) {
        $p = (float) ($segment['percentage'] ?? 0);
        $start = $running;
        $end = $running + $p;
        $color = $segment['color'] ?? '#0f766e';
        $mixStops[] = $color.' '.number_format($start, 2).'% '.number_format($end, 2).'%';
        $running = $end;
    }
    $mixGradient = empty($mixStops) ? '#e2e8f0 0% 100%' : implode(', ', $mixStops);

    $cashflowRoute = \Illuminate\Support\Facades\Route::has('loan.accounting.cashflow') ? route('loan.accounting.cashflow') : '#';
    $lendingCapacityRoute = '#';
@endphp

<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle" :show-quick-links="false">
        <x-slot name="actions">
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold">
                    {{ $today->format('l, d M Y') }}
                </span>
                <form method="get" action="{{ route('loan.book.collections_reports') }}" class="inline-flex">
                    <label class="sr-only" for="branch_id">Branch selector</label>
                    <select id="branch_id" name="branch_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 bg-white text-xs font-medium text-slate-700">
                        <option value="0">All branches</option>
                        @foreach (($branchOptions ?? []) as $branch)
                            <option value="{{ $branch->id }}" @selected((int) ($selectedBranchId ?? 0) === (int) $branch->id)>
                                {{ $branch->name }}@if($branch->region) - {{ $branch->region->name }}@endif
                            </option>
                        @endforeach
                    </select>
                </form>
                <a href="#" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 font-semibold text-blue-700 hover:bg-blue-100 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12l-4-4m4 4l4-4M4 20h16" />
                    </svg>
                    Export Report
                </a>
            </div>
        </x-slot>

        <div x-data="{ activeTab: 'overview' }" class="space-y-5 bg-slate-50 p-3 sm:p-4 rounded-xl">
            <section class="rounded-xl border border-slate-200 bg-white px-4 pt-2 shadow-sm">
                <nav class="overflow-x-auto">
                    <div class="flex min-w-max items-center gap-5 border-b border-slate-200">
                        <button type="button" @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Overview</button>
                        <button type="button" @click="activeTab = 'collection_sheet'" :class="activeTab === 'collection_sheet' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Collection Sheet</button>
                        <button type="button" @click="activeTab = 'upcoming'" :class="activeTab === 'upcoming' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Upcoming</button>
                        <button type="button" @click="activeTab = 'missed_pending'" :class="activeTab === 'missed_pending' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Missed/Pending</button>
                        <button type="button" @click="activeTab = 'rates'" :class="activeTab === 'rates' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Rates</button>
                        <button type="button" @click="activeTab = 'reports'" :class="activeTab === 'reports' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Reports</button>
                        <button type="button" @click="activeTab = 'agents'" :class="activeTab === 'agents' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Agents</button>
                        <button type="button" @click="activeTab = 'cashflow'" :class="activeTab === 'cashflow' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Cashflow</button>
                        <button type="button" @click="activeTab = 'inflow_forecast'" :class="activeTab === 'inflow_forecast' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Inflow Forecast</button>
                        <button type="button" @click="activeTab = 'lending_capacity'" :class="activeTab === 'lending_capacity' ? 'border-[#0f766e] text-[#0f766e]' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-1 py-3 text-sm font-semibold whitespace-nowrap">Lending Capacity</button>
                    </div>
                </nav>
            </section>

            <section x-show="activeTab === 'overview'" x-cloak class="space-y-5">
                <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Collection Efficiency Ratio</p>
                        <span class="text-xs font-semibold text-emerald-700">+2.1% vs yesterday</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-[#0f3d3e]">{{ $fmtPct($metrics['collection_efficiency'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">({{ $fmtKes($metrics['total_collected'] ?? 0) }} / {{ $fmtKes($metrics['total_expected'] ?? 0) }})</p>
                    <svg viewBox="0 0 120 30" class="mt-3 h-8 w-full text-[#0f766e]" fill="none">
                        <polyline points="2,24 18,22 34,20 50,18 66,16 82,14 98,12 118,9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current Yield (Yesterday)</p>
                        <span class="text-xs font-semibold text-emerald-700">+3.4%</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $fmtKes($metrics['current_yield'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Collected against yesterday due bucket</p>
                </article>

                <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-orange-600">Arrears Recovery Yield</p>
                        <span class="text-xs font-semibold text-orange-600">Priority</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-orange-700">{{ $fmtKes($metrics['arrears_recovery_yield'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Recovered from already late loans</p>
                </article>

                <article class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Pre-payment Yield</p>
                        <span class="text-xs font-semibold text-emerald-700">Healthy</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $fmtKes($metrics['prepayment_yield'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Collected ahead of due dates</p>
                </article>

                <article class="rounded-xl border border-rose-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Yield Gap</p>
                        <span class="text-xs font-semibold text-rose-600">Behind target</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-rose-700">{{ $fmtKes($metrics['yield_gap'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Expected - actual collected</p>
                </article>
                </section>

                <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available Liquidity Today</p>
                    <p class="mt-2 text-2xl font-bold text-[#0f3d3e]">{{ $fmtKes($metrics['available_liquidity_today'] ?? 0) }}</p>
                    <a href="{{ $cashflowRoute }}" class="mt-2 inline-block text-xs font-semibold text-blue-700 hover:underline">View Cashflow Report →</a>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">7 Day Expected Inflow</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $fmtKes($metrics['expected_inflow_7_days'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $dateWindowLabel ?? '' }}</p>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">14 Day Expected Inflow</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $fmtKes($metrics['expected_inflow_14_days'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Forward inflow projection</p>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">30 Day Expected Inflow</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $fmtKes($metrics['expected_inflow_30_days'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Medium-term cash outlook</p>
                </article>

                <article class="rounded-xl border p-4 shadow-sm {{ $isAtRisk ? 'border-orange-300 bg-orange-50/70' : 'border-emerald-200 bg-emerald-50/60' }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide {{ $isAtRisk ? 'text-orange-700' : 'text-emerald-700' }}">Liquidity Floor Status</p>
                        <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold {{ $isAtRisk ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $liquidityFloorStatus }}</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold {{ $isAtRisk ? 'text-orange-700' : 'text-emerald-700' }}">{{ $fmtKes($metrics['liquidity_floor_amount'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-slate-600">Projected breach in {{ (int) ($metrics['projected_liquidity_breach_days'] ?? 0) }} days</p>
                    @if ($isAtRisk)
                        <p class="mt-1 text-xs font-semibold text-rose-700">Recommend: Lending Brake</p>
                    @endif
                    <a href="#" class="mt-2 inline-block text-xs font-semibold text-blue-700 hover:underline">View Details →</a>
                </article>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-[#0f3d3e]">7 / 14 / 30 Day Inflow Waterfall</h3>
                    <span class="text-xs text-slate-500">Expected vs collectible cash windows</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach (($forecastWindows ?? []) as $window)
                        @php
                            $rate = (float) ($window['collection_rate'] ?? 0);
                            $rateClass = $rate < 75 ? 'text-orange-600' : 'text-emerald-700';
                            $barClass = $rate < 75 ? 'bg-orange-500' : 'bg-emerald-500';
                        @endphp
                        <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                            <h4 class="text-sm font-semibold text-slate-800">{{ $window['window'] }}</h4>
                            <dl class="mt-2 space-y-1 text-xs">
                                <div class="flex items-center justify-between"><dt class="text-slate-500">Expected Inflow</dt><dd class="font-semibold text-slate-800">{{ $fmtKes($window['expected_inflow']) }}</dd></div>
                                <div class="flex items-center justify-between"><dt class="text-slate-500">Expected Collected</dt><dd class="font-semibold text-emerald-700">{{ $fmtKes($window['expected_collected']) }}</dd></div>
                                <div class="flex items-center justify-between"><dt class="text-slate-500">Gap</dt><dd class="font-semibold text-rose-700">{{ $fmtKes($window['gap']) }}</dd></div>
                                <div class="flex items-center justify-between"><dt class="text-slate-500">Collection Rate</dt><dd class="font-semibold {{ $rateClass }}">{{ $fmtPct($rate) }}</dd></div>
                            </dl>
                            <div class="mt-2 h-2.5 rounded-full bg-slate-200 overflow-hidden">
                                <div class="h-full {{ $barClass }}" style="width: {{ max(0, min(100, $rate)) }}%"></div>
                            </div>
                        </article>
                    @endforeach
                </div>
                </section>

                <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <article class="xl:col-span-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-base font-semibold text-[#0f3d3e]">Collection Mix</h3>
                    <p class="text-xs text-slate-500">Segmentation by due and arrears buckets</p>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                        <div class="mx-auto h-44 w-44 rounded-full" style="background: conic-gradient({{ $mixGradient }});">
                            <div class="m-8 flex h-28 w-28 items-center justify-center rounded-full bg-white text-center">
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Total Mix</p>
                                    <p class="text-sm font-bold text-slate-800">{{ $fmtKes($collectionMixTotal ?? 0) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            @foreach (($collectionMix ?? []) as $segment)
                                <div class="rounded-lg border border-slate-200 p-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                            <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $segment['color'] }}"></span>
                                            {{ $segment['label'] }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-600">{{ $fmtPct($segment['percentage']) }}</span>
                                    </div>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $fmtKes($segment['amount']) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-base font-semibold text-[#0f3d3e]">Alerts & Insights</h3>
                    <div class="mt-3 space-y-2.5">
                        @foreach (($alerts ?? []) as $alert)
                            @php
                                $theme = match ($alert['severity']) {
                                    'critical' => ['bg' => 'bg-rose-50', 'icon' => 'text-rose-600', 'title' => 'text-rose-700'],
                                    'warning' => ['bg' => 'bg-orange-50', 'icon' => 'text-orange-600', 'title' => 'text-orange-700'],
                                    'positive' => ['bg' => 'bg-emerald-50', 'icon' => 'text-emerald-600', 'title' => 'text-emerald-700'],
                                    default => ['bg' => 'bg-blue-50', 'icon' => 'text-blue-600', 'title' => 'text-blue-700'],
                                };
                            @endphp
                            <div class="rounded-lg border border-slate-200 p-2.5 {{ $theme['bg'] }}">
                                <div class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-4 w-4 {{ $theme['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" />
                                    </svg>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold {{ $theme['title'] }}">{{ $alert['title'] }}</p>
                                        <p class="text-xs text-slate-600">{{ $alert['description'] }}</p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $alert['time_ago'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-[#0f3d3e]">Daily Collection Rates</h3>
                    <p class="text-xs text-slate-500">This week snapshot</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[860px] w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Date</th>
                                <th class="px-3 py-2 font-semibold text-right">Expected (KES)</th>
                                <th class="px-3 py-2 font-semibold text-right">Collected (KES)</th>
                                <th class="px-3 py-2 font-semibold text-right">Collection Rate</th>
                                <th class="px-3 py-2 font-semibold text-right">Yield Gap (KES)</th>
                                <th class="px-3 py-2 font-semibold">Trend</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach (($dailyCollectionRates ?? []) as $row)
                                @php
                                    $rate = (float) ($row['collection_rate'] ?? 0);
                                    $stroke = $rate >= 75 ? '#16a34a' : '#ef4444';
                                    $points = '';
                                    $trendPoints = $row['trend'] ?? [];
                                    $count = count($trendPoints);
                                    foreach ($trendPoints as $index => $value) {
                                        $x = $count > 1 ? ($index / ($count - 1)) * 116 + 2 : 2;
                                        $y = 22 - ((float) $value / 100) * 18;
                                        $points .= number_format($x, 2).','.number_format($y, 2).' ';
                                    }
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-800">{{ $row['date'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $fmtKes($row['expected']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $fmtKes($row['collected']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold {{ $rate >= 75 ? 'text-emerald-700' : 'text-orange-700' }}">{{ $fmtPct($rate) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold text-rose-700">{{ $fmtKes($row['yield_gap']) }}</td>
                                    <td class="px-3 py-2">
                                        <svg viewBox="0 0 120 24" class="h-6 w-28" fill="none" aria-label="Collection trend sparkline">
                                            <polyline points="{{ trim($points) }}" stroke="{{ $stroke }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                                        </svg>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                </section>
            </section>

            <section x-show="activeTab === 'collection_sheet'" x-cloak class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0f3d3e]">Collection Sheet (Today)</h3>
                <p class="mt-1 text-sm text-slate-600">Daily receipting battle plan and posting queue.</p>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-700">Use this workspace to capture today’s collections, assign field follow-ups, and post all receipting lines.</p>
                    <a href="{{ route('loan.book.collection_sheet.index') }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open Collection Sheet →</a>
                </div>
            </section>

            <section x-show="activeTab === 'upcoming'" x-cloak class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0f3d3e]">Upcoming Collections</h3>
                <p class="mt-1 text-sm text-slate-600">Expected inflows due soon for proactive collection planning.</p>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-700">Focus this queue on clients due in the next 7 days and prepare daily routing for collectors.</p>
                    <a href="{{ route('loan.book.collection_sheet.index', ['from' => now()->addDay()->toDateString(), 'to' => now()->addDays(7)->toDateString()]) }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open Upcoming Collections →</a>
                </div>
            </section>

            <section x-show="activeTab === 'missed_pending'" x-cloak class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0f3d3e]">Missed / Pending Collections</h3>
                <p class="mt-1 text-sm text-slate-600">Unposted items and missed collection actions needing immediate follow-up.</p>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-700">Clear unposted transactions and unresolved entries to protect daily collection accuracy.</p>
                    <a href="{{ route('loan.payments.unposted') }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open Missed/Pending Queue →</a>
                </div>
            </section>

            <section x-show="activeTab === 'rates'" x-cloak class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0f3d3e]">Collection Rates</h3>
                <p class="mt-1 text-sm text-slate-600">Track branch collection targets against actual collections.</p>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-700">Review target attainment, branch gaps, and trend lines for tactical interventions.</p>
                    <a href="{{ route('loan.book.collection_rates.index') }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open Collection Rates →</a>
                </div>
            </section>

            <section x-show="activeTab === 'reports'" x-cloak class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0f3d3e]">Collection Reports</h3>
                <p class="mt-1 text-sm text-slate-600">Detailed collection reporting by branch and client portfolio.</p>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-700">Use this view for audit context, trend validation, and branch-level reporting exports.</p>
                    <a href="{{ route('loan.book.collection_reports') }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open Collection Reports →</a>
                </div>
            </section>

            <section x-show="activeTab === 'agents'" x-cloak class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0f3d3e]">Agents Performance</h3>
                <p class="mt-1 text-sm text-slate-600">Collections productivity and outcomes by field agent.</p>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-700">Top agent today: <span class="font-semibold">{{ $agentPerformanceSummary['top_agent'] }}</span> with <span class="font-semibold">{{ $fmtKes($agentPerformanceSummary['top_agent_collected']) }}</span>.</p>
                    <a href="{{ route('loan.book.collection_agents.index') }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open Agents Performance →</a>
                </div>
            </section>

            <section x-show="activeTab === 'cashflow'" x-cloak class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0f3d3e]">Cashflow Report</h3>
                <p class="mt-1 text-sm text-slate-600">Liquidity movement, runway, and balance visibility.</p>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-700">Link collections timing to available liquidity and near-term operating needs.</p>
                    <a href="{{ $cashflowRoute }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open Cashflow Report →</a>
                </div>
            </section>

            <section x-show="activeTab === 'inflow_forecast'" x-cloak class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-[#0f3d3e]">Inflow Forecast</h3>
                    <p class="mt-1 text-sm text-slate-600">7/14/30-day inflow expectation and gap management.</p>
                </div>
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-[#0f3d3e]">7 / 14 / 30 Day Inflow Waterfall</h3>
                        <span class="text-xs text-slate-500">Expected vs collectible cash windows</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        @foreach (($forecastWindows ?? []) as $window)
                            @php
                                $rate = (float) ($window['collection_rate'] ?? 0);
                                $rateClass = $rate < 75 ? 'text-orange-600' : 'text-emerald-700';
                                $barClass = $rate < 75 ? 'bg-orange-500' : 'bg-emerald-500';
                            @endphp
                            <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                <h4 class="text-sm font-semibold text-slate-800">{{ $window['window'] }}</h4>
                                <dl class="mt-2 space-y-1 text-xs">
                                    <div class="flex items-center justify-between"><dt class="text-slate-500">Expected Inflow</dt><dd class="font-semibold text-slate-800">{{ $fmtKes($window['expected_inflow']) }}</dd></div>
                                    <div class="flex items-center justify-between"><dt class="text-slate-500">Expected Collected</dt><dd class="font-semibold text-emerald-700">{{ $fmtKes($window['expected_collected']) }}</dd></div>
                                    <div class="flex items-center justify-between"><dt class="text-slate-500">Gap</dt><dd class="font-semibold text-rose-700">{{ $fmtKes($window['gap']) }}</dd></div>
                                    <div class="flex items-center justify-between"><dt class="text-slate-500">Collection Rate</dt><dd class="font-semibold {{ $rateClass }}">{{ $fmtPct($rate) }}</dd></div>
                                </dl>
                                <div class="mt-2 h-2.5 rounded-full bg-slate-200 overflow-hidden">
                                    <div class="h-full {{ $barClass }}" style="width: {{ max(0, min(100, $rate)) }}%"></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            </section>

            <section x-show="activeTab === 'lending_capacity'" x-cloak class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0f3d3e]">Lending Capacity</h3>
                <p class="mt-1 text-sm text-slate-600">Safe disbursement headroom based on liquidity floor and inflow confidence.</p>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <article class="rounded-xl border p-4 {{ $isAtRisk ? 'border-orange-300 bg-orange-50/70' : 'border-emerald-200 bg-emerald-50/60' }}">
                        <p class="text-xs font-semibold uppercase tracking-wide {{ $isAtRisk ? 'text-orange-700' : 'text-emerald-700' }}">Liquidity Floor Status</p>
                        <p class="mt-2 text-2xl font-bold {{ $isAtRisk ? 'text-orange-700' : 'text-emerald-700' }}">{{ $liquidityFloorStatus }}</p>
                        <p class="mt-1 text-xs text-slate-600">Floor: {{ $fmtKes($metrics['liquidity_floor_amount'] ?? 0) }}</p>
                        <p class="mt-1 text-xs text-slate-600">Projected breach date: {{ $projectedLiquidityBreachDate ?? 'N/A' }}</p>
                        @if ($isAtRisk)
                            <p class="mt-2 text-xs font-semibold text-rose-700">Recommendation: Lending Brake</p>
                        @endif
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available Liquidity Today</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $fmtKes($metrics['available_liquidity_today'] ?? 0) }}</p>
                        <p class="mt-1 text-xs text-slate-600">Use cashflow and forecast tabs to validate next lending batch.</p>
                        <a href="{{ $lendingCapacityRoute }}" class="mt-3 inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">View Lending Capacity Details →</a>
                    </article>
                </div>
            </section>
        </div>
    </x-loan.page>
</x-loan-layout>
