@php
    $showRequestFormByDefault = $errors->hasAny(['property_id', 'property_unit_id', 'category', 'urgency', 'description']);
@endphp
<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Maintenance requests"
    subtitle="Intake from agents — urgency, unit, and description."
    back-route="property.maintenance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No maintenance requests"
    empty-hint="Log a request below; create a job from the Jobs screen once scoped."
>
    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from(['showRequestForm' => $showRequestFormByDefault]) !!}" ></x-slot>

    @if ($maintenanceEnabled)
        <x-slot name="actions">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                data-property-modal-open="showRequestForm" @click="showRequestForm = true"
            >
                <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                <span>Add maintenance request</span>
            </button>
        </x-slot>

        <x-slot name="modals">
            <x-property.modal
                show="showRequestForm"
                close="showRequestForm = false"
                name="maintenance-request-create"
                title="New maintenance request"
                max-width="2xl"
            >
            <form
                method="post"
                action="{{ route('property.maintenance.requests.store') }}"
                class="space-y-3"
                x-data="{
                    propertyId: @js((string) old('property_id')),
                    unitId: @js((string) old('property_unit_id')),
                    units: @js(collect($units)->map(fn($u) => [
                        'id' => (string) $u->id,
                        'property_id' => (string) $u->property_id,
                        'label' => $u->label,
                        'property_name' => $u->property->name,
                    ])->values()),
                    get filteredUnits() {
                        return this.units.filter(u => u.property_id === this.propertyId);
                    }
                }"
            >
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New request</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                    <select
                        name="property_id"
                        x-model="propertyId"
                        @change="unitId = ''"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                    >
                        <option value="">Select property...</option>
                        @foreach (collect($units)->pluck('property')->unique('id')->sortBy('name') as $property)
                            <option value="{{ $property->id }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <select
                        name="property_unit_id"
                        x-model="unitId"
                        :disabled="!propertyId"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 disabled:bg-slate-100 disabled:text-slate-500"
                    >
                        <option value="">Select unit...</option>
                        <template x-for="u in filteredUnits" :key="u.id">
                            <option :value="u.id" x-text="u.label"></option>
                        </template>
                    </select>
                    @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                    <input type="text" name="category" value="{{ old('category') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Plumbing, electrical…" />
                    @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Urgency</label>
                    <select name="urgency" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="normal" @selected(old('urgency', 'normal') === 'normal')>Normal</option>
                        <option value="urgent" @selected(old('urgency') === 'urgent')>Urgent</option>
                        <option value="emergency" @selected(old('urgency') === 'emergency')>Emergency</option>
                    </select>
                    @error('urgency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                    <textarea name="description" rows="3" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('description') }}</textarea>
                    @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit request</button>
            </form>
            </x-property.modal>
        </x-slot>
    @endif

    <x-slot name="tabs">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('property.maintenance.requests', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All requests</a>
            <a href="{{ route('property.maintenance.requests', array_merge((array) ($filters ?? []), ['status' => 'open']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Open</a>
            <a href="{{ route('property.maintenance.requests', array_merge((array) ($filters ?? []), ['status' => 'in_progress']), absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">In progress</a>
            <a href="{{ route('property.maintenance.requests', array_merge((array) ($filters ?? []), ['status' => 'done']), absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Done</a>
            @include('property.agent.partials.table_export_dropdown', ['route' => 'property.maintenance.requests.export', 'query' => (array) ($filters ?? [])])
        </div>
    </x-slot>

    @if ($workflowAutoAssignTickets || ! $maintenanceEnabled)
        <x-slot name="secondary">
            @if ($workflowAutoAssignTickets)
                <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200 max-w-2xl">
                    Workflow automation is ON: new requests are auto-routed to triage.
                </p>
            @endif
            @if (! $maintenanceEnabled)
                <div class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200 max-w-2xl">
                    Maintenance request form is currently disabled in System setup.
                </div>
            @endif
        </x-slot>
    @endif

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.maintenance_requests', ['filters' => $filters])
    </x-slot>

    @if (isset($requestPager))
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $requestPager->firstItem() ?? 0 }}-{{ $requestPager->lastItem() ?? 0 }} of {{ $requestPager->total() }} requests.
                </p>
                <div>
                    {{ $requestPager->onEachSide(1)->links() }}
                </div>
            </div>
        </x-slot>
    @endif
</x-property.workspace>
