@php
    $historyResult = (array) ($history ?? []);
    $rows = (array) ($historyResult['data'] ?? []);
    $meta = (array) ($historyResult['meta'] ?? []);
    $historyOk = (bool) ($historyResult['ok'] ?? false);
    $currentPage = max(1, (int) ($meta['current_page'] ?? ($filters['page'] ?? 1)));
    $lastPage = max(1, (int) ($meta['last_page'] ?? 1));
    $total = (int) ($meta['total'] ?? count($rows));
    $currency = (string) (($usage['currency'] ?? $smsWallet['currency'] ?? 'KES'));
    $period = (string) ($filters['period'] ?? 'month');
    $days = (array) ($usage['days'] ?? []);
    $recent = (array) ($usage['recent'] ?? []);
    $periodLinks = [
        'today' => 'Today',
        'month' => 'This month',
        '30d' => 'Last 30 days',
    ];
    $spendText = function (float $spend, int $priced) use ($currency): string {
        return $priced > 0 ? number_format($spend, 2).' '.$currency : '—';
    };
@endphp

<x-property.workspace :compact-list="false"
    title="Provider SMS"
    back-route="property.communications.index"
    :stats="$stats"
    :columns="[]"
    :show-search="false"
>
    <x-slot name="above">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($periodLinks as $key => $label)
                <a href="{{ route('property.communications.sms_provider', ['period' => $key], false) }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $period === $key ? 'bg-emerald-700 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50' }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('property.communications.messages', absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50">SMS / email log</a>
        </div>

        @include('property.agent.communications.partials.sms_wallet_banner', ['compact' => true])

        @if (($smsDriver ?? 'pradytec') !== 'africastalking')
            @include('property.agent.communications.partials.sms_topup_card')
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 px-4 py-3 shadow-sm">
                <p class="text-xs font-medium text-slate-500">Webhook</p>
                <code class="mt-1 block break-all text-[11px] text-slate-800 dark:text-slate-100">{{ $webhookUrl ?? url('/webhooks/property/communications/pradytec') }}</code>
            </div>
        @endif
    </x-slot>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3 text-right">Sent</th>
                    <th class="px-4 py-3 text-right">Failed</th>
                    <th class="px-4 py-3 text-right">Spend</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($days as $day)
                    <tr class="border-t border-slate-100 dark:border-slate-700/70">
                        <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-900 dark:text-white">{{ \Illuminate\Support\Carbon::parse($day['day'])->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((int) $day['sms']) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ (int) $day['failed'] > 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">{{ number_format((int) $day['failed']) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $spendText((float) $day['spend'], (int) $day['priced']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">0</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 overflow-x-auto border-t border-slate-100 dark:border-slate-700">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">Sent</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Spend</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $row)
                    @php
                        $status = strtolower((string) ($row['status'] ?? ''));
                        $statusClass = match ($status) {
                            'failed' => 'text-rose-700 dark:text-rose-300',
                            'sent', 'delivered' => 'text-emerald-700 dark:text-emerald-300',
                            default => 'text-slate-600 dark:text-slate-300',
                        };
                    @endphp
                    <tr class="border-t border-slate-100 dark:border-slate-700/70">
                        <td class="px-4 py-3 whitespace-nowrap text-xs">{{ $row['at'] !== '' ? $row['at'] : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-phone-link :value="$row['phone']" /></td>
                        <td class="px-4 py-3 whitespace-nowrap font-semibold {{ $statusClass }}">{{ strtoupper($status !== '' ? $status : '—') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['spend'] !== null ? number_format((float) $row['spend'], 2).' '.$currency : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">0</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($historyOk && $rows !== [])
        <div class="mt-6 overflow-x-auto border-t border-slate-100 dark:border-slate-700">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Sent</th>
                        <th class="px-4 py-3">Recipient</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $row = (array) $row; @endphp
                        <tr class="border-t border-slate-100 dark:border-slate-700/70">
                            <td class="px-4 py-3 whitespace-nowrap text-xs">{{ isset($row['sent_at']) ? \Illuminate\Support\Str::of((string) $row['sent_at'])->replace('T', ' ')->substr(0, 16) : '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row['recipient'] ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-semibold">{{ strtoupper((string) ($row['status'] ?? '—')) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) ($row['cost'] ?? 0), 2) }} {{ $currency }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($lastPage > 1)
                <div class="flex items-center justify-between gap-3 px-4 py-3 text-xs text-slate-600">
                    <p>{{ number_format($total) }}</p>
                    <div class="flex gap-2">
                        @if ($currentPage > 1)
                            <a href="{{ route('property.communications.sms_provider', array_merge((array) ($filters ?? []), ['page' => $currentPage - 1]), false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium">Previous</a>
                        @endif
                        @if ($currentPage < $lastPage)
                            <a href="{{ route('property.communications.sms_provider', array_merge((array) ($filters ?? []), ['page' => $currentPage + 1]), false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium">Next</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-property.workspace>
