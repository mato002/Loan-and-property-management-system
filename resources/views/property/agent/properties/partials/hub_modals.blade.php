@php
    $landlordUsers = $landlordUsers ?? collect();
    $hubUnits = collect($units ?? []);
    $isReadOnly = (bool) ($isManagementReadOnly ?? $property->isManagementReadOnly());
@endphp

@if (! $isReadOnly)
{{-- Link landlord --}}
<x-property.modal
    show="showHubLinkLandlord"
    close="showHubLinkLandlord = false"
    name="property-hub-link-landlord"
    title="Link landlord"
    max-width="2xl"
>
    <form method="post" action="{{ route('property.properties.landlords.attach') }}" class="space-y-3" data-turbo-frame="property-main">
        @csrf
        <input type="hidden" name="property_id" value="{{ $property->id }}" />
        <input type="hidden" name="return_to" value="property_show" />
        <input type="hidden" name="return_property_id" value="{{ $property->id }}" />
        <input type="hidden" name="return_tab" value="landlords" />
        @if (! empty($monthValue))
            <input type="hidden" name="return_month" value="{{ $monthValue }}" />
        @endif
        @if (! empty($fyValue))
            <input type="hidden" name="return_fy" value="{{ $fyValue }}" />
        @endif

        <p class="text-xs text-slate-500">Link a landlord to <span class="font-semibold text-slate-800">{{ $property->name }}</span>.</p>

        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord</label>
            <select name="user_id" required data-property-searchable="true" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select…</option>
                @foreach ($landlordUsers as $u)
                    <option value="{{ $u->id }}" @selected((string) old('user_id') === (string) $u->id)>
                        {{ $u->name }} @if($u->email || $u->phone)({{ $u->email ?: $u->phone }})@endif
                    </option>
                @endforeach
            </select>
            @error('user_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ownership %</label>
            <input type="number" name="ownership_percent" value="{{ old('ownership_percent', 100) }}" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('ownership_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Link landlord</button>
    </form>
</x-property.modal>

{{-- Maintenance request --}}
<x-property.modal
    show="showHubMaintenanceForm"
    close="showHubMaintenanceForm = false"
    name="property-hub-maintenance"
    title="New maintenance request"
    max-width="2xl"
>
    @php
        $unitPayload = $hubUnits->map(fn ($u) => [
            'id' => (string) (is_array($u) ? ($u['id'] ?? '') : $u->id),
            'label' => (string) (is_array($u) ? ($u['label'] ?? '') : $u->label),
        ])->values();
        $defaultUnitId = (string) old('property_unit_id', optional($hubUnits->first())->id ?? (is_array($hubUnits->first()) ? ($hubUnits->first()['id'] ?? '') : ''));
    @endphp
    <form method="post" action="{{ route('property.maintenance.requests.store') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="property_id" value="{{ $property->id }}" />
        <input type="hidden" name="return_to" value="property_show" />
        <input type="hidden" name="return_property_id" value="{{ $property->id }}" />
        <input type="hidden" name="return_tab" value="maintenance" />

        @if ($hubUnits->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Add a unit first, then log maintenance.</div>
        @else
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                <select name="property_unit_id" required data-property-searchable="true" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @foreach ($hubUnits as $u)
                        @php
                            $uid = is_array($u) ? ($u['id'] ?? '') : $u->id;
                            $ulabel = is_array($u) ? ($u['label'] ?? '') : $u->label;
                        @endphp
                        <option value="{{ $uid }}" @selected((string) old('property_unit_id', $defaultUnitId) === (string) $uid)>{{ $ulabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                <input type="text" name="category" value="{{ old('category') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Plumbing, electrical…" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Urgency</label>
                <select name="urgency" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="normal" @selected(old('urgency', 'normal') === 'normal')>Normal</option>
                    <option value="urgent" @selected(old('urgency') === 'urgent')>Urgent</option>
                    <option value="emergency" @selected(old('urgency') === 'emergency')>Emergency</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                <textarea name="description" rows="3" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit request</button>
        @endif
    </form>
</x-property.modal>
@endif
