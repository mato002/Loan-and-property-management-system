@php
    $months = is_array($months ?? null) ? $months : [];
    $currentMonth = (string) ($currentMonth ?? '');
@endphp
<div class="px-1 py-1">
    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Monthly share breakdown</p>
    <div class="max-h-72 overflow-auto rounded-lg border border-slate-200 bg-white dark:border-slate-600 dark:bg-gray-800">
        <table class="w-full min-w-full text-xs">
            <thead class="sticky top-0 bg-slate-50 text-slate-500 dark:bg-slate-900/80">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Month</th>
                    <th class="px-3 py-2 text-right font-semibold">Collected</th>
                    <th class="px-3 py-2 text-right font-semibold">Owner share</th>
                    @if (($variant ?? 'share') === 'commission')
                        <th class="px-3 py-2 text-right font-semibold">Commission</th>
                        <th class="px-3 py-2 text-right font-semibold">Net to owner</th>
                    @elseif (($variant ?? 'share') === 'settlement')
                        <th class="px-3 py-2 text-right font-semibold">Mgmt fee</th>
                        <th class="px-3 py-2 text-right font-semibold">Amount payable</th>
                    @else
                        <th class="px-3 py-2 text-right font-semibold">Your earnings</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($months as $m)
                    @php
                        $isCurrent = (string) ($m['month'] ?? '') === $currentMonth;
                        $ownerShare = (float) ($m['owner_share'] ?? 0);
                        $earnings = (float) ($m['agent_earning'] ?? 0);
                    @endphp
                    <tr class="border-t border-slate-100 dark:border-slate-700 {{ $isCurrent ? 'bg-indigo-50 dark:bg-indigo-950/40' : '' }}">
                        <td class="px-3 py-2 whitespace-nowrap {{ $isCurrent ? 'font-semibold text-indigo-800 dark:text-indigo-200' : 'text-slate-700 dark:text-slate-200' }}">
                            {{ $m['month_label'] ?? ($m['month'] ?? '—') }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                            {{ \App\Services\Property\PropertyMoney::kes((float) ($m['gross_collected'] ?? 0)) }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                            {{ \App\Services\Property\PropertyMoney::kes($ownerShare) }}
                        </td>
                        @if (($variant ?? 'share') === 'commission')
                            <td class="px-3 py-2 text-right tabular-nums font-medium text-emerald-700">
                                {{ \App\Services\Property\PropertyMoney::kes($earnings) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                                {{ \App\Services\Property\PropertyMoney::kes(max(0, $ownerShare - $earnings)) }}
                            </td>
                        @elseif (($variant ?? 'share') === 'settlement')
                            <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                                {{ \App\Services\Property\PropertyMoney::kes($earnings) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold text-slate-800 dark:text-slate-100">
                                {{ \App\Services\Property\PropertyMoney::kes(max(0, $ownerShare - $earnings)) }}
                            </td>
                        @else
                            <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                                {{ \App\Services\Property\PropertyMoney::kes($earnings) }}
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ in_array(($variant ?? 'share'), ['commission', 'settlement'], true) ? 5 : 4 }}" class="px-3 py-4 text-center text-slate-500">No monthly collections for this property.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
