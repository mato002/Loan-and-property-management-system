<x-property.workspace
    title="Applications"
    subtitle="Rental applications linked to units. Extend screening fields when you add compliance requirements."
    back-route="property.listings.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No applications"
    empty-hint="Record applications below; attach documents in a later phase."
>
    <x-slot name="above">
        @if (session('success'))
            <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.listings.applications.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New application</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Applicant name</label>
                    <input type="text" name="applicant_name" value="{{ old('applicant_name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('applicant_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                    <input type="text" name="applicant_phone" value="{{ old('applicant_phone') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('applicant_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                    <input type="email" name="applicant_email" value="{{ old('applicant_email') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('applicant_email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (['received', 'review', 'approved', 'declined', 'withdrawn'] as $st)
                            <option value="{{ $st }}" @selected(old('status', 'received') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit (optional)</label>
                    <select name="property_unit_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">—</option>
                        @foreach ($units as $u)
                            <option value="{{ $u->id }}" @selected(old('property_unit_id') == $u->id)>{{ $u->property->name }} / {{ $u->label }}</option>
                        @endforeach
                    </select>
                    @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save application</button>
        </form>
    </x-slot>

    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search applications…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>

    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Update status</h3>
        <ul class="space-y-2 text-sm">
            @foreach ($applications as $app)
                <li class="flex flex-col sm:flex-row sm:items-center gap-2 rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                    <span class="font-medium text-slate-900 dark:text-white shrink-0">#{{ $app->id }} · {{ $app->applicant_name }}</span>
                    <form method="post" action="{{ route('property.listings.applications.update', $app) }}" class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-1.5 min-w-0">
                            @foreach (['received', 'review', 'approved', 'declined', 'withdrawn'] as $st)
                                <option value="{{ $st }}" @selected($app->status === $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">Apply</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-property.workspace>
