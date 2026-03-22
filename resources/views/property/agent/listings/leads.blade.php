<x-property.workspace
    title="Lead tracking"
    subtitle="Leads are stored in the database and can be linked to a unit. Stages are free-form labels for your pipeline."
    back-route="property.listings.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No leads"
    empty-hint="Add a lead below or import from your CRM when you wire CSV."
>
    <x-slot name="above">
        @if (session('success'))
            <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.listings.leads.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New lead</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Source</label>
                    <input type="text" name="source" value="{{ old('source') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Website, referral…" />
                    @error('source')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Stage</label>
                    <select name="stage" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (['new', 'contacted', 'viewing', 'negotiation', 'won', 'lost'] as $st)
                            <option value="{{ $st }}" @selected(old('stage', 'new') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                    @error('stage')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save lead</button>
        </form>
    </x-slot>

    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search leads…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>

    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Update stage</h3>
        <ul class="space-y-2 text-sm">
            @foreach ($leads as $lead)
                <li class="flex flex-col sm:flex-row sm:items-center gap-2 rounded-lg border border-slate-100 dark:border-slate-700 p-3">
                    <span class="font-medium text-slate-900 dark:text-white shrink-0">{{ $lead->name }}</span>
                    <form method="post" action="{{ route('property.listings.leads.update', $lead) }}" class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
                        @csrf
                        @method('PATCH')
                        <select name="stage" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2 py-1.5 min-w-0">
                            @foreach (['new', 'contacted', 'viewing', 'negotiation', 'won', 'lost'] as $st)
                                <option value="{{ $st }}" @selected($lead->stage === $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">Apply</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-property.workspace>
