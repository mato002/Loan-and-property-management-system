@if ($canManage ?? false)
    <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm max-w-2xl">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Assign property</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Link an unassigned property from this agent&apos;s workspace to this field officer.</p>
        @if (($unassignedProperties ?? []) === [])
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">All properties in this workspace are already assigned to a field officer.</p>
        @else
            <form method="post" action="{{ route('property.hr.employees.properties.assign', $employee, false) }}" class="mt-3 flex flex-col sm:flex-row gap-2 items-stretch sm:items-end">
                @csrf
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                    <select name="property_id" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select property…</option>
                        @foreach ($unassignedProperties as $property)
                            <option value="{{ $property['id'] }}" @selected((string) old('property_id') === (string) $property['id'])>{{ $property['name'] }}@if ($property['city'] !== '—') — {{ $property['city'] }}@endif</option>
                        @endforeach
                    </select>
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">Assign</button>
            </form>
        @endif
    </div>
@endif

<div class="overflow-x-auto w-full min-w-0 property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm">
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200 dark:[&_th]:border-slate-700 dark:[&_td]:border-slate-700">
        <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <tr>
                <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Property</th>
                <th class="px-3 sm:px-4 py-3 whitespace-nowrap">City</th>
                <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Units</th>
                <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Tenants</th>
                <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Rent portfolio</th>
                <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assignedProperties as $property)
                <tr class="border-t border-slate-100 dark:border-slate-700/80">
                    <td class="px-3 sm:px-4 py-3">
                        <a href="{{ $property['show_url'] }}" data-turbo-frame="property-main" class="font-medium text-slate-900 dark:text-white hover:text-blue-700 dark:hover:text-blue-400 break-words">{{ $property['name'] }}</a>
                    </td>
                    <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-300">{{ $property['city'] }}</td>
                    <td class="px-3 sm:px-4 py-3">{{ $property['units'] }}</td>
                    <td class="px-3 sm:px-4 py-3">{{ $property['tenants'] }}</td>
                    <td class="px-3 sm:px-4 py-3">{{ \App\Services\Property\PropertyMoney::kes((float) $property['rent']) }}</td>
                    <td class="px-3 sm:px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ $property['show_url'] }}" data-turbo-frame="property-main" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">View</a>
                            @if ($canManage ?? false)
                                <form
                                    method="post"
                                    action="{{ route('property.hr.employees.properties.detach', $employee, false) }}"
                                    data-swal-title="Unassign property?"
                                    data-swal-confirm="Remove this property from {{ $employee->full_name }}?"
                                    data-swal-confirm-text="Yes, unassign"
                                    class="inline"
                                >
                                    @csrf
                                    <input type="hidden" name="property_id" value="{{ $property['id'] }}" />
                                    <button type="submit" class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Unassign</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No properties assigned yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
