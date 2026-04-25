<x-loan-layout>
    <x-loan.page
        title="Staff groups"
        subtitle="Compact group matrix with permissions."
    >
        @php
            $groupPayload = $groups->mapWithKeys(function ($group) {
                return [
                    (string) $group->id => [
                        'id' => (int) $group->id,
                        'name' => (string) $group->name,
                        'description' => (string) ($group->description ?? ''),
                        'permissions' => array_values((array) ($group->permissions ?? [])),
                    ],
                ];
            })->all();
            $permissionsList = $permissionOptions ?? [];
            $permissionsChunked = array_chunk($permissionsList, (int) ceil(max(1, count($permissionsList)) / 2), true);
            $updateActionTemplate = route('loan.employees.groups.update', ['staff_group' => '__GROUP_ID__']);
        @endphp

        <x-slot name="actions">
            <button type="button" id="open-create-group-modal" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                + Group
            </button>
        </x-slot>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Group name..." oninput="window.clearTimeout(this._autoSearchTimer); this._autoSearchTimer = window.setTimeout(() => this.form.requestSubmit(), 1100);" class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.requestSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([12, 24, 36, 60, 120] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 24) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040]">Filter</button>
                <a href="{{ route('loan.employees.groups') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
            </div>
        </form>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Staff Groups</h2>
                <p class="text-xs text-slate-500">{{ $groups->total() }} group(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Group</th>
                            <th class="px-5 py-3 text-right">Total Staff</th>
                            <th class="px-5 py-3">Permissions</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($groups as $group)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $group->name }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ (int) $group->employees_count }}</td>
                                <td class="px-5 py-3 text-slate-700">
                                    {{ count(array_filter((array) ($group->permissions ?? []))) }} Roles
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="js-edit-group text-indigo-600 hover:underline text-xs font-semibold mr-3"
                                        data-group-id="{{ $group->id }}"
                                    >
                                        Edit
                                    </button>
                                    <a href="{{ route('loan.employees.groups.show', $group) }}" class="text-slate-600 hover:underline text-xs font-semibold mr-3">Members</a>
                                    <form method="post" action="{{ route('loan.employees.groups.destroy', $group) }}" class="inline" data-swal-confirm="Delete this group and remove all memberships?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:underline text-xs font-semibold">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-slate-500">No staff groups found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($groups->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $groups->links() }}
                </div>
            @endif
        </div>

        <div id="create-group-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/55 p-4">
            <div class="w-full max-w-3xl rounded-xl border border-slate-200 bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h3 class="text-lg font-semibold text-slate-800">Create Staff Group</h3>
                    <button type="button" id="close-create-group-modal" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700">✕</button>
                </div>
                <form method="post" action="{{ route('loan.employees.groups.store') }}" class="p-5">
                    @csrf
                    <div class="mb-4">
                        <label for="create_group_name" class="mb-1 block text-xs font-semibold text-slate-600">Group Name</label>
                        <input id="create_group_name" name="name" type="text" required class="w-full rounded-md border-slate-300 text-sm" />
                    </div>
                    <div class="mb-4">
                        <label for="create_group_description" class="mb-1 block text-xs font-semibold text-slate-600">Description</label>
                        <textarea id="create_group_description" name="description" rows="2" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($permissionsChunked as $chunk)
                            <div class="space-y-2">
                                @foreach ($chunk as $key => $label)
                                    <label class="flex items-start gap-2 text-xs text-slate-700">
                                        <input type="checkbox" name="permissions[]" value="{{ $key }}" class="mt-0.5 rounded border-slate-300 text-indigo-600">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" id="cancel-create-group-modal" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="edit-group-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/55 p-4">
            <div class="w-full max-w-3xl rounded-xl border border-slate-200 bg-white shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h3 class="text-lg font-semibold text-slate-800">Edit Staff Group</h3>
                    <button type="button" id="close-edit-group-modal" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700">✕</button>
                </div>
                <form id="edit-group-form" method="post" class="p-5">
                    @csrf
                    @method('patch')
                    <div class="mb-4">
                        <label for="edit_group_name" class="mb-1 block text-xs font-semibold text-slate-600">Group Name</label>
                        <input id="edit_group_name" name="name" type="text" required class="w-full rounded-md border-slate-300 text-sm" />
                    </div>
                    <div class="mb-4">
                        <label for="edit_group_description" class="mb-1 block text-xs font-semibold text-slate-600">Description</label>
                        <textarea id="edit_group_description" name="description" rows="2" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($permissionsChunked as $chunk)
                            <div class="space-y-2">
                                @foreach ($chunk as $key => $label)
                                    <label class="flex items-start gap-2 text-xs text-slate-700">
                                        <input type="checkbox" name="permissions[]" value="{{ $key }}" class="js-edit-group-permission mt-0.5 rounded border-slate-300 text-indigo-600">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" id="cancel-edit-group-modal" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
<script>
    (() => {
        const groupPayload = @json($groupPayload);
        const updateActionTemplate = @json($updateActionTemplate);

        const createModal = document.getElementById('create-group-modal');
        const openCreateBtn = document.getElementById('open-create-group-modal');
        const closeCreateBtn = document.getElementById('close-create-group-modal');
        const cancelCreateBtn = document.getElementById('cancel-create-group-modal');

        const editModal = document.getElementById('edit-group-modal');
        const editForm = document.getElementById('edit-group-form');
        const closeEditBtn = document.getElementById('close-edit-group-modal');
        const cancelEditBtn = document.getElementById('cancel-edit-group-modal');
        const editName = document.getElementById('edit_group_name');
        const editDescription = document.getElementById('edit_group_description');
        const permissionBoxes = Array.from(document.querySelectorAll('.js-edit-group-permission'));

        const openModal = (modal) => {
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
        };
        const closeModal = (modal) => {
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
        };

        openCreateBtn?.addEventListener('click', () => openModal(createModal));
        [closeCreateBtn, cancelCreateBtn].forEach((el) => el?.addEventListener('click', () => closeModal(createModal)));
        createModal?.addEventListener('click', (event) => {
            if (event.target === createModal) closeModal(createModal);
        });

        document.querySelectorAll('.js-edit-group').forEach((button) => {
            button.addEventListener('click', () => {
                const groupId = String(button.dataset.groupId || '');
                const group = groupPayload[groupId];
                if (!group || !editForm) return;

                editForm.action = String(updateActionTemplate).replace('__GROUP_ID__', groupId);
                if (editName) editName.value = group.name || '';
                if (editDescription) editDescription.value = group.description || '';

                const selected = new Set(Array.isArray(group.permissions) ? group.permissions : []);
                permissionBoxes.forEach((input) => {
                    input.checked = selected.has(input.value);
                });

                openModal(editModal);
            });
        });

        [closeEditBtn, cancelEditBtn].forEach((el) => el?.addEventListener('click', () => closeModal(editModal)));
        editModal?.addEventListener('click', (event) => {
            if (event.target === editModal) closeModal(editModal);
        });
    })();
</script>
