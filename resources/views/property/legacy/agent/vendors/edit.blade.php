<x-property.workspace
    title="Edit vendor"
    subtitle="Update supplier profile used across maintenance jobs, quotes, and performance."
    back-route="property.vendors.directory"
    :stats="[
        ['label' => 'Vendor', 'value' => $vendor->name, 'hint' => $vendor->category ?: 'No category'],
        ['label' => 'Status', 'value' => ucfirst($vendor->status), 'hint' => 'Current state'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.vendors.update', $vendor) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
        @csrf
        @method('PUT')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Vendor details</h3>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
            <input type="text" name="name" value="{{ old('name', $vendor->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                <input type="text" name="category" value="{{ old('category', $vendor->category) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="active" @selected(old('status', $vendor->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $vendor->status) === 'inactive')>Inactive</option>
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $vendor->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                <input type="email" name="email" value="{{ old('email', $vendor->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rating (0-5)</label>
                <input type="number" name="rating" value="{{ old('rating', $vendor->rating) }}" min="0" max="5" step="0.1" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('rating')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.vendors.directory') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back</a>
        </div>
    </form>
</x-property.workspace>

