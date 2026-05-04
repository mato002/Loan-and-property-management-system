@php
    $cl = $loan_client->clientLead;
    $pipelineLabels = (array) config('lead_intelligence.pipeline_source_labels', []);
    $stageLabels = [
        \App\Models\ClientLead::STAGE_NEW => 'New',
        \App\Models\ClientLead::STAGE_CONTACTED => 'Contacted',
        \App\Models\ClientLead::STAGE_INTERESTED => 'Interested',
        \App\Models\ClientLead::STAGE_APPLIED => 'Applied',
        \App\Models\ClientLead::STAGE_APPROVED => 'Approved',
        \App\Models\ClientLead::STAGE_DISBURSED => 'Disbursed',
        \App\Models\ClientLead::STAGE_DROPPED => 'Dropped',
    ];
@endphp

<x-loan-layout>
    <x-loan.page
        title="{{ $loan_client->full_name }}"
        subtitle="Lead workspace — complete KYC and onboarding before this prospect uses the full client profile."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.edit', $loan_client) }}" class="inline-flex items-center justify-center rounded-lg border border-teal-700 bg-teal-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-800 transition-colors">
                Complete details
            </a>
            <a href="{{ route('loan.clients.interactions.for_client.create', $loan_client) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Notes / interactions
            </a>
            <a href="{{ route('loan.clients.leads') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to leads
            </a>
        </x-slot>

        <x-slot name="banner">
            @include('loan.clients.partials.identity-flashes', ['contextClient' => $loan_client])
        </x-slot>

        <div class="max-w-5xl space-y-6">
            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-900/80">Prospect</p>
                        <h2 class="mt-1 text-lg font-semibold text-amber-950">Not a full client yet</h2>
                        <p class="mt-2 text-sm text-amber-950/90 leading-relaxed">
                            Use <strong>Complete details</strong> to capture remaining biodata and requirements. When you
                            <strong>Convert to client</strong>, they move to the standard client profile for wallet, loans, and reporting.
                        </p>
                    </div>
                    <form method="post" action="{{ route('loan.clients.leads.convert', $loan_client) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 transition-colors">
                            Convert to client
                        </button>
                    </form>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800">Lead summary</h3>
                <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</dt>
                        <dd class="mt-0.5 font-mono text-slate-800">{{ $loan_client->client_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Legacy status</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $loan_client->lead_status ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $loan_client->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $loan_client->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $loan_client->branch ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned officer</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $loan_client->assignedEmployee?->full_name ?? '—' }}</dd>
                    </div>
                    @if ($leadSourceLabel)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Capture source</dt>
                            <dd class="mt-0.5 text-slate-800">{{ $leadSourceLabel }}</dd>
                        </div>
                    @endif
                    @if ($cl)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pipeline stage</dt>
                            <dd class="mt-0.5 text-slate-800">{{ $stageLabels[$cl->current_stage] ?? $cl->current_stage }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pipeline source</dt>
                            <dd class="mt-0.5 text-slate-800">{{ $pipelineLabels[$cl->lead_source] ?? $cl->lead_source }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Expected amount</dt>
                            <dd class="mt-0.5 text-slate-800 tabular-nums">{{ number_format((float) $cl->expected_loan_amount, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Officer (user)</dt>
                            <dd class="mt-0.5 text-slate-800">{{ $cl->assignedOfficer?->name ?? '—' }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($loan_client->notes)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</p>
                        <p class="mt-1 text-sm text-slate-700 whitespace-pre-line">{{ $loan_client->notes }}</p>
                    </div>
                @endif
            </div>

            @if ($cl && $cl->activities->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Recent pipeline activity</h3>
                    <ul class="mt-3 divide-y divide-slate-100 text-sm">
                        @foreach ($cl->activities as $act)
                            <li class="py-3 flex flex-col gap-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-medium text-slate-800">{{ ucfirst($act->activity_type) }}</span>
                                    <span class="text-xs text-slate-500">{{ $act->created_at?->format('Y-m-d H:i') }}</span>
                                </div>
                                @if ($act->notes)
                                    <p class="text-slate-600 whitespace-pre-line">{{ $act->notes }}</p>
                                @endif
                                @if ($act->next_action_date)
                                    <p class="text-xs text-teal-800">Next action: {{ $act->next_action_date->format('Y-m-d') }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
