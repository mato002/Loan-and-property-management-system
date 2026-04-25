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
    @php
        $applicationCfg = $applicationFields ?? [];
        $applicationRequired = fn (string $k, bool $d = false) => (bool) (($applicationCfg[$k]['required'] ?? $d) && ($applicationCfg[$k]['enabled'] ?? true));
    @endphp
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.listings.applications', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All applications</a>
                <a href="{{ route('property.listings.applications', array_merge((array) ($filters ?? []), ['status' => 'review']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">In review</a>
                <a href="{{ route('property.listings.applications', array_merge((array) ($filters ?? []), ['status' => 'approved']), absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Approved</a>
                <a href="{{ route('property.listings.applications.export', (array) ($filters ?? []), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
            </div>
        </div>

        <form method="get" action="{{ route('property.listings.applications') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, phone, email, notes..." class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['received', 'review', 'approved', 'declined', 'withdrawn'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <select name="unit_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach ($units as $u)
                            <option value="{{ $u->id }}" @selected((string) ($filters['unit_id'] ?? '') === (string) $u->id)>{{ $u->property->name }} / {{ $u->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.listings.applications', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
        </form>

        @if (session('success'))
            <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        <form method="post" action="{{ route('property.listings.applications.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New application</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Applicant name</label>
                    <input type="text" name="applicant_name" value="{{ old('applicant_name') }}" @required($applicationRequired('applicant_name', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
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
                    <x-property.quick-create-select
                        name="property_unit_id"
                        :required="$applicationRequired('unit_id', true)"
                        placeholder="—"
                        :options="collect($units)->map(fn($u) => ['value' => $u->id, 'label' => $u->property->name.' / '.$u->label, 'selected' => (string) old('property_unit_id') === (string) $u->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Add unit',
                            'endpoint' => route('property.units.store_json'),
                            'fields' => [
                                ['name' => 'property_id', 'label' => 'Property', 'required' => true, 'span' => '2', 'type' => 'select', 'placeholder' => 'Select property', 'options' => collect($units)->map(fn($u) => ['value' => $u->property_id, 'label' => $u->property->name])->unique('value')->values()->all()],
                                ['name' => 'label', 'label' => 'Unit label', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. A1'],
                                ['name' => 'unit_type', 'label' => 'Unit type', 'required' => false, 'type' => 'select', 'options' => [['value' => 'apartment', 'label' => 'Apartment'], ['value' => 'single_room', 'label' => 'Single room'], ['value' => 'bedsitter', 'label' => 'Bedsitter'], ['value' => 'studio', 'label' => 'Studio'], ['value' => 'bungalow', 'label' => 'Bungalow'], ['value' => 'maisonette', 'label' => 'Maisonette'], ['value' => 'villa', 'label' => 'Villa'], ['value' => 'townhouse', 'label' => 'Townhouse'], ['value' => 'commercial', 'label' => 'Commercial']]],
                                ['name' => 'status', 'label' => 'Status', 'required' => false, 'type' => 'select', 'options' => [['value' => 'vacant', 'label' => 'Vacant'], ['value' => 'occupied', 'label' => 'Occupied'], ['value' => 'notice', 'label' => 'Notice']]],
                            ],
                        ]"
                    />
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

    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <p class="text-sm text-slate-700 dark:text-slate-200">
            Tip: Use the <span class="font-semibold">Actions</span> column to view, message, and update status per applicant.
        </p>
    </div>
</x-property.workspace>
