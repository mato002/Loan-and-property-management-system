<x-property.workspace
    title="Edit tenant"
    subtitle="Update tenant profile details used in leases, billing, and tenant operations."
    back-route="property.tenants.directory"
    :stats="[
        ['label' => 'Tenant', 'value' => $tenant->name, 'hint' => $tenant->email ?: 'No email'],
        ['label' => 'Risk', 'value' => ucfirst($tenant->risk_level), 'hint' => 'Current'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.tenants.update', $tenant) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
        @csrf
        @method('PUT')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tenant details</h3>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
            <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                <input type="email" name="email" value="{{ old('email', $tenant->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">National ID / ref</label>
                <input type="text" name="national_id" value="{{ old('national_id', $tenant->national_id) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Risk</label>
                <select name="risk_level" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="normal" @selected(old('risk_level', $tenant->risk_level) === 'normal')>Normal</option>
                    <option value="medium" @selected(old('risk_level', $tenant->risk_level) === 'medium')>Medium</option>
                    <option value="high" @selected(old('risk_level', $tenant->risk_level) === 'high')>High</option>
                </select>
                @error('risk_level')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes', $tenant->notes) }}</textarea>
            @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.tenants.directory') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back</a>
        </div>
    </form>
</x-property.workspace>

