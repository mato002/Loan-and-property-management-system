<x-property.workspace
    title="Move-in / move-out"
    subtitle="Schedule and complete move events per unit. Checklists and deposits can attach to these rows later."
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No movement events"
    empty-hint="Log a planned move-in or move-out below."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.tenants.movements', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All movements</a>
                <a href="{{ route('property.tenants.movements', array_merge((array) ($filters ?? []), ['preset' => 'planned', 'status' => 'planned']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Planned</a>
                <a href="{{ route('property.tenants.movements', array_merge((array) ($filters ?? []), ['preset' => 'in_progress', 'status' => 'in_progress']), absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">In progress</a>
                <a href="{{ route('property.tenants.movements', array_merge((array) ($filters ?? []), ['preset' => 'done', 'status' => 'done']), absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Done</a>
                <a href="{{ route('property.tenants.movements', array_merge((array) ($filters ?? []), ['preset' => 'move_out', 'movement_type' => 'move_out']), absolute: false) }}" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Move-outs</a>
            </div>
        </div>

        <form method="get" action="{{ route('property.tenants.movements') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Unit, property, notes..." class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                    <select name="property_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach(($propertyOptions ?? []) as $p)
                            <option value="{{ $p->id }}" @selected((string) ($filters['property_id'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Type</label>
                    <select name="movement_type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        <option value="move_in" @selected(($filters['movement_type'] ?? '') === 'move_in')>Move in</option>
                        <option value="move_out" @selected(($filters['movement_type'] ?? '') === 'move_out')>Move out</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['planned', 'in_progress', 'done', 'cancelled'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <input type="hidden" name="preset" value="{{ $filters['preset'] ?? '' }}" />
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.tenants.movements', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
                <a href="{{ route('property.tenants.movements', array_merge((array) ($filters ?? []), ['export' => 'csv']), absolute: false) }}" data-turbo="false" class="rounded-xl border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">CSV</a>
                <a href="{{ route('property.tenants.movements', array_merge((array) ($filters ?? []), ['export' => 'pdf']), absolute: false) }}" data-turbo="false" class="rounded-xl border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">PDF</a>
                <a href="{{ route('property.tenants.movements', array_merge((array) ($filters ?? []), ['export' => 'word']), absolute: false) }}" data-turbo="false" class="rounded-xl border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Word</a>
            </div>
        </form>

        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Handover flow: Move-ins &amp; Move-outs</p>
            <p class="mt-1 text-sm text-slate-600">Use this page for checklists and handover notes. Leases can auto-log move-ins/move-outs; you can also log manual events here.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Go to Leases
                    <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.properties.units', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Unit status
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        @if (session('success'))
            <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p class="text-sm text-rose-700 dark:text-rose-400">{{ session('error') }}</p>
        @endif

        <form method="post" action="{{ route('property.tenants.movements.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Log movement</h3>
            @if (! $tenantMoveInEnabled)
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                    Move-in form is disabled in System setup. You can still log move-out events.
                </p>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <x-property.quick-create-select
                        name="property_unit_id"
                        :required="true"
                        :options="collect($units)->map(fn($u) => ['value' => $u->id, 'label' => $u->property->name.' / '.$u->label, 'selected' => (string) old('property_unit_id') === (string) $u->id])->all()"
                        :create="[
                            'mode' => 'link',
                            'link' => route('property.properties.units', absolute: false),
                        ]"
                    />
                    @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Type</label>
                    <select name="movement_type" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="move_in" @selected(old('movement_type') === 'move_in')>Move in</option>
                        <option value="move_out" @selected(old('movement_type') === 'move_out')>Move out</option>
                    </select>
                    @error('movement_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (['planned', 'in_progress', 'done', 'cancelled'] as $st)
                            <option value="{{ $st }}" @selected(old('status', 'planned') === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Scheduled</label>
                    <input type="date" name="scheduled_on" value="{{ old('scheduled_on') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('scheduled_on')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Completed</label>
                    <input type="date" name="completed_on" value="{{ old('completed_on') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('completed_on')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save</button>
        </form>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Movement type summary</p>
            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <div class="rounded-lg border border-blue-200 px-3 py-2">
                    <p class="text-blue-700 font-semibold">{{ (int) (($typeSummary['move_in'] ?? 0)) }}</p>
                    <p class="text-xs text-slate-500">Move-ins</p>
                </div>
                <div class="rounded-lg border border-rose-200 px-3 py-2">
                    <p class="text-rose-700 font-semibold">{{ (int) (($typeSummary['move_out'] ?? 0)) }}</p>
                    <p class="text-xs text-slate-500">Move-outs</p>
                </div>
                <div class="rounded-lg border border-amber-200 px-3 py-2">
                    <p class="text-amber-700 font-semibold">{{ (int) (($typeSummary['pending'] ?? 0)) }}</p>
                    <p class="text-xs text-slate-500">Pending</p>
                </div>
                <div class="rounded-lg border border-emerald-200 px-3 py-2">
                    <p class="text-emerald-700 font-semibold">{{ (int) (($typeSummary['done'] ?? 0)) }}</p>
                    <p class="text-xs text-slate-500">Done</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">Upcoming in 7 days: <span class="font-semibold text-slate-700">{{ (int) ($upcoming7 ?? 0) }}</span></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">6-month trend</h3>
                <p class="mt-1 text-xs text-slate-500">Movement completion trend by month.</p>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Month</th>
                        <th class="px-4 py-3">Move-ins</th>
                        <th class="px-4 py-3">Move-outs</th>
                        <th class="px-4 py-3">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($trend ?? []) as $row)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">{{ $row['label'] }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ (int) $row['move_in'] }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ (int) $row['move_out'] }}</td>
                            <td class="px-4 py-3 tabular-nums {{ ((int) $row['net']) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ ((int) $row['net']) >= 0 ? '+' : '' }}{{ (int) $row['net'] }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No trend data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Showing {{ (int) (($movementsPage ?? null)?->firstItem() ?? 0) }}-{{ (int) (($movementsPage ?? null)?->lastItem() ?? 0) }}
                of {{ (int) (($movementsPage ?? null)?->total() ?? 0) }} events.
            </p>
            <div>
                {{ ($movementsPage ?? null)?->links() }}
            </div>
        </div>
    </x-slot>
</x-property.workspace>
