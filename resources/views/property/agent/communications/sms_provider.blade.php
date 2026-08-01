@php
    $historyResult = (array) ($history ?? []);
    $rows = (array) ($historyResult['data'] ?? []);
    $meta = (array) ($historyResult['meta'] ?? []);
    $historyOk = (bool) ($historyResult['ok'] ?? false);
    $historyError = (string) ($historyResult['error'] ?? '');
    $currentPage = max(1, (int) ($meta['current_page'] ?? ($filters['page'] ?? 1)));
    $lastPage = max(1, (int) ($meta['last_page'] ?? 1));
    $total = (int) ($meta['total'] ?? count($rows));
    $currency = (string) (($smsWallet['currency'] ?? 'KES'));
@endphp

<x-property.workspace :compact-list="false"
    title="Provider SMS"
    subtitle="Live Pradytec Bulk SMS statistics and delivery history from the provider API."
    back-route="property.communications.index"
    :stats="$stats"
    :columns="[]"
    :show-search="false"
    empty-title="No provider SMS history"
    empty-hint="Messages sent through Pradytec will appear here once the provider API is configured."
>
    <x-slot name="above">
        @include('property.agent.communications.partials.communications_manage_bar', ['manageContext' => 'provider'])

        @include('property.agent.communications.partials.sms_wallet_banner')

        @include('property.agent.communications.partials.sms_topup_card')

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm text-xs text-slate-600 dark:text-slate-300">
            <p class="font-semibold text-slate-800 dark:text-slate-100">Pradytec webhook URL</p>
            <p class="mt-1">Share this with your Pradytec account manager for real-time balance and delivery updates:</p>
            <code class="mt-2 block break-all rounded-lg bg-slate-100 dark:bg-slate-900 px-3 py-2 text-[11px]">{{ $webhookUrl ?? url('/webhooks/property/communications/pradytec') }}</code>
            <p class="mt-2 opacity-80">Set <code class="text-[11px]">BULKSMS_WEBHOOK_SECRET</code> in <code class="text-[11px]">.env</code> to match the secret they configure.</p>
        </div>

        <form method="get" action="{{ route('property.communications.sms_provider', absolute: false) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['queued', 'sent', 'delivered', 'failed'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ ucfirst($statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Per page</label>
                    <select name="per_page" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Apply filters</button>
                <a href="{{ route('property.communications.sms_provider', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
        </form>
    </x-slot>

    @if (! $historyOk)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
            {{ $historyError !== '' ? $historyError : 'Could not load provider SMS history.' }}
        </div>
    @elseif ($rows === [])
        <p class="text-sm text-slate-600 dark:text-slate-300">No messages match your filters.</p>
    @else
        <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Message ID</th>
                        <th class="px-4 py-3">Recipient</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Cost</th>
                        <th class="px-4 py-3">Sent</th>
                        <th class="px-4 py-3">Delivered</th>
                        <th class="px-4 py-3">Message</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $row = (array) $row;
                            $status = strtolower((string) ($row['status'] ?? 'unknown'));
                            $statusClass = match ($status) {
                                'delivered' => 'text-emerald-700 dark:text-emerald-300',
                                'sent', 'queued' => 'text-amber-700 dark:text-amber-300',
                                'failed' => 'text-rose-700 dark:text-rose-300',
                                default => 'text-slate-600 dark:text-slate-300',
                            };
                        @endphp
                        <tr class="border-t border-slate-100 dark:border-slate-700/70">
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $row['message_id'] ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row['recipient'] ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-semibold {{ $statusClass }}">{{ strtoupper($status) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ number_format((float) ($row['cost'] ?? 0), 2) }} {{ $currency }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs">{{ isset($row['sent_at']) ? \Illuminate\Support\Str::of((string) $row['sent_at'])->replace('T', ' ')->substr(0, 19) : '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs">{{ isset($row['delivered_at']) ? \Illuminate\Support\Str::of((string) $row['delivered_at'])->replace('T', ' ')->substr(0, 19) : '—' }}</td>
                            <td class="px-4 py-3 max-w-md truncate" title="{{ $row['message'] ?? '' }}">{{ \Illuminate\Support\Str::limit((string) ($row['message'] ?? ''), 80) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($lastPage > 1)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-600 dark:text-slate-300">
                <p>Page {{ $currentPage }} of {{ $lastPage }} · {{ number_format($total) }} total</p>
                <div class="flex flex-wrap gap-2">
                    @if ($currentPage > 1)
                        <a href="{{ route('property.communications.sms_provider', array_merge((array) ($filters ?? []), ['page' => $currentPage - 1]), absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 font-medium hover:bg-slate-50 dark:hover:bg-slate-700/50">Previous</a>
                    @endif
                    @if ($currentPage < $lastPage)
                        <a href="{{ route('property.communications.sms_provider', array_merge((array) ($filters ?? []), ['page' => $currentPage + 1]), absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 font-medium hover:bg-slate-50 dark:hover:bg-slate-700/50">Next</a>
                    @endif
                </div>
            </div>
        @endif
    @endif
</x-property.workspace>
