@php
    $permission = $permission ?? null;
    if (! $permission) {
        return;
    }
    $isEditing = (int) ($editingPermissionId ?? 0) === (int) $permission->id;
    $groupLabel = trim((string) ($permission->group ?? '')) !== '' ? $permission->group : 'general';
    $groupKey = strtolower($groupLabel);
    $statusLabel = (int) $permission->roles_count > 0 ? 'In use' : 'Unassigned';
    $statusClass = (int) $permission->roles_count > 0
        ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-800'
        : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600';
@endphp

{{-- Desktop table row (view) --}}
<tr
    data-permission-row
    data-permission-id="{{ $permission->id }}"
    data-name="{{ strtolower($permission->name) }}"
    data-key="{{ strtolower($permission->key) }}"
    data-group="{{ strtolower($groupLabel) }}"
    data-roles="{{ (int) $permission->roles_count }}"
    class="hidden md:table-row border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
    x-show="editingId !== {{ (int) $permission->id }} && rowVisible($el) && rowGroupVisible('{{ $groupKey }}')"
>
    <td class="px-4 py-3 align-top">
        <p class="font-semibold text-slate-900 dark:text-white text-sm">{{ $permission->name }}</p>
        @if (trim((string) ($permission->description ?? '')) !== '')
            <p class="mt-0.5 text-xs text-slate-500 line-clamp-1">{{ $permission->description }}</p>
        @endif
    </td>
    <td class="px-4 py-3 align-top">
        <code class="inline-block max-w-[14rem] truncate rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-mono text-slate-700 dark:bg-slate-900 dark:text-slate-300" title="{{ $permission->key }}">{{ $permission->key }}</code>
    </td>
    <td class="px-4 py-3 align-top">
        <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200">{{ $groupLabel }}</span>
    </td>
    <td class="px-4 py-3 align-top">
        <span class="inline-flex min-w-[2rem] justify-center rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">{{ $permission->roles_count }}</span>
    </td>
    <td class="px-4 py-3 align-top">
        <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
    </td>
    <td class="px-4 py-3 align-top text-right">
        <x-property.action-menu label="Actions" width="w-44">
            <button type="button" @click="startEdit({{ (int) $permission->id }})" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Edit</button>
            <form method="post" action="{{ route('property.settings.system_setup.access.permissions.destroy', $permission) }}" data-swal-confirm="Delete this permission?" class="border-t border-slate-100 dark:border-slate-700">
                @csrf
                @method('delete')
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-medium text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30">Delete</button>
            </form>
        </x-property.action-menu>
    </td>
</tr>

{{-- Desktop edit row --}}
<tr
    class="hidden md:table-row border-t border-blue-100 bg-blue-50/40 dark:border-blue-900/50 dark:bg-blue-950/20"
    x-show="editingId === {{ (int) $permission->id }}"
    x-cloak
>
    <td colspan="6" class="px-4 py-4">
        <form method="post" action="{{ route('property.settings.system_setup.access.permissions.update', $permission) }}" class="space-y-3">
            @csrf
            @method('patch')
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                    <input type="text" name="name" value="{{ old('name', $permission->name) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Key</label>
                    <input type="text" name="key" value="{{ old('key', $permission->key) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 font-mono text-sm px-3 py-2" />
                    @error('key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Group</label>
                    <input type="text" name="group" value="{{ old('group', $permission->group) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('group')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                    <input type="text" name="description" value="{{ old('description', $permission->description) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="button" @click="cancelEdit()" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800">Cancel</button>
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Save changes</button>
            </div>
        </form>
    </td>
</tr>

{{-- Mobile card (view) --}}
<div
    data-permission-row
    data-permission-id="{{ $permission->id }}"
    data-name="{{ strtolower($permission->name) }}"
    data-key="{{ strtolower($permission->key) }}"
    data-group="{{ strtolower($groupLabel) }}"
    data-roles="{{ (int) $permission->roles_count }}"
    class="md:hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 shadow-sm"
    x-show="editingId !== {{ (int) $permission->id }} && rowVisible($el) && rowGroupVisible('{{ $groupKey }}')"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-sm text-slate-900 dark:text-white">{{ $permission->name }}</p>
            <code class="mt-1 inline-block max-w-full truncate rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-mono text-slate-600 dark:bg-slate-900 dark:text-slate-400">{{ $permission->key }}</code>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200">{{ $groupLabel }}</span>
                <span class="text-[11px] text-slate-500">{{ $permission->roles_count }} role{{ (int) $permission->roles_count === 1 ? '' : 's' }}</span>
                <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
        </div>
        <x-property.action-menu label="Actions" width="w-40">
            <button type="button" @click="startEdit({{ (int) $permission->id }})" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Edit</button>
            <form method="post" action="{{ route('property.settings.system_setup.access.permissions.destroy', $permission) }}" data-swal-confirm="Delete this permission?" class="border-t border-slate-100 dark:border-slate-700">
                @csrf
                @method('delete')
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-medium text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30">Delete</button>
            </form>
        </x-property.action-menu>
    </div>
</div>

{{-- Mobile card (edit) --}}
<div
    class="md:hidden rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-800 dark:bg-blue-950/30 p-3"
    x-show="editingId === {{ (int) $permission->id }}"
    x-cloak
>
    <form method="post" action="{{ route('property.settings.system_setup.access.permissions.update', $permission) }}" class="space-y-3">
        @csrf
        @method('patch')
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Edit permission</p>
        <div class="space-y-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                <input type="text" name="name" value="{{ old('name', $permission->name) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Key</label>
                <input type="text" name="key" value="{{ old('key', $permission->key) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 font-mono text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Group</label>
                <input type="text" name="group" value="{{ old('group', $permission->group) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                <input type="text" name="description" value="{{ old('description', $permission->description) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="cancelEdit()" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Save changes</button>
        </div>
    </form>
</div>
