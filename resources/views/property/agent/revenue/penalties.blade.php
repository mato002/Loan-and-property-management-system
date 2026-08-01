@php
    $showPenaltyFormByDefault = $errors->hasAny(['name','scope','trigger_event','grace_days','formula','percent','amount','cap','effective_from','is_active']);
@endphp
<div
    x-data="{ showPenaltyForm: @js($showPenaltyFormByDefault) }"
    class="w-full min-w-0"
    data-property-page-modals
>
<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Penalties &amp; auto rules"
    subtitle="Rules are stored here; automatic posting to invoices is still a separate integration step."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No penalty rules"
    empty-hint="Add a rule below. Applied / waived counts stay at zero until billing automation uses this table."
>
    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            @click="showPenaltyForm = true"
        >
            <i class="fa-solid fa-percent" aria-hidden="true"></i>
            <span>Add penalty rule</span>
        </button>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showPenaltyForm"
            close="showPenaltyForm = false"
            name="penalty-rule-create"
            title="New penalty rule"
            max-width="3xl"
        >
        <form method="post" action="{{ route('property.revenue.penalties.store') }}" class="space-y-3">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New rule</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Scope</label>
                    <input type="text" name="scope" value="{{ old('scope', 'global') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Trigger</label>
                    <input type="text" name="trigger_event" value="{{ old('trigger_event', 'days_after_due') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Grace days</label>
                    <input type="number" name="grace_days" value="{{ old('grace_days', 0) }}" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Formula</label>
                    <input type="text" name="formula" value="{{ old('formula', 'percent_of_rent') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Percent</label>
                    <input type="number" name="percent" value="{{ old('percent') }}" step="0.0001" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Flat amount (KES)</label>
                    <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Compounding mode</label>
                    <select name="compounding_mode" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="simple" @selected(old('compounding_mode', 'simple') === 'simple')>Simple (single period)</option>
                        <option value="daily_compound" @selected(old('compounding_mode') === 'daily_compound')>Daily compound</option>
                        <option value="one_shot" @selected(old('compounding_mode') === 'one_shot')>One-shot (once per invoice)</option>
                    </select>
                    <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-400">Daily compounding grows penalty exponentially with overdue days.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Cap (KES)</label>
                    <input type="number" name="cap" value="{{ old('cap') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Cumulative cap (KES)</label>
                    <input type="number" name="cumulative_cap" value="{{ old('cumulative_cap') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-[11px] text-slate-500">Optional lifetime cap per invoice for this rule.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Effective from</label>
                    <input type="date" name="effective_from" value="{{ old('effective_from') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-blue-600" @checked(old('is_active', true)) />
                    <span class="text-sm text-slate-700 dark:text-slate-300">Active</span>
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save rule</button>
        </form>
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.penalties', [
            'filters' => $filters,
            'scopes' => $scopes ?? [],
        ])
    </x-slot>

    <div class="space-y-2">
        <p class="text-xs font-medium text-slate-600 dark:text-slate-400">Remove rule</p>
        <ul class="flex flex-wrap gap-2">
            @foreach ($penaltyRules as $rule)
                <li>
                    <form method="post" action="{{ route('property.revenue.penalties.destroy', $rule) }}" data-swal-confirm="Delete this rule?" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-red-200 dark:border-red-900/50 px-2 py-1 text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/30">{{ $rule->name }} ×</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
    <x-slot name="footer">
        @isset($paginator)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} rule(s)
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
    </x-slot>
</x-property.workspace>
</div>
