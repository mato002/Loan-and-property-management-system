@php
    $showPenaltyFormByDefault = $errors->hasAny(['name','scope','trigger_event','grace_days','formula','percent','amount','cap','effective_from','is_active']);
@endphp
<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Late payment penalties"
    subtitle="Set how much to charge when rent is paid after the due date. The system applies this to every property."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No late payment rules yet"
    empty-hint="Add a rule to say how much to charge after the rent due date."
>
    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from(['showPenaltyForm' => $showPenaltyFormByDefault]) !!}" ></x-slot>

    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            data-property-modal-open="showPenaltyForm" @click="showPenaltyForm = true"
        >
            <i class="fa-solid fa-percent" aria-hidden="true"></i>
            <span>Add late payment rule</span>
        </button>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showPenaltyForm"
            close="showPenaltyForm = false"
            name="penalty-rule-create"
            title="New late payment rule"
            max-width="3xl"
        >
        <form
            method="post"
            action="{{ route('property.revenue.penalties.store') }}"
            class="space-y-3"
            x-data="{ formula: @js(old('formula', 'percent_of_rent')) }"
        >
            @csrf
            <input type="hidden" name="scope" value="global" />
            <input type="hidden" name="trigger_event" value="days_after_due" />
            <p class="text-sm text-slate-600 dark:text-slate-300">This rule applies to every property. The charge starts after the rent due date.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rule name</label>
                    <input type="text" name="name" value="{{ old('name', 'Late payment penalty') }}" required placeholder="Late payment penalty" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Days to wait after the due date</label>
                    <input type="number" name="grace_days" value="{{ old('grace_days', 0) }}" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-[11px] text-slate-500">0 means the charge can start the day after rent is due.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">How to calculate</label>
                    <select name="formula" x-model="formula" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (\App\Models\PmPenaltyRule::formulaOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="formula !== 'flat'" x-cloak>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Percent of unpaid rent</label>
                    <input type="number" name="percent" value="{{ old('percent') }}" step="0.01" min="0" max="100" placeholder="10" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-[11px] text-slate-500">Example: 10 means 10% of the unpaid rent.</p>
                </div>
                <div x-show="formula === 'flat' || formula === 'percent_plus_flat'" x-cloak>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed amount (KES)</label>
                    <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" placeholder="500" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">When to charge</label>
                    <select name="compounding_mode" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (\App\Models\PmPenaltyRule::compoundingOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(old('compounding_mode', 'simple') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">Charging for every extra day makes the amount grow quickly.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Don't charge more than (KES)</label>
                    <input type="number" name="cap" value="{{ old('cap') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-[11px] text-slate-500">Leave blank for no limit on one charge.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Total limit on one bill (KES)</label>
                    <input type="number" name="cumulative_cap" value="{{ old('cumulative_cap') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-[11px] text-slate-500">Leave blank if later charges on the same bill can keep adding up.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Start using this rule from</label>
                    <input type="date" name="effective_from" value="{{ old('effective_from') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-blue-600" @checked(old('is_active', true)) />
                    <span class="text-sm text-slate-700 dark:text-slate-300">Use this rule</span>
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
