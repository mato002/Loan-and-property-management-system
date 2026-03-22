<x-property-layout>
    <x-slot name="header">Public listing — {{ $unit->property->name }} / {{ $unit->label }}</x-slot>

    <x-property.page
        title="Public website listing"
        subtitle="Photos appear on Discover Properties and the listing detail page (same design as today’s public site)."
    >
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <a
                href="{{ route('property.listings.vacant') }}"
                class="inline-flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
            >
                ← Back to vacant listings
            </a>
            @if ($unit->public_listing_published)
                <a
                    href="{{ route('public.property_details', $unit->id) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center text-sm font-medium text-slate-600 dark:text-slate-300 hover:underline"
                >
                    View on public site ↗
                </a>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-2 max-w-5xl">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Upload photos</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">JPEG, PNG, or WebP — up to 5 MB each, 12 files per batch. Run <code class="text-xs bg-slate-100 dark:bg-slate-900 px-1 rounded">php artisan storage:link</code> if images 404 locally.</p>
                <form
                    method="post"
                    action="{{ route('property.listings.vacant.public.photos.store', $unit) }}"
                    enctype="multipart/form-data"
                    class="space-y-3"
                >
                    @csrf
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required class="block w-full text-sm text-slate-600 dark:text-slate-300" />
                    @error('photos')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('photos.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Upload</button>
                </form>

                <div class="border-t border-slate-200 dark:border-slate-600 pt-4 space-y-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gallery ({{ $unit->publicImages->count() }})</h4>
                    <ul class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach ($unit->publicImages as $img)
                            <li class="relative group rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 aspect-[4/3]">
                                <img src="{{ $img->publicUrl() }}" alt="" class="w-full h-full object-cover" />
                                <form
                                    method="post"
                                    action="{{ route('property.listings.vacant.public.photos.destroy', [$unit, $img]) }}"
                                    class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                    onsubmit="return confirm('Remove this photo?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md bg-red-600 text-white text-xs px-2 py-1 font-medium hover:bg-red-700">Remove</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

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
                            <span class="block text-xs text-slate-500 mt-0.5">Requires at least one photo. Only vacant units are shown.</span>
                        </span>
                    </label>
                    @error('public_listing_published')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save</button>
                </form>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
