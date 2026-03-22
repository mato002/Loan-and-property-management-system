<x-property.workspace
    title="Maintenance requests"
    subtitle="Intake from agents — urgency, unit, and description."
    back-route="property.maintenance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No maintenance requests"
    empty-hint="Log a request below; create a job from the Jobs screen once scoped."
>
    <x-slot name="above">
        <form method="post" action="{{ route('property.maintenance.requests.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New request</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                <select name="property_unit_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" @selected(old('property_unit_id') == $u->id)>{{ $u->property->name }} / {{ $u->label }}</option>
                    @endforeach
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
    </x-slot>

    <x-slot name="toolbar">
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Priority: All</option>
            <option value="normal">Normal</option>
            <option value="urgent">Urgent</option>
            <option value="emergency">Emergency</option>
        </select>
    </x-slot>
</x-property.workspace>
