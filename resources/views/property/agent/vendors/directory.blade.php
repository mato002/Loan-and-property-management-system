<x-property.workspace
    title="Vendor directory"
    subtitle="Active suppliers for maintenance and projects."
    back-route="property.vendors.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No vendors"
    empty-hint="Add vendors here; assign them when creating maintenance jobs."
>
    <x-slot name="above">
        <form method="post" action="{{ route('property.vendors.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
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
    </x-slot>

    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search vendor…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>

    @if (session()->has('next_steps') && ($ns = session('next_steps')) && is_array($ns))
        <div
            x-data="{ open: true }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40"
        >
            <div class="mx-4 max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/10">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                        Vendor onboarding
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">
                        {{ $ns['title'] ?? 'Vendor saved' }}
                    </h2>
                    @php
                        $summary = $ns['vendor'] ?? null;
                    @endphp
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $ns['message'] ?? 'Continue with the onboarding steps below.' }}
                    </p>
                    @if (is_array($summary))
                        <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-slate-600">
                            <div class="flex justify-between gap-3">
                                <dt class="font-medium text-slate-700">Vendor</dt>
                                <dd class="text-right">{{ $summary['name'] ?? '' }}</dd>
                            </div>
                            @if (!empty($summary['category']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Category</dt>
                                    <dd class="text-right">{{ $summary['category'] }}</dd>
                                </div>
                            @endif
                            @if (!empty($summary['phone']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Phone</dt>
                                    <dd class="text-right">{{ $summary['phone'] }}</dd>
                                </div>
                            @endif
                            @if (!empty($summary['email']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Email</dt>
                                    <dd class="text-right">{{ $summary['email'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endif
                </div>
                <div class="px-5 py-4 space-y-3">
                    <p class="text-xs font-medium text-slate-500">
                        Choose where to go next:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($ns['actions'] ?? [] as $action)
                            @php
                                $kind = $action['kind'] ?? 'secondary';
                                $isPrimary = $kind === 'primary';
                                $frame = $action['turbo_frame'] ?? null;
                            @endphp
                            <a
                                href="{{ $action['href'] ?? '#' }}"
                                @if ($frame) data-turbo-frame="{{ $frame }}" @endif
                                class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold
                                    {{ $isPrimary ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-slate-300 text-slate-700 bg-white hover:bg-slate-50' }}"
                                @click="open = false"
                            >
                                @if (!empty($action['icon']))
                                    <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                                @endif
                                <span>{{ $action['label'] ?? 'Continue' }}</span>
                            </a>
                        @endforeach
                    </div>
                    <button
                        type="button"
                        class="mt-1 text-xs font-medium text-slate-500 hover:text-slate-700"
                        @click="open = false"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-property.workspace>
