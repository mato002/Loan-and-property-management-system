@php
    $linkableProperties = $linkableProperties ?? collect();
    $monthValue = $monthValue ?? '';
    $fyValue = $fyValue ?? null;
@endphp

{{-- Link property to this landlord --}}
<x-property.modal
    show="showHubLinkProperty"
    close="showHubLinkProperty = false"
    name="landlord-hub-link-property"
    title="Link property"
    max-width="2xl"
>
    <form method="post" action="{{ route('property.properties.landlords.attach') }}" class="space-y-3" data-turbo-frame="property-main">
        @csrf
        <input type="hidden" name="user_id" value="{{ $landlord->id }}" />
        <input type="hidden" name="return_to" value="landlord_show" />
        <input type="hidden" name="return_landlord_id" value="{{ $landlord->id }}" />
        <input type="hidden" name="return_tab" value="properties" />
        @if ($monthValue !== '')
            <input type="hidden" name="return_month" value="{{ $monthValue }}" />
        @endif
        @if ($fyValue)
            <input type="hidden" name="return_fy" value="{{ $fyValue }}" />
        @endif

        <p class="text-xs text-slate-500">Attach an unlinked property to <span class="font-semibold text-slate-800">{{ $landlord->name }}</span>.</p>

        @if ($linkableProperties->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                No unlinked properties available. Create a property first, or unlink one from another landlord.
            </div>
        @else
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" required data-property-searchable="true" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @foreach ($linkableProperties as $p)
                        <option value="{{ $p->id }}" @selected((string) old('property_id') === (string) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ownership %</label>
                <input type="number" name="ownership_percent" value="{{ old('ownership_percent', 100) }}" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Link property</button>
        @endif
    </form>
</x-property.modal>
