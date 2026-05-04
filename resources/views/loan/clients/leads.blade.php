<x-loan-layout>
    <x-loan.page
        title="Client leads"
        subtitle="Lead-to-cash intelligence: pipeline, officer performance, and follow-up discipline."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.leads.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Create a lead
            </a>
        </x-slot>

        <x-slot name="banner">
            @include('loan.clients.partials.identity-flashes')
        </x-slot>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Leads (MTD)</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ number_format((int) ($summary['total_leads_mtd'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Conversion rate</p>
                <p class="mt-1 text-2xl font-bold text-emerald-800 tabular-nums">{{ number_format((float) ($summary['conversion_rate'] ?? 0), 1) }}%</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pipeline value</p>
                <p class="mt-1 text-xl font-bold text-slate-900 tabular-nums">{{ number_format((float) ($summary['pipeline_value'] ?? 0), 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Disbursed (MTD)</p>
                <p class="mt-1 text-xl font-bold text-slate-900 tabular-nums">{{ number_format((float) ($summary['total_disbursed'] ?? 0), 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Avg conversion time</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">
                    {{ $summary['avg_conversion_days'] !== null ? number_format((float) $summary['avg_conversion_days'], 1).'d' : '—' }}
                </p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50/80 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-800">Not contacted ({{ (int) ($summary['idle_hours'] ?? 24) }}h SLA)</p>
                <p class="mt-1 text-2xl font-bold text-rose-900 tabular-nums">{{ number_format((int) ($summary['not_contacted_24h'] ?? 0)) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800">Leaderboard — conversion rate</h3>
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @forelse (($leaderboard['top_by_conversion_rate'] ?? collect()) as $row)
                        <li class="py-2 flex justify-between gap-2">
                            <span class="text-slate-700 truncate">{{ $row['user_name'] ?? '—' }}</span>
                            <span class="text-slate-900 font-semibold tabular-nums">{{ number_format((float) ($row['conversion_rate'] ?? 0), 1) }}%</span>
                        </li>
                    @empty
                        <li class="py-3 text-slate-500 text-sm">No officer data for this period.</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800">Leaderboard — disbursed value</h3>
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @forelse (($leaderboard['top_by_disbursed'] ?? collect()) as $row)
                        <li class="py-2 flex justify-between gap-2">
                            <span class="text-slate-700 truncate">{{ $row['user_name'] ?? '—' }}</span>
                            <span class="text-slate-900 font-semibold tabular-nums">{{ number_format((float) ($row['total_disbursed'] ?? 0), 0) }}</span>
                        </li>
                    @empty
                        <li class="py-3 text-slate-500 text-sm">No disbursements attributed yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm mb-6">
            <h3 class="text-sm font-semibold text-amber-950">Insights (MTD, portfolio-scoped)</h3>
            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-amber-950/90">
                <div>
                    <p class="font-semibold text-amber-950 mb-1">Highest-value sources</p>
                    <ul class="space-y-1">
                        @forelse (($insights['value_by_source'] ?? collect()) as $row)
                            <li class="flex justify-between gap-2">
                                <span>{{ $pipelineSources[$row->lead_source] ?? $row->lead_source }}</span>
                                <span class="tabular-nums font-medium">{{ number_format((float) $row->disbursed, 0) }}</span>
                            </li>
                        @empty
                            <li>—</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-amber-950 mb-1">Drop reasons</p>
                    <ul class="space-y-1">
                        @forelse (($insights['drop_reason_counts'] ?? collect()) as $row)
                            <li class="flex justify-between gap-2">
                                <span>{{ str_replace('_', ' ', (string) $row->reason) }}</span>
                                <span class="tabular-nums font-medium">{{ (int) $row->c }}</span>
                            </li>
                        @empty
                            <li>—</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-amber-950 mb-1">Lost pipeline value</p>
                    <p class="text-lg font-bold text-amber-950 tabular-nums">{{ number_format((float) ($insights['lost_pipeline_value'] ?? 0), 0) }}</p>
                    <p class="mt-2 font-semibold text-amber-950 mb-1">Fastest cash-out (avg days)</p>
                    <ul class="space-y-1">
                        @forelse (($insights['officer_disbursement_speed'] ?? collect()) as $row)
                            <li class="flex justify-between gap-2">
                                <span class="truncate">{{ $row['user_name'] ?? '—' }}</span>
                                <span class="tabular-nums font-medium">{{ $row['avg_days_to_disburse'] !== null ? number_format((float) $row['avg_days_to_disburse'], 1).'d' : '—' }}</span>
                            </li>
                        @empty
                            <li>—</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <form method="get" action="{{ route('loan.clients.leads') }}" class="mb-4 space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 sm:items-end">
                <div class="lg:col-span-2">
                    <x-input-label for="q" value="Search" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block w-full" :value="request('q')" placeholder="Name, number, phone…" />
                </div>
                <div>
                    <x-input-label for="stage" value="Stage" />
                    <select id="stage" name="stage" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All stages</option>
                        @foreach ($pipelineStages as $val => $label)
                            <option value="{{ $val }}" @selected(request('stage') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="source" value="Source" />
                    <select id="source" name="source" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All sources</option>
                        @foreach ($pipelineSources as $val => $label)
                            <option value="{{ $val }}" @selected(request('source') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="officer_id" value="Officer (user)" />
                    <select id="officer_id" name="officer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All officers</option>
                        @foreach ($officerFilterOptions as $u)
                            <option value="{{ $u->id }}" @selected((string) request('officer_id') === (string) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="sort" value="Sort" />
                    <select id="sort" name="sort" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="created" @selected(request('sort', 'created') === 'created')>Newest</option>
                        <option value="expected" @selected(request('sort') === 'expected')>Expected amount</option>
                        <option value="aging" @selected(request('sort') === 'aging')>Oldest first</option>
                        <option value="activity" @selected(request('sort') === 'activity')>Recent activity</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-primary-button type="submit">Apply filters</x-primary-button>
                @if (request()->hasAny(['q', 'stage', 'source', 'officer_id', 'sort']))
                    <a href="{{ route('loan.clients.leads') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Reset</a>
                @endif
            </div>
            <p class="text-[11px] text-slate-500 leading-relaxed">
                Stage rules are enforced server-side (no skipping ahead). Round-robin assignment uses <code class="text-xs bg-slate-100 px-1 rounded">LEAD_ROUND_ROBIN</code> and <code class="text-xs bg-slate-100 px-1 rounded">LEAD_ROUND_ROBIN_USER_IDS</code> in <code class="text-xs bg-slate-100 px-1 rounded">.env</code>.
            </p>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">Pipeline</h2>
                    <p class="text-xs text-slate-500">{{ $leads->total() }} record(s) · {{ (int) ($summary['stale_no_activity'] ?? 0) }} active without recent touch</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-3">Client</th>
                            <th class="px-3 py-3">Phone</th>
                            <th class="px-3 py-3">Source</th>
                            <th class="px-3 py-3">Officer</th>
                            <th class="px-3 py-3">Stage</th>
                            <th class="px-3 py-3 text-right">Expected</th>
                            <th class="px-3 py-3 text-right">Approved</th>
                            <th class="px-3 py-3 text-right">Disbursed</th>
                            <th class="px-3 py-3 text-right">Days in</th>
                            <th class="px-3 py-3 text-right">Stage days</th>
                            <th class="px-3 py-3">Last activity</th>
                            <th class="px-3 py-3">Next action</th>
                            <th class="px-3 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leads as $lead)
                            @php
                                $cl = $lead->clientLead;
                                $act = $cl ? $latestActivityByClientLeadId->get($cl->id) : null;
                                $daysIn = $lead->created_at ? $lead->created_at->diffInDays(now()) : null;
                                $stageDays = $cl?->stage_entered_at ? $cl->stage_entered_at->diffInDays(now()) : null;
                            @endphp
                            <tr
                                class="hover:bg-slate-50/80 align-top"
                                @click="if (!$event.target.closest('a, button, input, select, textarea, form, label, summary, details')) { window.location.href='{{ $lead->loanPortalProfileUrl() }}'; }"
                            >
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-900">{{ $lead->full_name }}</div>
                                    <div class="text-[11px] font-mono text-slate-500">{{ $lead->client_number }}</div>
                                    @if (!empty($alerts[$lead->id] ?? []))
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($alerts[$lead->id] as $msg)
                                                <span class="inline-flex items-center rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-900">{{ $msg }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-600 whitespace-nowrap">{{ $lead->phone ?? '—' }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $cl ? ($pipelineSources[$cl->lead_source] ?? $cl->lead_source) : '—' }}</td>
                                <td class="px-3 py-3 text-slate-600">
                                    {{ $cl?->assignedOfficer?->name ?? $lead->assignedEmployee?->full_name ?? '—' }}
                                </td>
                                <td class="px-3 py-3" onclick="event.stopPropagation()">
                                    @if ($cl)
                                        <form method="post" action="{{ route('loan.clients.leads.pipeline.stage', $lead) }}" class="inline">
                                            @csrf
                                            <label class="sr-only" for="stage-{{ $lead->id }}">Stage</label>
                                            <select id="stage-{{ $lead->id }}" name="stage" class="rounded-md border-gray-300 text-xs shadow-sm max-w-[9rem]" onchange="this.form.submit()">
                                                @foreach ($pipelineStages as $val => $label)
                                                    <option value="{{ $val }}" @selected($cl->current_stage === $val)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-700">{{ $cl ? number_format((float) $cl->expected_loan_amount, 0) : '—' }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-700">{{ $cl && $cl->approved_amount !== null ? number_format((float) $cl->approved_amount, 0) : '—' }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-700">{{ $cl && $cl->disbursed_amount !== null ? number_format((float) $cl->disbursed_amount, 0) : '—' }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-600">{{ $daysIn !== null ? $daysIn : '—' }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-600">{{ $stageDays !== null ? $stageDays : '—' }}</td>
                                <td class="px-3 py-3 text-xs text-slate-600 whitespace-nowrap">{{ $act?->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-3 py-3 text-xs text-slate-600 whitespace-nowrap">{{ $act?->next_action_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                    <details class="text-left inline-block relative">
                                        <summary class="cursor-pointer list-none rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Pipeline</summary>
                                        <div class="absolute right-0 z-20 mt-1 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-lg space-y-3">
                                            <form method="post" action="{{ route('loan.clients.leads.pipeline.activity', $lead) }}" class="space-y-2">
                                                @csrf
                                                <p class="text-xs font-semibold text-slate-700">Log activity</p>
                                                <select name="activity_type" class="block w-full rounded-md border-gray-300 text-xs">
                                                    <option value="call">Call</option>
                                                    <option value="visit">Visit</option>
                                                    <option value="sms">SMS</option>
                                                    <option value="whatsapp">WhatsApp</option>
                                                </select>
                                                <input type="date" name="next_action_date" class="block w-full rounded-md border-gray-300 text-xs" />
                                                <textarea name="notes" rows="2" class="block w-full rounded-md border-gray-300 text-xs" placeholder="Notes"></textarea>
                                                <button type="submit" class="w-full rounded-lg bg-slate-800 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">Save activity</button>
                                            </form>
                                            <form method="post" action="{{ route('loan.clients.leads.pipeline.loss', $lead) }}" class="space-y-2 border-t border-slate-100 pt-3">
                                                @csrf
                                                <p class="text-xs font-semibold text-rose-800">Mark dropped</p>
                                                <select name="reason" class="block w-full rounded-md border-gray-300 text-xs" required>
                                                    <option value="high_interest">High interest</option>
                                                    <option value="no_documents">No documents</option>
                                                    <option value="not_reachable">Not reachable</option>
                                                    <option value="competitor">Competitor</option>
                                                    <option value="changed_mind">Changed mind</option>
                                                    <option value="other">Other</option>
                                                </select>
                                                <textarea name="notes" rows="2" class="block w-full rounded-md border-gray-300 text-xs" placeholder="Loss notes"></textarea>
                                                <button type="submit" class="w-full rounded-lg border border-rose-200 bg-rose-50 py-1.5 text-xs font-semibold text-rose-900 hover:bg-rose-100">Record loss</button>
                                            </form>
                                            <div class="border-t border-slate-100 pt-2 flex flex-wrap gap-2 text-xs">
                                                <a href="{{ $lead->loanPortalProfileUrl() }}" class="text-slate-600 hover:text-slate-900 font-medium">View</a>
                                                <form method="post" action="{{ route('loan.clients.leads.convert', $lead) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-emerald-700 hover:text-emerald-600 font-medium">Convert</button>
                                                </form>
                                                <a href="{{ route('loan.clients.edit', $lead) }}" class="text-indigo-600 hover:text-indigo-500 font-medium">Edit</a>
                                            </div>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-5 py-12 text-center text-slate-500">
                                    No leads yet. Use <span class="font-medium text-slate-700">Create a Lead</span>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($leads->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $leads->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
