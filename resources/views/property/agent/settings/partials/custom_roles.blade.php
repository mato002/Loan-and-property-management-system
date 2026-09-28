@php
    $scopeLabels = ['agent' => 'Agent', 'landlord' => 'Landlord', 'tenant' => 'Tenant', 'any' => 'Any'];
    $customRoles = $customRoles ?? collect();
@endphp

<div id="create-role" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Custom roles</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Create a role here, then set what it can do.</p>
        </div>
        <a href="{{ route('property.settings.system_setup.access') }}" data-turbo-frame="property-main" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Permission matrix</a>
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    <form
        method="post"
        action="{{ route('property.settings.system_setup.access.roles.store') }}"
        class="grid gap-3 md:grid-cols-2 xl:grid-cols-5"
        data-turbo="false"
        x-data="{
            name: @js(old('name', '')),
            slug: @js(old('slug', '')),
            slugTouched: @js(old('slug') !== null),
            syncSlug() {
                if (this.slugTouched) return;
                this.slug = this.name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
            }
        }"
    >
        @csrf
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
            Role name
            <input type="text" name="name" x-model="name" @input="syncSlug()" required maxlength="100" placeholder="Accountant" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </label>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
            Slug
            <input type="text" name="slug" x-model="slug" @input="slugTouched = true" required maxlength="100" placeholder="accountant" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </label>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
            Who can hold it
            <select name="portal_scope" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                @foreach ($scopeLabels as $scopeKey => $scopeLabel)
                    <option value="{{ $scopeKey }}" @selected(old('portal_scope', 'agent') === $scopeKey)>{{ $scopeLabel }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 xl:col-span-1">
            Description
            <input type="text" name="description" value="{{ old('description') }}" maxlength="500" placeholder="Optional" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </label>
        <div class="flex items-end">
            <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create role</button>
        </div>
    </form>

    @if ($customRoles->isEmpty())
        <p class="text-sm text-slate-500">No custom roles yet. Use the form above to add the first one.</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-600">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Role</th>
                        <th class="px-3 py-2 font-semibold">Slug</th>
                        <th class="px-3 py-2 font-semibold">Scope</th>
                        <th class="px-3 py-2 font-semibold">Permissions</th>
                        <th class="px-3 py-2 font-semibold">Users</th>
                        <th class="px-3 py-2 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($customRoles as $role)
                        <tr>
                            <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">{{ $role->name }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $role->slug }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $scopeLabels[$role->portal_scope] ?? ucfirst((string) $role->portal_scope) }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ (int) $role->permissions_count }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ (int) $role->users_count }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('property.settings.system_setup.access') }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">Set permissions</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
