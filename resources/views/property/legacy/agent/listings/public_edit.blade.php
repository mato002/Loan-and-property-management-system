<x-property-layout>
    <x-slot name="header">Public listing — {{ $unit->property->name }} / {{ $unit->label }}</x-slot>

    <x-property.page
        title="Public website listing"
    >
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <a
                href="{{ route('property.listings.vacant') }}"
                class="inline-flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
            >
                ← Back to vacant listings
            </a>
            <a
                href="{{ route('public.property_details', $unit->id) }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center text-sm font-medium text-slate-600 dark:text-slate-300 hover:underline"
            >
                View on public site ↗
            </a>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 max-w-5xl">
            @include('property.agent.listings.partials.public_media_upload', ['unit' => $unit])

            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Description &amp; publish</h3>
                <form method="post" action="{{ route('property.listings.vacant.public.update', $unit) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="public_listing_description">Public description</label>
                        <textarea
                            id="public_listing_description"
                            name="public_listing_description"
                            rows="8"
                            class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                        >{{ old('public_listing_description', $unit->public_listing_description) }}</textarea>
                        @error('public_listing_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            name="public_listing_published"
                            value="1"
                            class="mt-1 rounded border-slate-300 text-blue-600"
                            @checked(old('public_listing_published', $unit->public_listing_published))
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            <span class="font-medium text-slate-900 dark:text-white">Published on public website</span>
                        </span>
                    </label>
                    @error('public_listing_published')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save</button>
                </form>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
