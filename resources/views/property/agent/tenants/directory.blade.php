<x-property.workspace
    :title="$pageTitle"
    :subtitle="$pageSubtitle"
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No tenants on file"
    empty-hint="Create tenants here, then add leases and invoices against them."
>
    <x-slot name="above">
        @if ($showTenantForm ?? true)
        <form method="post" action="{{ route('property.tenants.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add tenant</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">National ID / ref</label>
                    <input type="text" name="national_id" value="{{ old('national_id') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Risk</label>
                    <select name="risk_level" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="normal" @selected(old('risk_level', 'normal') === 'normal')>Normal</option>
                        <option value="medium" @selected(old('risk_level') === 'medium')>Medium</option>
                        <option value="high" @selected(old('risk_level') === 'high')>High</option>
                    </select>
                    @error('risk_level')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900/40 p-3 cursor-pointer">
                <input type="checkbox" name="create_portal_login" value="1" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500" @checked(old('create_portal_login')) />
                <span class="text-sm text-slate-700 dark:text-slate-300">
                    <span class="font-medium text-slate-900 dark:text-white">Create portal login &amp; email credentials</span>
                    <span class="block text-slate-500 dark:text-slate-400 mt-0.5">Requires a unique email. The tenant receives sign-in instructions and a temporary password.</span>
                </span>
            </label>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save tenant</button>
        </form>
        @endif
    </x-slot>

    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search name, phone, ID…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
</x-property.workspace>
