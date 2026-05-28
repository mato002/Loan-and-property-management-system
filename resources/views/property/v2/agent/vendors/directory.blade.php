<x-property.workspace
    :legacy-toolbar="false"
    title="Vendor directory"
    subtitle="Active suppliers for maintenance and projects."
    :show-search="false"
    back-route="property.vendors.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No vendors"
    empty-hint="Add vendors here; assign them when creating maintenance jobs."
>
    <x-slot name="above">
        @php
            $showVendorFormByDefault = $errors->hasAny(['name','category','status','phone','email','rating']);
        @endphp
        <details class="space-y-3 group" @if($showVendorFormByDefault) open @endif>
        <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
            <span class="group-open:hidden">Add vendor</span>
            <span class="hidden group-open:inline">Hide vendor form</span>
        </summary>
        <form method="post" action="{{ route('property.vendors.store') }}" class="mt-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add vendor</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                    <input type="text" name="category" value="{{ old('category') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
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
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rating (0–5)</label>
                    <input type="number" name="rating" value="{{ old('rating') }}" step="0.1" min="0" max="5" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('rating')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save vendor</button>
        </form>
        </details>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.vendors_directory', ['filters' => $filters])
    </x-slot>

    @if (isset($vendorPager))
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $vendorPager->firstItem() ?? 0 }}-{{ $vendorPager->lastItem() ?? 0 }} of {{ $vendorPager->total() }} vendors.
                </p>
                <div>
                    {{ $vendorPager->onEachSide(1)->links() }}
                </div>
            </div>
        </x-slot>
    @endif

</x-property.workspace>
