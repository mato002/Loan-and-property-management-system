<x-property.workspace
    title="Setup a public listing"
    subtitle="Vacant units appear on the public Discover page as soon as they exist. Use this flow to add photos, description, and publish to feature a unit with a full gallery."
    back-route="property.listings.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="toolbar">
        <a
            href="{{ route('property.listings.vacant') }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            Full vacant roster
        </a>
        <a
            href="{{ route('property.properties.units') }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            Properties → Units
        </a>
    </x-slot>

    <div class="space-y-6 max-w-2xl">
        <ol class="list-decimal list-inside space-y-2 text-sm text-slate-600 dark:text-slate-300">
            <li><span class="font-medium text-slate-800 dark:text-slate-100">Building &amp; unit</span> exist under <strong>Properties → Units</strong> (unit must be <strong>vacant</strong>).</li>
            <li><span class="font-medium text-slate-800 dark:text-slate-100">Photos &amp; description</span> on the next screen (stored only for the public website).</li>
            <li><span class="font-medium text-slate-800 dark:text-slate-100">Publish</span> (with photos) to feature the unit with a gallery; the unit is already visible on Discover while vacant.</li>
        </ol>

        @if ($vacantUnits->isEmpty())
            <div class="rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-950/30 p-6">
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">No vacant units yet</p>
                <p class="mt-2 text-sm text-amber-900/80 dark:text-amber-200/90">Add a unit and set status to vacant before you can create a public listing.</p>
                <a
                    href="{{ route('property.properties.units') }}"
                    class="mt-4 inline-flex rounded-xl bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600"
                >Go to Units</a>
            </div>
        @else
            <form
                method="post"
                action="{{ route('property.listings.start') }}"
                class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4"
            >
                @csrf
                <div>
                    <label for="property_unit_id" class="block text-sm font-medium text-slate-800 dark:text-slate-100">Vacant unit</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose which door you are listing; you will upload photos on the next page.</p>
                    <select
                        id="property_unit_id"
                        name="property_unit_id"
                        required
                        class="mt-2 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                    >
                        <option value="">Select property / unit…</option>
                        @foreach ($vacantUnits as $u)
                            <option value="{{ $u->id }}" @selected(old('property_unit_id') == $u->id)>
                                {{ $u->property->name }} — {{ $u->label }}
                                @if ($u->public_listing_published)
                                    (featured)
                                @elseif ($u->publicImages->isNotEmpty())
                                    (photos · {{ $u->publicImages->count() }})
                                @else
                                    (on Discover · no photos yet)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('property_unit_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Continue to photos &amp; publish
                </button>
            </form>
        @endif
    </div>
</x-property.workspace>
