@php
    $months = is_array($months ?? null) ? $months : [];
    $currentMonth = (string) ($currentMonth ?? '');
    $propertyUrl = (string) ($propertyUrl ?? '#');
    $propertyName = (string) ($propertyName ?? '');
@endphp
<div x-data="{ open: false }" class="min-w-0" data-row-ignore-click>
    <div class="flex items-center gap-2 min-w-0">
        <button
            type="button"
            @click.stop="open = !open"
            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-base font-bold leading-none text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200"
            :aria-expanded="open.toString()"
            title="Monthly share breakdown"
        >
            <span x-text="open ? '−' : '+'"></span>
        </button>
        <a href="{{ $propertyUrl }}" data-turbo-frame="property-main" class="font-medium text-slate-900 dark:text-white hover:text-blue-700 break-words min-w-0">{{ $propertyName }}</a>
    </div>
    <div x-show="open" x-cloak class="mt-2 max-h-72 overflow-auto rounded-lg border border-slate-200 bg-white dark:border-slate-600 dark:bg-gray-800">
        <table class="w-full text-xs">
            <thead class="sticky top-0 bg-slate-50 text-slate-500 dark:bg-slate-900/80">
                <tr>
                    <th class="px-3 py-1.5 text-left font-semibold">Month</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Owner share</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Your earnings</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($months as $m)
                    @php $isCurrent = (string) ($m['month'] ?? '') === $currentMonth; @endphp
                    <tr class="border-t border-slate-100 dark:border-slate-700 {{ $isCurrent ? 'bg-indigo-50 dark:bg-indigo-950/40' : '' }}">
                        <td class="px-3 py-1.5 whitespace-nowrap {{ $isCurrent ? 'font-semibold text-indigo-800 dark:text-indigo-200' : 'text-slate-700 dark:text-slate-200' }}">
                            {{ $m['month_label'] ?? ($m['month'] ?? '—') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-slate-800 dark:text-slate-100">
                            {{ \App\Services\Property\PropertyMoney::kes((float) ($m['owner_share'] ?? 0)) }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-slate-800 dark:text-slate-100">
                            {{ \App\Services\Property\PropertyMoney::kes((float) ($m['agent_earning'] ?? 0)) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-4 text-center text-slate-500">No monthly collections for this property.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
