<x-property-layout>
    <x-slot name="header">Permissions</x-slot>

    <x-property.page
        title="Permissions"
        subtitle="Edit, remove, and review all property permission keys."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.roles') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Property users</a>
            <a href="{{ route('property.settings.permissions') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Permissions</a>
            <a href="{{ route('property.settings.system_setup.access') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Access control</a>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            @foreach ($stats as $stat)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $stat['value'] }}</p>
                    @if (($stat['hint'] ?? '') !== '')
                        <p class="mt-1 text-xs text-slate-500">{{ $stat['hint'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="space-y-3">
            @forelse ($permissions as $permission)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                    <form method="post" action="{{ route('property.settings.system_setup.access.permissions.update', $permission) }}">
                        @csrf
                        @method('patch')
                        <div class="grid gap-3 md:grid-cols-5">
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Name</label>
                                <input type="text" name="name" value="{{ $permission->name }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Key</label>
                                <input type="text" name="key" value="{{ $permission->key }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Group</label>
                                <input type="text" name="group" value="{{ $permission->group }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-slate-500">Description</label>
                                <input type="text" name="description" value="{{ $permission->description }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <p class="text-xs text-slate-500">Used by roles: {{ $permission->roles_count }}</p>
                            <button type="submit" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Save changes</button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('property.settings.system_setup.access.permissions.destroy', $permission) }}" data-swal-confirm="Delete this permission?" class="mt-2 text-right">
                        @csrf
                        @method('delete')
                        <button type="submit" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                    </form>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-6 text-sm text-slate-500">
                    No permissions yet. Use Access control page to create one.
                </div>
            @endforelse
        </div>
    </x-property.page>
</x-property-layout>


