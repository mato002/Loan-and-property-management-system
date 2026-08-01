@php
    $suspenseCheck = collect($checklist)->firstWhere('key', 'suspense_reviewed');
    $suspenseNeedsAck = $period->isOpen() && $suspenseCheck && ! ($suspenseCheck['passed'] ?? false);
@endphp

<x-property.workspace :compact-list="false"
    :title="'Utility period — '.$billingMonth"
    subtitle="Reconciliation checklist, period close, and supervisor override workflow."
    back-route="property.revenue.utilities.periods"
    :stats="[]"
>
    <x-slot name="actions">
        @if ($period->isClosed())
            <a href="{{ route('property.revenue.utilities.periods.close_report', ['billingMonth' => $billingMonth, 'export' => 'pdf'], false) }}" target="_blank" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close report (PDF)</a>
            <a href="{{ route('property.revenue.utilities.periods.close_report', ['billingMonth' => $billingMonth], false) }}" target="_blank" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Print view</a>
        @endif
        <a href="{{ route('property.revenue.utilities.reconciliation', ['from' => $billingMonth.'-01', 'to' => \Illuminate\Support\Carbon::parse($billingMonth.'-01')->endOfMonth()->toDateString()], false) }}" class="rounded-xl border border-teal-300 bg-teal-50 px-3 py-2 text-sm font-medium text-teal-800 hover:bg-teal-100">Reconciliation</a>
    </x-slot>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        @if ($period->isClosed())
            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-sm font-semibold text-rose-800">CLOSED — immutable</span>
            @if ($period->closed_at)
                <span class="text-sm text-slate-600">Closed {{ $period->closed_at->format('Y-m-d H:i') }} by {{ $period->closedBy?->name ?? '—' }}</span>
            @endif
        @else
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">OPEN — editable</span>
        @endif
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3 bg-slate-50">
            <h2 class="text-sm font-semibold text-slate-800">Pre-close reconciliation checklist</h2>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach ($checklist as $check)
                <li class="flex flex-wrap items-start gap-3 px-4 py-3">
                    <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ ($check['passed'] ?? false) ? 'bg-emerald-100 text-emerald-800' : (($check['severity'] ?? '') === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                        {{ ($check['passed'] ?? false) ? '✓' : '!' }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-slate-900">{{ $check['label'] ?? '' }}</p>
                        <p class="text-sm text-slate-600">{{ $check['detail'] ?? '' }}</p>
                    </div>
                    <span class="text-xs uppercase tracking-wide text-slate-400">{{ $check['severity'] ?? '' }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    @if ($period->isOpen())
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Close this period</h2>
            @if ($canClose)
                <form method="post" action="{{ route('property.revenue.utilities.periods.close', ['billingMonth' => $billingMonth], false) }}" class="space-y-3 max-w-xl" data-swal-confirm="Close {{ $billingMonth }}? This locks all utility mutations for the month.">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-600">Close notes (optional)</label>
                        <textarea name="close_notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Reconciliation sign-off notes…">{{ old('close_notes') }}</textarea>
                    </div>
                    @if ($suspenseNeedsAck)
                        <label class="flex items-start gap-2 text-sm text-amber-900">
                            <input type="checkbox" name="acknowledge_suspense" value="1" class="mt-1 rounded border-amber-400" @checked(old('acknowledge_suspense')) />
                            <span>I acknowledge the suspense (GL 1250) balance and confirm it has been reviewed before closing.</span>
                        </label>
                    @endif
                    <button type="submit" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Close {{ $billingMonth }}</button>
                </form>
            @else
                <p class="text-sm text-rose-700">Resolve all critical checklist items before closing.@if ($suspenseNeedsAck) Acknowledge suspense review if applicable.@endif</p>
            @endif
        </div>
    @elseif ($period->close_notes)
        <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <h2 class="text-sm font-semibold text-slate-800">Close notes</h2>
            <p class="mt-1 text-sm text-slate-700 whitespace-pre-wrap">{{ $period->close_notes }}</p>
        </div>
    @endif

    @if ($period->isClosed())
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Request supervisor override</h2>
            <p class="text-xs text-slate-500 mb-3">Maker/checker required. Approved overrides expire after 48 hours and are single-use.</p>
            <form method="post" action="{{ route('property.revenue.utilities.periods.overrides.request', ['billingMonth' => $billingMonth], false) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3 max-w-3xl">
                @csrf
                <div>
                    <label class="text-xs text-slate-600">Action</label>
                    <select name="action_type" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        @foreach ($actionTypes as $key => $label)
                            <option value="{{ $key }}" @selected(old('action_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-600">Entity ID (optional)</label>
                    <input type="number" name="entity_id" value="{{ old('entity_id') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Reading / invoice / payment ID" />
                    <input type="hidden" name="entity_type" value="" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-600">Reason (required)</label>
                    <textarea name="reason" rows="2" required class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Explain why this closed-period change is necessary…">{{ old('reason') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Submit override request</button>
                </div>
            </form>
        </div>
    @endif

    @php
        $allOverrides = $period->overrideRequests->sortByDesc('created_at');
    @endphp
    @if ($allOverrides->isNotEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-4 py-3 bg-slate-50">
                <h2 class="text-sm font-semibold text-slate-800">Override requests</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Action</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Requester</th>
                            <th class="px-4 py-3 text-left">Reason</th>
                            <th class="px-4 py-3 text-right">Checker</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($allOverrides as $override)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $override->id }}</td>
                                <td class="px-4 py-3">{{ $override->actionLabel() }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold
                                        @if ($override->status === 'approved') bg-emerald-100 text-emerald-800
                                        @elseif ($override->status === 'pending') bg-amber-100 text-amber-800
                                        @elseif ($override->status === 'executed') bg-slate-200 text-slate-700
                                        @else bg-rose-100 text-rose-800 @endif">
                                        {{ ucfirst($override->status) }}
                                    </span>
                                    @if ($override->isApproved())
                                        <p class="text-xs text-slate-500 mt-1">Use override #{{ $override->id }} on the action form (48h window).</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $override->requester?->name ?? '—' }}</td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $override->reason }}">{{ $override->reason }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($override->status === 'pending')
                                        <form method="post" action="{{ route('property.revenue.utilities.periods.overrides.approve', $override, false) }}" class="inline-block mb-1" data-swal-confirm="Approve override #{{ $override->id }}?">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-emerald-700 hover:underline">Approve</button>
                                        </form>
                                        <form method="post" action="{{ route('property.revenue.utilities.periods.overrides.reject', $override, false) }}" class="inline-block ml-2">
                                            @csrf
                                            <input type="hidden" name="rejection_reason" value="Rejected from period control screen" />
                                            <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline" data-swal-confirm="Reject override #{{ $override->id }}?">Reject</button>
                                        </form>
                                    @else
                                        {{ $override->approver?->name ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($period->isClosed() && ! empty($closeReport))
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Close report summary</h2>
            @php $totals = $closeReport['totals'] ?? []; @endphp
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Total billed</p>
                    <p class="font-bold">{{ \App\Services\Property\PropertyMoney::kes($totals['total_billed'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Open AR</p>
                    <p class="font-bold">{{ \App\Services\Property\PropertyMoney::kes($totals['open_ar'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Unapplied credit</p>
                    <p class="font-bold">{{ $closeReport['credits_summary']['total_unapplied_display'] ?? '—' }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Outstanding invoices</p>
                    <p class="font-bold">{{ count($closeReport['outstanding_balances'] ?? []) }}</p>
                </div>
            </div>
        </div>
    @endif
</x-property.workspace>
