<x-property.workspace
    title="Unit performance"
    subtitle="Asking rent on the unit vs contracted rent on the active lease (variance). Vacancy column shows current stretch for vacant units only — not full YTD history."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No units"
    empty-hint="Add units under Properties → Units."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('property.properties.units', absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Manage units</a>
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Leases</a>
                <a href="{{ route('property.listings.vacant', absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-blue-300 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">Vacant listings</a>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('property.properties.performance', array_merge(request()->query(), ['preset' => 'below_ask', 'trend' => 'below_ask']), false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Below ask</a>
                <a href="{{ route('property.properties.performance', array_merge(request()->query(), ['preset' => 'vacant', 'status' => 'vacant', 'trend' => 'vacant']), false) }}" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Vacant only</a>
                <a href="{{ route('property.properties.performance', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Clear presets</a>
            </div>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.properties.performance') }}" class="w-full grid gap-2 sm:grid-cols-2 lg:grid-cols-7">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search unit or property..." class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 lg:col-span-2" />
            <select name="property_id" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="">All properties</option>
                @foreach(($propertyOptions ?? []) as $p)
                    <option value="{{ $p->id }}" @selected((string) ($filters['property_id'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="">All statuses</option>
                <option value="occupied" @selected(($filters['status'] ?? '') === 'occupied')>Occupied</option>
                <option value="vacant" @selected(($filters['status'] ?? '') === 'vacant')>Vacant</option>
                <option value="notice" @selected(($filters['status'] ?? '') === 'notice')>Notice</option>
            </select>
            <select name="trend" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="">All trends</option>
                <option value="below_ask" @selected(($filters['trend'] ?? '') === 'below_ask')>Below ask</option>
                <option value="at_or_above_ask" @selected(($filters['trend'] ?? '') === 'at_or_above_ask')>At/above ask</option>
                <option value="vacant" @selected(($filters['trend'] ?? '') === 'vacant')>Vacant</option>
            </select>
            <input type="hidden" name="preset" value="{{ $filters['preset'] ?? '' }}" />
            <div class="flex items-center gap-2 lg:col-span-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.properties.performance', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
                <a href="{{ route('property.properties.performance', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">CSV</a>
                <a href="{{ route('property.properties.performance', array_merge(request()->query(), ['export' => 'pdf']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">PDF</a>
                <a href="{{ route('property.properties.performance', array_merge(request()->query(), ['export' => 'word']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Word</a>
            </div>
        </form>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Trend breakdown</p>
            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                <a href="{{ route('property.properties.performance', array_merge(request()->query(), ['trend' => 'below_ask']), false) }}" class="rounded-lg border border-amber-200 px-3 py-2 hover:bg-amber-50">
                    <p class="text-amber-700 font-semibold">{{ (int) (($trendBreakdown['below_ask'] ?? 0)) }}</p>
                    <p class="text-xs text-slate-500">Below ask</p>
                </a>
                <a href="{{ route('property.properties.performance', array_merge(request()->query(), ['trend' => 'at_or_above_ask']), false) }}" class="rounded-lg border border-emerald-200 px-3 py-2 hover:bg-emerald-50">
                    <p class="text-emerald-700 font-semibold">{{ (int) (($trendBreakdown['at_or_above_ask'] ?? 0)) }}</p>
                    <p class="text-xs text-slate-500">At/above ask</p>
                </a>
                <a href="{{ route('property.properties.performance', array_merge(request()->query(), ['trend' => 'vacant']), false) }}" class="rounded-lg border border-rose-200 px-3 py-2 hover:bg-rose-50">
                    <p class="text-rose-700 font-semibold">{{ (int) (($trendBreakdown['vacant'] ?? 0)) }}</p>
                    <p class="text-xs text-slate-500">Vacant</p>
                </a>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average vacancy stretch</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((float) ($avgVacantDays ?? 0), 1) }} days</p>
            <p class="mt-1 text-xs text-slate-500">Across currently vacant units in this filtered view.</p>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Showing {{ (int) (($unitsPage ?? null)?->firstItem() ?? 0) }}-{{ (int) (($unitsPage ?? null)?->lastItem() ?? 0) }}
                of {{ (int) (($unitsPage ?? null)?->total() ?? 0) }} units.
            </p>
            <div>
                {{ ($unitsPage ?? null)?->links() }}
            </div>
        </div>
    </x-slot>
</x-property.workspace>
