<x-property-layout>
    <x-slot name="header">Maintenance</x-slot>

    <x-property.page
        title="Maintenance"
        subtitle="Transparency on requests, quotes, and spend — approve high-cost jobs when your agreement requires it."
    >
        <form method="post" action="{{ route('property.landlord.maintenance.threshold.store') }}" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Approval threshold (KES)</label>
                <input type="number" name="approval_threshold" min="0" step="1" value="{{ (int) $approvalThreshold }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="pending_only" value="1" class="rounded border-slate-300" @checked($pendingOnly) />
                Pending approvals/open jobs only
            </label>
            <button type="submit" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/60">Save + apply</button>
        </form>

        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($stats as $s)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $s['label'] }}</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $s['value'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $s['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Jobs and approvals</h2>
                <span class="text-xs text-slate-500">Approval threshold: {{ \App\Services\Property\PropertyMoney::kes((float) $approvalThreshold) }}</span>
            </div>
            @if ($jobs->isEmpty())
                <p class="text-sm text-slate-500">No maintenance activity found.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="py-2 pr-3">Job</th>
                                <th class="py-2 pr-3">Property / unit</th>
                                <th class="py-2 pr-3">Vendor</th>
                                <th class="py-2 pr-3">Quote</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($jobs as $job)
                                <tr class="border-b border-slate-100 dark:border-slate-700/70">
                                    <td class="py-2 pr-3 font-medium">#{{ $job->id }}</td>
                                    <td class="py-2 pr-3">{{ $job->request?->unit?->property?->name ?? '—' }} / {{ $job->request?->unit?->label ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $job->vendor?->name ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ \App\Services\Property\PropertyMoney::kes((float) ($job->quote_amount ?? 0)) }}</td>
                                    <td class="py-2 pr-3">{{ ucfirst(str_replace('_', ' ', (string) $job->status)) }}</td>
                                    <td class="py-2">
                                        <form method="post" action="{{ route('property.landlord.maintenance.jobs.approval', $job) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="approval_threshold" value="{{ (float) $approvalThreshold }}" />
                                            <input type="text" name="note" placeholder="Optional note" class="min-w-[150px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1 text-xs" />
                                            <button type="submit" name="decision" value="approve" class="rounded-lg bg-emerald-600 text-white px-2 py-1 text-xs font-medium hover:bg-emerald-700">Approve</button>
                                            <button type="submit" name="decision" value="reject" class="rounded-lg bg-rose-600 text-white px-2 py-1 text-xs font-medium hover:bg-rose-700">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-property.page>
</x-property-layout>
