@php
    /** @var \App\Models\PropertyUnit $selectedUnit */
@endphp

<div id="listing-publish" class="rounded-2xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/40 dark:bg-blue-950/20 p-4 sm:p-5 space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Publish editor</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $selectedUnit->property->name }} — <span class="font-medium text-slate-800 dark:text-slate-200">{{ $selectedUnit->label }}</span>
            </p>
        </div>
        <button
            type="button"
            data-listing-publish-close
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            Back to roster
        </button>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @include('property.agent.listings.partials.public_media_upload', ['unit' => $selectedUnit])

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Description &amp; publish</h3>
            <form method="post" action="{{ route('property.listings.vacant.public.update', $selectedUnit) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="public_listing_description">Public description</label>
                    <textarea
                        id="public_listing_description"
                        name="public_listing_description"
                        rows="8"
                        class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                    >{{ old('public_listing_description', $selectedUnit->public_listing_description) }}</textarea>
                    @error('public_listing_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="public_listing_published"
                        value="1"
                        class="mt-1 rounded border-slate-300 text-blue-600"
                        @checked(old('public_listing_published', $selectedUnit->public_listing_published))
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
</div>
