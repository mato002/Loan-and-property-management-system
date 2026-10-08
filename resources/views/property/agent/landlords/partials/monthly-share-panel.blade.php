@php
    $months = is_array($months ?? null) ? $months : [];
    $currentMonth = (string) ($currentMonth ?? '');
    $variant = (string) ($variant ?? 'share');
    $shareTotals = [
        'collected' => 0.0,
        'owner_share' => 0.0,
        'earnings' => 0.0,
        'expenses' => 0.0,
        'payable' => 0.0,
        'net' => 0.0,
        'remitted' => 0.0,
        'pending' => 0.0,
    ];
    foreach ($months as $monthRow) {
        if (! is_array($monthRow)) {
            continue;
        }
        $rowOwnerShare = (float) ($monthRow['owner_share'] ?? 0);
        $rowEarnings = (float) ($monthRow['agent_earning'] ?? 0);
        $rowExpenses = (float) ($monthRow['expenses'] ?? 0);
        $shareTotals['collected'] += (float) ($monthRow['gross_collected'] ?? 0);
        $shareTotals['owner_share'] += $rowOwnerShare;
        $shareTotals['earnings'] += $rowEarnings;
        $shareTotals['expenses'] += $rowExpenses;
        $shareTotals['payable'] += max(0, $rowOwnerShare - $rowEarnings - $rowExpenses);
        $shareTotals['net'] += max(0, $rowOwnerShare - $rowEarnings);
        $shareTotals['remitted'] += (float) ($monthRow['paid_share'] ?? 0);
        $shareTotals['pending'] += (float) ($monthRow['pending_share'] ?? 0);
    }
    $payableGap = round($shareTotals['remitted'] - $shareTotals['payable'], 2);
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
                    @if ($variant === 'commission')
                        <th class="px-3 py-2 text-right font-semibold">Commission</th>
                        <th class="px-3 py-2 text-right font-semibold">Net to landlord</th>
                    @elseif ($variant === 'settlement')
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
                        @if ($variant === 'commission')
                            <td class="px-3 py-2 text-right tabular-nums font-medium text-emerald-700">
                                {{ \App\Services\Property\PropertyMoney::kes($earnings) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-800 dark:text-slate-100">
                                {{ \App\Services\Property\PropertyMoney::kes(max(0, $ownerShare - $earnings)) }}
                            </td>
                        @elseif ($variant === 'settlement')
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
                        <td colspan="{{ $variant === 'settlement' ? 8 : ($variant === 'commission' ? 7 : 6) }}" class="px-3 py-4 text-center text-slate-500">No monthly collections for this property.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($months !== [])
                <tfoot class="sticky bottom-0 border-t-2 border-slate-300 bg-slate-100 text-slate-900 dark:border-slate-500 dark:bg-slate-800 dark:text-slate-100">
                    <tr>
                        <td class="px-3 py-2 font-semibold">Total</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['collected']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['owner_share']) }}</td>
                        @if ($variant === 'commission')
                            <td class="px-3 py-2 text-right tabular-nums font-semibold text-emerald-700">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['earnings']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['net']) }}</td>
                        @elseif ($variant === 'settlement')
                            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['earnings']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['expenses']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['payable']) }}</td>
                        @else
                            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['earnings']) }}</td>
                        @endif
                        <td class="px-3 py-2 text-right tabular-nums font-semibold {{ $variant === 'settlement' && $payableGap > 0.009 ? 'text-rose-700 dark:text-rose-300' : '' }}">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['remitted']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($shareTotals['pending']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    @if ($variant === 'settlement' && $months !== [])
        <p class="mt-2 text-[11px] font-medium {{ abs($payableGap) <= 0.009 ? 'text-emerald-700 dark:text-emerald-300' : ($payableGap > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-amber-800 dark:text-amber-200') }}">
            @if (abs($payableGap) <= 0.009)
                Totals match: paid to landlord equals the amount payable.
            @elseif ($payableGap > 0)
                Paid to landlord is {{ \App\Services\Property\PropertyMoney::kes($payableGap) }} more than the amount payable. That extra is an advance.
            @else
                Amount payable is {{ \App\Services\Property\PropertyMoney::kes(abs($payableGap)) }} more than what has been paid. That difference is still unpaid.
            @endif
        </p>
    @endif
</div>
