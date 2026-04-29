<x-property-layout>
    <x-slot name="header">Audit trail</x-slot>

    <x-property.page
        title="Audit trail"
        subtitle="Timeline of landlord actions captured in the portal (approvals, payout settings, withdrawals, and preferences)."
    >
        <div class="grid gap-3 sm:grid-cols-3 mb-4">
            @foreach (($stats ?? []) as $s)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $s['label'] }}</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $s['value'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $s['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="get" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                <input
                    type="search"
                    name="q"
                    value="{{ $q ?? '' }}"
                    placeholder="action key, notes, context..."
                    class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[220px]"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Action type</label>
                <select name="action_key" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
                    <option value="">All actions</option>
                    @foreach (($actionKeys ?? collect()) as $key)
                        <option value="{{ $key }}" @selected(($actionKey ?? '') === $key)>{{ $key }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/60">Apply</button>
            <a href="{{ route('property.landlord.audit_trail.export', array_filter(['action_key' => $actionKey ?? null, 'q' => $q ?? null])) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Download CSV</a>
        </form>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 overflow-hidden shadow-sm">
            @if (($actions->total() ?? 0) === 0)
                <p class="p-5 text-sm text-slate-500">No actions recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="py-2 px-4 whitespace-normal break-words">When</th>
                                <th class="py-2 px-4 whitespace-normal break-words">Action key</th>
                                <th class="py-2 px-4 whitespace-normal break-words">Notes</th>
                                <th class="py-2 px-4 whitespace-normal break-words">Context</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($actions as $a)
                                <tr class="border-b border-slate-100 dark:border-slate-700/70 align-top">
                                    <td class="py-2 px-4 whitespace-normal break-words">{{ optional($a->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="py-2 px-4 font-medium">{{ $a->action_key }}</td>
                                    <td class="py-2 px-4">{{ $a->notes ?: '—' }}</td>
                                    <td class="py-2 px-4">
                                        @if (is_array($a->context) && $a->context !== [])
                                            <pre class="text-xs text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{{ json_encode($a->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-slate-700">
                    {{ $actions->links() }}
                </div>
            @endif
        </div>
    </x-property.page>
</x-property-layout>
