@php
    $permissions = $permissions ?? collect();
    $stats = $stats ?? [];
    $permissionsByGroup = $permissions->groupBy(fn ($p) => trim((string) ($p->group ?? '')) !== '' ? $p->group : 'general');
    $groupOptions = $permissionsByGroup->keys()->sort()->values();
    $editingPermissionId = 0;
    if ($errors->any() && old('key')) {
        $match = $permissions->first(fn ($p) => $p->key === old('key') || $p->name === old('name'));
        $editingPermissionId = $match?->id ?? 0;
    }
    $openAddModal = $errors->any() && ! $editingPermissionId && (old('name') || old('key'));
@endphp

@if (session('success'))
    <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">{{ session('success') }}</p>
@endif

@if ($errors->any() && ! $editingPermissionId && ! $openAddModal)
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200">
        <p class="font-semibold">Please fix the errors below.</p>
    </div>
@endif

<div
    x-data="permissionsConsole(@js([
        'editingId' => $editingPermissionId,
        'showAddModal' => $openAddModal,
    ]))"
    @permissions-open-add.window="showAddModal = true"
    class="space-y-4"
>
    <div class="grid gap-3 sm:grid-cols-3">
        @php
            $statMeta = [
                'Permissions' => ['icon' => 'fa-key', 'tone' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-300 dark:bg-emerald-950/40'],
                'Groups' => ['icon' => 'fa-layer-group', 'tone' => 'text-indigo-700 bg-indigo-50 dark:text-indigo-300 dark:bg-indigo-950/40'],
                'Role links' => ['icon' => 'fa-link', 'tone' => 'text-blue-700 bg-blue-50 dark:text-blue-300 dark:bg-blue-950/40'],
            ];
        @endphp
        @foreach ($stats as $stat)
            @php
                $meta = $statMeta[$stat['label'] ?? ''] ?? ['icon' => 'fa-circle-info', 'tone' => 'text-slate-700 bg-slate-100'];
            @endphp
            <div class="flex items-center gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 px-3 py-2.5 shadow-sm">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $meta['tone'] }}">
                    <i class="fa-solid {{ $meta['icon'] }} text-sm" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                    <p class="text-lg font-semibold leading-tight text-slate-900 dark:text-white">{{ $stat['value'] }}</p>
                    @if (($stat['hint'] ?? '') !== '')
                        <p class="text-[11px] text-slate-500 truncate">{{ $stat['hint'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2 xl:col-span-1">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Search</label>
                    <input type="search" x-model="search" placeholder="Name or key…" autocomplete="off" class="mt-1 w-full min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Group</label>
                    <select x-model="groupFilter" class="mt-1 w-full min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All groups</option>
                        @foreach ($groupOptions as $groupName)
                            <option value="{{ strtolower($groupName) }}">{{ $groupName }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Role usage</label>
                    <select x-model="roleFilter" class="mt-1 w-full min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Any</option>
                        <option value="0">Unused (0 roles)</option>
                        <option value="1">In use (1+)</option>
                        <option value="3">Heavy (3+)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Sort</label>
                    <select x-model="sort" class="mt-1 w-full min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="name-asc">Name (A–Z)</option>
                        <option value="name-desc">Name (Z–A)</option>
                        <option value="key-asc">Key (A–Z)</option>
                        <option value="group-asc">Group (A–Z)</option>
                        <option value="roles-desc">Most roles</option>
                        <option value="roles-asc">Fewest roles</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button type="button" @click="groupedView = !groupedView" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <span x-text="groupedView ? 'Flat list' : 'Grouped view'"></span>
                </button>
                <button type="button" @click="resetFilters()" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</button>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-500" x-show="visibleCount() < totalCount()" x-cloak>
            Showing <span x-text="visibleCount()"></span> of <span x-text="totalCount()"></span> permissions
        </p>
    </div>

    @if ($permissions->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-8 text-center text-sm text-slate-500">
            No permissions yet. Use <strong>Add permission</strong> or open Access control to seed defaults.
        </div>
    @else
        <div class="hidden md:block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="property-erp-table w-full min-w-[720px] text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-4 py-2.5">Permission</th>
                            <th class="px-4 py-2.5">Key</th>
                            <th class="px-4 py-2.5">Group</th>
                            <th class="px-4 py-2.5">Used by roles</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="permissions-table-body">
                        @foreach ($permissionsByGroup->sortKeys() as $groupName => $groupPermissions)
                            @php $groupKey = strtolower($groupName); @endphp
                            <tr
                                class="bg-slate-50/80 dark:bg-slate-900/50"
                                x-show="groupedView && groupHeaderVisible('{{ $groupKey }}')"
                                x-cloak
                            >
                                <td colspan="6" class="px-4 py-2">
                                    <button type="button" class="flex w-full items-center justify-between gap-2 text-left" @click="toggleGroup('{{ $groupKey }}')">
                                        <span class="text-xs font-bold uppercase tracking-wide text-slate-700 dark:text-slate-200">{{ $groupName }}</span>
                                        <span class="inline-flex items-center gap-2 text-[11px] text-slate-500">
                                            <span>{{ $groupPermissions->count() }} permissions</span>
                                            <span class="text-slate-400" x-text="collapsedGroups['{{ $groupKey }}'] ? '▸' : '▾'"></span>
                                        </span>
                                    </button>
                                </td>
                            </tr>
                            @foreach ($groupPermissions->sortBy('name') as $permission)
                                @include('property.agent.settings.partials.permissions_row', [
                                    'permission' => $permission,
                                    'editingPermissionId' => $editingPermissionId,
                                ])
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="md:hidden space-y-3" id="permissions-mobile-list">
            @foreach ($permissions->sortBy('name') as $permission)
                @include('property.agent.settings.partials.permissions_row', [
                    'permission' => $permission,
                    'editingPermissionId' => $editingPermissionId,
                ])
            @endforeach
        </div>
    @endif

    <div class="hidden" aria-hidden="true">
        @foreach ($permissions as $permission)
            <span
                data-permission-export
                data-export-name="{{ $permission->name }}"
                data-export-key="{{ $permission->key }}"
                data-export-group="{{ $permission->group ?: 'general' }}"
                data-export-roles="{{ (int) $permission->roles_count }}"
                data-export-description="{{ $permission->description }}"
            ></span>
        @endforeach
    </div>

    <x-property.modal show="showAddModal" close="showAddModal = false" name="permissions-add" title="Add permission" max-width="lg">
        <form id="permissions-add-form" method="post" action="{{ route('property.settings.system_setup.access.permissions.store') }}" class="space-y-3" data-turbo="false">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Approve payouts" />
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Key</label>
                <input type="text" name="key" value="{{ old('key') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 font-mono text-sm px-3 py-2" placeholder="permission.key" />
                @error('key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Group</label>
                <input type="text" name="group" value="{{ old('group', 'general') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. payments" />
                @error('group')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional">{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </form>
        <x-slot name="footer">
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="min-h-[44px] rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium" @click="showAddModal = false">Cancel</button>
                <button type="submit" form="permissions-add-form" class="min-h-[44px] rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create permission</button>
            </div>
        </x-slot>
    </x-property.modal>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('permissionsConsole', (boot = {}) => ({
            editingId: boot.editingId ? Number(boot.editingId) : null,
            showAddModal: !!boot.showAddModal,
            groupedView: true,
            collapsedGroups: {},
            search: '',
            groupFilter: '',
            roleFilter: '',
            sort: 'name-asc',
            startEdit(id) { this.editingId = Number(id); },
            cancelEdit() { this.editingId = null; },
            toggleGroup(key) { this.collapsedGroups[key] = !this.collapsedGroups[key]; },
            rowGroupVisible(groupKey) {
                if (!this.groupedView) return true;
                return !this.collapsedGroups[groupKey];
            },
            resetFilters() {
                this.search = '';
                this.groupFilter = '';
                this.roleFilter = '';
                this.sort = 'name-asc';
            },
            rowVisible(el) {
                if (!(el instanceof HTMLElement)) return true;
                const q = (this.search || '').trim().toLowerCase();
                const name = el.dataset.name || '';
                const key = el.dataset.key || '';
                const group = el.dataset.group || '';
                const roles = Number(el.dataset.roles || 0);
                if (q !== '' && !name.includes(q) && !key.includes(q)) return false;
                if (this.groupFilter !== '' && group !== this.groupFilter) return false;
                if (this.roleFilter === '0' && roles !== 0) return false;
                if (this.roleFilter === '1' && roles < 1) return false;
                if (this.roleFilter === '3' && roles < 3) return false;
                return true;
            },
            compareRows(a, b) {
                const [field, dir] = (this.sort || 'name-asc').split('-');
                const mul = dir === 'desc' ? -1 : 1;
                const av = field === 'roles' ? Number(a.dataset.roles || 0) : (a.dataset[field] || '');
                const bv = field === 'roles' ? Number(b.dataset.roles || 0) : (b.dataset[field] || '');
                if (av < bv) return -1 * mul;
                if (av > bv) return 1 * mul;
                return 0;
            },
            applySort(container) {
                if (!(container instanceof HTMLElement)) return;
                const rows = Array.from(container.querySelectorAll('[data-permission-row]'));
                rows.sort((a, b) => this.compareRows(a, b));
                rows.forEach((row) => container.appendChild(row));
            },
            rowListSelector() {
                return window.matchMedia('(min-width: 768px)').matches
                    ? '#permissions-table-body [data-permission-row]'
                    : '#permissions-mobile-list [data-permission-row]';
            },
            visibleCount() {
                return Array.from(document.querySelectorAll(this.rowListSelector())).filter((el) => this.rowVisible(el)).length;
            },
            totalCount() {
                return document.querySelectorAll(this.rowListSelector()).length;
            },
            groupHeaderVisible(groupKey) {
                return Array.from(document.querySelectorAll('#permissions-table-body [data-permission-row]'))
                    .filter((el) => (el.dataset.group || '') === groupKey)
                    .some((el) => this.rowVisible(el));
            },
            init() {
                this.$nextTick(() => {
                    const mobile = document.getElementById('permissions-mobile-list');
                    if (mobile) this.applySort(mobile);
                });
                this.$watch('sort', () => {
                    const mobile = document.getElementById('permissions-mobile-list');
                    if (mobile) this.applySort(mobile);
                });
            },
        }));
    });

    window.exportPermissionsCsv = function () {
        const headers = ['Name', 'Key', 'Group', 'Roles', 'Description'];
        const rows = [];
        document.querySelectorAll('[data-permission-export]').forEach((el) => {
            rows.push([
                el.dataset.exportName || '',
                el.dataset.exportKey || '',
                el.dataset.exportGroup || '',
                el.dataset.exportRoles || '0',
                el.dataset.exportDescription || '',
            ]);
        });
        const escape = (v) => `"${String(v).replace(/"/g, '""')}"`;
        const csv = [headers.map(escape).join(','), ...rows.map((r) => r.map(escape).join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `permissions-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    };
</script>
@endpush
