<x-property.workspace
    title="Utilities &amp; charges"
    subtitle="Monthly-style charge lines per unit (water, service charge, etc.). Invoice integration can follow; rent stays on the rent roll."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="[]"
    empty-title="No utility charges"
    empty-hint="Add a line below — amounts are stored separately from core rent."
>
    <x-slot name="toolbar">
        <input
            type="search"
            data-table-filter="parent"
            autocomplete="off"
            placeholder="Search label or unit…"
            class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
        />
    </x-slot>

    <x-slot name="above">
        <form method="post" action="{{ route('property.revenue.utilities.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add charge line</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                <select name="property_unit_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" @selected(old('property_unit_id') == $u->id)>{{ $u->property->name }} — {{ $u->label }}</option>
                    @endforeach
                </select>
                @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label</label>
                <input type="text" name="label" value="{{ old('label') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Water / Service charge" />
                @error('label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save charge</button>
        </form>
    </x-slot>

    <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Label</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Unit</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Added</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Amount</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Notes</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charges as $c)
                    @php
                        $ft = mb_strtolower($c->label.' '.$c->unit->property->name.' '.$c->unit->label.' '.$c->created_at->format('Y-m'));
                    @endphp
                    <tr
                        class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                        data-filter-text="{{ e($ft) }}"
                    >
                        <td class="px-3 sm:px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $c->label }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">{{ $c->unit->property->name }} / {{ $c->unit->label }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $c->created_at->format('Y-m-d') }}</td>
                        <td class="px-3 sm:px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $c->amount) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400 max-w-xs truncate">{{ $c->notes ?? '—' }}</td>
                        <td class="px-3 sm:px-4 py-3">
                            <form method="post" action="{{ route('property.revenue.utilities.destroy', $c) }}" onsubmit="return confirm('Delete this charge line?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-14 text-center text-slate-600 dark:text-slate-400">No utility charges yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
