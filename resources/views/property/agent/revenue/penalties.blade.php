<x-property.workspace
    title="Penalties &amp; auto rules"
    subtitle="Rules are stored here; automatic posting to invoices is still a separate integration step."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No penalty rules"
    empty-hint="Add a rule below. Applied / waived counts stay at zero until billing automation uses this table."
>
    <x-slot name="above">
        @if (session('success'))
            <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.revenue.penalties.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
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
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Cap (KES)</label>
                    <input type="number" name="cap" value="{{ old('cap') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
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
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.penalties', absolute: false) }}" class="w-full flex flex-wrap items-end gap-2">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Search rules…" class="w-full min-w-0 sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Status: All</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="off" @selected(($filters['status'] ?? '') === 'off')>Off</option>
            </select>
            <select name="scope" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Scope: All</option>
                @foreach (($scopes ?? []) as $scope)
                    <option value="{{ $scope }}" @selected(($filters['scope'] ?? '') === (string) $scope)>{{ $scope }}</option>
                @endforeach
            </select>
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Sort: Name</option>
                <option value="scope" @selected(($filters['sort'] ?? '') === 'scope')>Sort: Scope</option>
                <option value="trigger_event" @selected(($filters['sort'] ?? '') === 'trigger_event')>Sort: Trigger</option>
                <option value="effective_from" @selected(($filters['sort'] ?? '') === 'effective_from')>Sort: Effective</option>
                <option value="id" @selected(($filters['sort'] ?? '') === 'id')>Sort: ID</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="asc" @selected(($filters['dir'] ?? 'asc') === 'asc')>Asc</option>
                <option value="desc" @selected(($filters['dir'] ?? '') === 'desc')>Desc</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.revenue.penalties', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.revenue.penalties', array_merge(request()->query(), ['export' => 'csv']), false),
                'xlsUrl' => route('property.revenue.penalties', array_merge(request()->query(), ['export' => 'xls']), false),
                'pdfUrl' => route('property.revenue.penalties', array_merge(request()->query(), ['export' => 'pdf']), false),
            ])
        </form>
    </x-slot>

    <div class="space-y-2">
        <p class="text-xs font-medium text-slate-600 dark:text-slate-400">Remove rule</p>
        <ul class="flex flex-wrap gap-2">
            @foreach ($penaltyRules as $rule)
                <li>
                    <form method="post" action="{{ route('property.revenue.penalties.destroy', $rule) }}" onsubmit="return confirm('Delete this rule?');" class="inline">
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
