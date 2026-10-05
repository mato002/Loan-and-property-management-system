@php
    $months = is_array($months ?? null) ? $months : [];
    $currentMonth = (string) ($currentMonth ?? '');
@endphp
<div class="px-1 py-1">
    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Monthly share breakdown</p>
    <p class="mb-2 text-[11px] text-slate-500">Collected is what has been paid on that month's bills, including money received before the month. Paid to landlord is remittances for that month. Pending is what is still owed to the landlord after your earnings and that remittance.</p>
    <div class="max-h-72 overflow-auto rounded-lg border border-slate-200 bg-white dark:border-slate-600 dark:bg-gray-800">
        <table class="w-full min-w-full text-xs">
            <thead class="sticky top-0 bg-slate-50 text-slate-500 dark:bg-slate-900/80">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Month</th>
                    <th class="px-3 py-2 text-right font-semibold">Collected</th>
                    <th class="px-3 py-2 text-right font-semibold">Landlord share</th>
                    @if (($variant ?? 'share') === 'commission')
                        <th class="px-3 py-2 text-right font-semibold">Commission</th>
                        <th class="px-3 py-2 text-right font-semibold">Net to landlord</th>
                    @elseif (($variant ?? 'share') === 'settlement')
                        <th class="px-3 py-2 text-right font-semibold">Mgmt fee</th>
                        <th class="px-3 py-2 text-right font-semibold">Expenses</th>
                        <th class="px-3 py-2 text-right font-semibold">Amount payable</th>
                    @else
                        <th class="px-3 py-2 text-right font-semibold">Your earnings</th>
                    @endif
                    <th class="px-3 py-2 text-right font-semibold">Paid to landlord</th>
                    <th class="px-3 py-2 text-right font-semibold">Pending</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($months as $m)
                    @php
                        $isCurrent = (string) ($m['month'] ?? '') === $currentMonth;
                        $ownerShare = (float) ($m['owner_share'] ?? 0);
                        $earnings = (float) ($m['agent_earning'] ?? 0);
                        $collected = (float) ($m['gross_collected'] ?? 0);
                        $expenses = (float) ($m['expenses'] ?? 0);
                        $remitted = (float) ($m['paid_share'] ?? 0);
                        $payable = max(0, $ownerShare - $earnings - $expenses);
                        $overRemitted = $remitted > ($payable + 0.009);
                    @endphp
                    <tr class="border-t border-slate-100 dark:border-slate-700 {{ $isCurrent ? 'bg-indigo-50 dark:bg-indigo-950/40' : '' }}">
                        <td class="px-3 py-2 whitespace-nowrap {{ $isCurrent ? 'font-semibold text-indigo-800 dark:text-indigo-200' : 'text-slate-700 dark:text-slate-200' }}">
                            {{ $m['month_label'] ?? ($m['month'] ?? '—') }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                            {{ \App\Services\Property\PropertyMoney::kes($collected) }}
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
                            <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                                {{ \App\Services\Property\PropertyMoney::kes($expenses) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold text-slate-800 dark:text-slate-100">
                                {{ \App\Services\Property\PropertyMoney::kes($payable) }}
                            </td>
                        @else
                            <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                                {{ \App\Services\Property\PropertyMoney::kes($earnings) }}
                            </td>
                        @endif
                        <td class="px-3 py-2 text-right tabular-nums {{ $overRemitted ? 'font-semibold text-rose-700 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-200' }}" @if ($overRemitted) title="This remittance is higher than the month's receipts. It can include a balance brought forward." @endif>
                            {{ \App\Services\Property\PropertyMoney::kes($remitted) }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums {{ ((float) ($m['pending_share'] ?? 0)) > 0.009 ? 'text-amber-800 dark:text-amber-200 font-medium' : 'text-slate-800 dark:text-slate-100' }}" title="Landlord share minus the management fee, minus what was already paid.">
                            {{ \App\Services\Property\PropertyMoney::kes((float) ($m['pending_share'] ?? 0)) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($variant ?? 'share') === 'settlement' ? 8 : ((($variant ?? 'share') === 'commission') ? 7 : 6) }}" class="px-3 py-4 text-center text-slate-500">No monthly collections for this property.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
