<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        @include('loan.accounting.partials.flash')

        <div class="space-y-4 bg-slate-50/60 p-3 sm:p-4 rounded-xl">
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">Unified System Surveillance &amp; Forensics</h1>
                        <p class="mt-1 text-sm text-slate-600">Real-Time Accounting &bull; Immutable Audit Trail</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>System Integrity: OPTIMIZED
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">
                                <span class="h-2 w-2 rounded-full bg-teal-500"></span>Threat Defense: ENABLED
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">{{ now()->format('l, F j, Y') }} &middot; {{ now()->format('h:i:s A') }} (EAT)</span>
                        <a href="{{ route('loan.dashboard') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Dashboard</a>
                        <a href="{{ route('loan.system.access_logs.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reports</a>
                        <select class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                            <option>Advanced Surveillance</option>
                            <option>Privileged Access</option>
                            <option>Tamper Trace</option>
                        </select>
                        <span class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-teal-700">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                            {{ auth()->user()->name ?? 'User' }}
                        </span>
                    </div>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Global Activity Stream (Live)</p>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Live</span>
                    </div>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $kpis['eventsPerMinute'] ?? 0 }} <span class="text-sm font-semibold text-slate-500">Events/min</span></p>
                    <p class="mt-1 text-xs text-slate-600">{{ $kpis['activeSessions'] ?? 0 }} Active Sessions &middot; {{ $kpis['activeUsersOnline'] ?? 0 }} People Online</p>
                    <p class="mt-1 text-xs text-slate-600">Most active time: {{ $kpis['mostActiveHour'] ?? 'N/A' }} ({{ $kpis['mostActiveHourHits'] ?? 0 }} events)</p>
                    <svg viewBox="0 0 120 28" class="mt-2 h-8 w-full text-emerald-500" fill="none">
                        <path d="M2 20 L18 14 L28 18 L38 11 L52 15 L66 9 L78 13 L92 7 L106 11 L118 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="mt-2 space-y-1 border-t border-slate-100 pt-2 text-[11px] text-slate-600">
                        <p class="font-semibold text-slate-700">Top 5 visited paths (7d)</p>
                        @foreach (($kpis['topVisitedPaths'] ?? collect()) as $pathStat)
                            <p class="truncate"><span class="font-mono">{{ $pathStat->path }}</span> <span class="text-slate-400">({{ (int) $pathStat->hits }})</span></p>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Real-Time Threat Level</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $kpis['interceptedAttempts'] ?? 0 }} <span class="text-sm font-semibold text-slate-500">Intercepted Attempts</span></p>
                    <p class="mt-1 text-xs text-slate-600">{{ $kpis['foreignIpBlocked'] ?? 0 }} Foreign IP blocked &middot; {{ $kpis['failedDistinctPages'] ?? 0 }} Pages with failed access</p>
                    <p class="mt-1 text-xs text-slate-600">Failed pages (7d): {{ $kpis['failedDistinctPages7d'] ?? 0 }} &middot; {{ $kpis['anomaliesDetected'] ?? 0 }} High/Critical today</p>
                    <svg viewBox="0 0 120 28" class="mt-2 h-8 w-full text-rose-500" fill="none">
                        <path d="M2 16 L18 17 L28 13 L38 15 L52 9 L66 12 L78 8 L92 14 L106 10 L118 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Privileged Access Monitor</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $kpis['gatedCriticalEvents'] ?? 0 }} <span class="text-sm font-semibold text-slate-500">Critical Events</span></p>
                    <p class="mt-1 text-xs text-slate-600">COA DNA {{ $kpis['coaDnaModificationCount'] ?? 0 }} &middot; Floor override {{ $kpis['floorOverrideCount'] ?? 0 }} &middot; Reversal {{ $kpis['manualReversalCount'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-600">Imports {{ $kpis['importCount'] ?? 0 }} &middot; Exports {{ $kpis['exportCount'] ?? 0 }} &middot; Downloads {{ $kpis['downloadCount'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-600">7d: Imports {{ $kpis['importCount7d'] ?? 0 }} &middot; Exports {{ $kpis['exportCount7d'] ?? 0 }} &middot; Downloads {{ $kpis['downloadCount7d'] ?? 0 }}</p>
                    <p class="mt-2 text-xs font-semibold {{ ($kpis['checkerRequired'] ?? false) ? 'text-purple-700' : 'text-slate-500' }}">{{ ($kpis['checkerRequired'] ?? false) ? 'Checker Required' : 'Checker Queue Clear' }}</p>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statutory Compliance Pulse</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $kpis['integrityScore'] ?? 100 }}<span class="text-sm font-semibold text-slate-500">%</span></p>
                    <p class="mt-1 text-xs text-slate-600">Log checksum verified {{ $kpis['checksumVerified'] ?? 0 }} &middot; PAYE shadow ledger {{ ($kpis['shadowLedgerActive'] ?? false) ? 'active' : 'inactive' }}</p>
                    <p class="mt-2 text-xs font-semibold text-indigo-700">Auditor Indicator</p>
                </article>
            </section>

            <form method="get" action="{{ route('loan.system.access_logs.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">User</label>
                        <select name="user_id" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">All users</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Method</label>
                        <select name="method" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Any</option>
                            @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'SYSTEM'] as $m)
                                <option value="{{ $m }}" @selected(request('method') === $m)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Activity Type</label>
                        <select name="activity_type" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Any</option>
                            @foreach (($activityTypes ?? []) as $type)
                                <option value="{{ $type }}" @selected(request('activity_type') === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Route</label>
                        <select name="route_name" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">All routes</option>
                            @foreach (($routes ?? []) as $routeName)
                                <option value="{{ $routeName }}" @selected(request('route_name') === $routeName)>{{ $routeName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">IP</label>
                        <select name="ip_address" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">All IPs</option>
                            @foreach (($ips ?? []) as $ip)
                                <option value="{{ $ip }}" @selected(request('ip_address') === $ip)>{{ $ip }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">From</label>
                        <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">To</label>
                        <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Per page</label>
                        <select name="per_page" class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach ([20, 40, 80, 120, 200] as $size)
                                <option value="{{ $size }}" @selected((int) ($perPage ?? 40) === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Search</label>
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search event, user, route, IP, reason, audit token..." class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-600">
                        <label class="inline-flex items-center gap-1"><input type="checkbox" name="boolean_search" value="1" @checked(request()->boolean('boolean_search')) class="rounded border-slate-300"> Boolean</label>
                        <label class="inline-flex items-center gap-1"><input type="checkbox" name="advanced_boolean" value="1" @checked(request()->boolean('advanced_boolean')) class="rounded border-slate-300"> Advanced Boolean</label>
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter Presets</button>
                    <select class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                        <option>View All Privileged Actions</option>
                        <option>Only Privileged</option>
                        <option>Only Critical</option>
                    </select>
                </div>
            </form>

            <section class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-9">
                    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table class="min-w-[1200px] w-full text-sm">
                            <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-3">Time (UTC)</th>
                                    <th class="px-3 py-3">User / Role</th>
                                    <th class="px-3 py-3">Method</th>
                                    <th class="px-3 py-3">Activity / Type</th>
                                    <th class="px-3 py-3">Path / Route / Reason</th>
                                    <th class="px-3 py-3">IP / Geo</th>
                                    <th class="px-3 py-3">Result</th>
                                    <th class="px-3 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($logs as $log)
                                    @php
                                        $score = (int) ($log->risk_score ?? 10);
                                        $riskClass = $score >= 90 ? 'bg-rose-50/80' : ($score >= 70 ? 'bg-amber-50/70' : '');
                                        $riskLabel = $score >= 90 ? 'Critical' : ($score >= 70 ? 'High' : ($score >= 40 ? 'Medium' : 'Low'));
                                        $riskTone = $score >= 90 ? 'text-rose-700 bg-rose-100' : ($score >= 70 ? 'text-amber-700 bg-amber-100' : ($score >= 40 ? 'text-orange-700 bg-orange-100' : 'text-emerald-700 bg-emerald-100'));
                                        $result = strtolower((string) ($log->result ?? 'success'));
                                        $dot = $result === 'blocked' || $score >= 90 ? 'bg-rose-500' : ($log->method === 'GET' ? 'bg-emerald-500' : ($score >= 40 ? 'bg-amber-500' : 'bg-sky-500'));
                                        $role = $log->user?->is_super_admin ? 'Director' : ucfirst((string) ($log->user?->loan_role ?? 'System'));
                                        $initials = strtoupper(substr((string) ($log->user?->name ?? 'SY'), 0, 2));
                                        $flag = ($log->country_code ?? 'KE') === 'KE' ? '🇰🇪' : '🌍';
                                    @endphp
                                    <tr class="hover:bg-slate-50 {{ $riskClass }}">
                                        <td class="whitespace-nowrap px-3 py-3 text-xs text-slate-600">{{ optional($log->created_at)->setTimezone('UTC')->format('Y-m-d H:i:s') }}</td>
                                        <td class="px-3 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-[11px] font-semibold text-white">{{ $initials }}</span>
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-900">{{ $log->user?->name ?? '(BLOCKED IP)' }}</p>
                                                    <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">{{ $role }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-xs font-semibold text-slate-700">{{ $log->method }}</td>
                                        <td class="px-3 py-3 text-xs">
                                            <div class="flex items-start gap-2">
                                                <span class="mt-1 h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                                                <div>
                                                    <p class="font-semibold text-slate-800">{{ $log->activity ?? 'Activity logged' }}</p>
                                                    <p class="text-slate-500">{{ ucfirst((string) ($log->action_type ?? 'event')) }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-xs text-slate-600">
                                            <p class="max-w-[260px] truncate font-mono text-[11px] text-slate-700" title="{{ $log->path }}">{{ $log->path }}</p>
                                            <p class="max-w-[260px] truncate" title="{{ $log->route_name }}">{{ $log->route_name ?? '—' }}</p>
                                            <p class="max-w-[260px] truncate text-[11px] text-slate-500" title="{{ $log->risk_reason }}">{{ $log->risk_reason ?? 'Routine review' }}</p>
                                        </td>
                                        <td class="px-3 py-3 text-xs text-slate-600">
                                            <p>{{ $log->ip_address }} {{ $flag }}</p>
                                            <p class="text-[11px] text-slate-500">{{ $log->geo_label ?? 'Nairobi, KE' }}</p>
                                        </td>
                                        <td class="px-3 py-3 text-xs">
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-semibold {{ $result === 'blocked' ? 'bg-rose-100 text-rose-700' : ($result === 'pending_review' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">{{ ucfirst(str_replace('_', ' ', $result)) }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-xs">
                                            <details class="group relative">
                                                <summary class="cursor-pointer list-none rounded-lg border border-slate-200 px-2 py-1 text-slate-700">Actions</summary>
                                                <div class="absolute right-0 z-10 mt-1 w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-lg">
                                                    <form method="post" action="{{ route('loan.system.access_logs.concerns.store', $log) }}" class="space-y-2">
                                                        @csrf
                                                        <input type="text" name="title" required maxlength="255" value="Path concern: {{ \Illuminate\Support\Str::limit($log->path, 40) }}" class="w-full rounded-lg border-slate-200 text-xs">
                                                        <textarea name="reason" rows="2" required maxlength="5000" class="w-full rounded-lg border-slate-200 text-xs" placeholder="What were you doing on this path? Please explain."></textarea>
                                                        <select name="priority" class="w-full rounded-lg border-slate-200 text-xs">
                                                            <option value="normal">Normal</option>
                                                            <option value="high">High</option>
                                                            <option value="critical">Critical</option>
                                                        </select>
                                                        <button type="submit" class="w-full rounded-lg bg-slate-900 px-2 py-1.5 text-xs font-semibold text-white">Open Concern Conversation</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">No log entries yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if ($logs->hasPages())
                            <div class="border-t border-slate-100 px-4 py-3">{{ $logs->links() }}</div>
                        @endif
                    </div>
                </div>

                <aside class="space-y-3 xl:col-span-3">
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Security Intelligence Summary</h3>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-lg bg-rose-50 p-2"><p class="text-lg font-bold text-rose-700">{{ $kpis['criticalEvents'] ?? 0 }}</p><p class="text-[11px] text-rose-600">Critical Events</p></div>
                            <div class="rounded-lg bg-amber-50 p-2"><p class="text-lg font-bold text-amber-700">{{ $kpis['highRiskActions'] ?? 0 }}</p><p class="text-[11px] text-amber-600">High Risk Actions</p></div>
                            <div class="rounded-lg bg-blue-50 p-2"><p class="text-lg font-bold text-blue-700">{{ $kpis['blockedAttempts'] ?? 0 }}</p><p class="text-[11px] text-blue-600">Blocked Attempts</p></div>
                            <div class="rounded-lg bg-emerald-50 p-2"><p class="text-lg font-bold text-emerald-700">{{ $kpis['successfulLogins'] ?? 0 }}</p><p class="text-[11px] text-emerald-600">Successful Logins</p></div>
                        </div>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Risk Distribution (Today)</h3>
                        @php
                            $total = max(1, (int) ($riskDistribution['total'] ?? 1));
                            $criticalPct = round((($riskDistribution['critical'] ?? 0) / $total) * 100);
                            $highPct = round((($riskDistribution['high'] ?? 0) / $total) * 100);
                            $mediumPct = round((($riskDistribution['medium'] ?? 0) / $total) * 100);
                        @endphp
                        <div class="mt-3 flex items-center gap-3">
                            <div class="h-24 w-24 rounded-full" style="background: conic-gradient(#ef4444 0 {{ $criticalPct }}%, #f59e0b {{ $criticalPct }}% {{ $criticalPct + $highPct }}%, #f97316 {{ $criticalPct + $highPct }}% {{ $criticalPct + $highPct + $mediumPct }}%, #10b981 {{ $criticalPct + $highPct + $mediumPct }}% 100%);">
                                <div class="m-4 flex h-16 w-16 items-center justify-center rounded-full bg-white text-xs font-semibold text-slate-700">{{ $riskDistribution['total'] ?? 0 }}</div>
                            </div>
                            <div class="space-y-1 text-xs text-slate-600">
                                <p><span class="text-rose-600">●</span> Critical: {{ $riskDistribution['critical'] ?? 0 }}</p>
                                <p><span class="text-amber-600">●</span> High: {{ $riskDistribution['high'] ?? 0 }}</p>
                                <p><span class="text-orange-600">●</span> Medium: {{ $riskDistribution['medium'] ?? 0 }}</p>
                                <p><span class="text-emerald-600">●</span> Low: {{ $riskDistribution['low'] ?? 0 }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Top Risky Users</h3>
                        <div class="mt-3 space-y-2">
                            @foreach (($topRiskyUsers ?? collect()) as $idx => $userRisk)
                                @php $bar = min(100, (int) $userRisk->risk_points); @endphp
                                <div>
                                    <p class="text-xs font-medium text-slate-700">{{ $idx + 1 }}. {{ $userRisk->user?->name ?? 'Unknown user' }} <span class="float-right text-slate-500">{{ (int) $userRisk->risk_points }}</span></p>
                                    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-teal-600" style="width: {{ $bar }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Daily Security Digest</h3>
                        <p class="mt-2 text-xs text-slate-600">Email sent every day at 8:00 AM</p>
                        <p class="mt-1 text-xs text-slate-500">Last sent: {{ $kpis['digestLastSentAt'] ?? now()->format('Y-m-d 08:00') }}</p>
                        <button type="button" class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Send Now</button>
                    </article>
                </aside>
            </section>
        </div>
    </x-loan.page>
</x-loan-layout>
