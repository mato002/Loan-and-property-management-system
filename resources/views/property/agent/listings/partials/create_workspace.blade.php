@if ($vacantUnits->isEmpty())
    <div class="rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-950/30 p-6">
        <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">No vacant units yet</p>
        <p class="mt-2 text-sm text-amber-900/80 dark:text-amber-200/90">Add a unit and set status to vacant before you can create a public listing.</p>
        <a
            href="{{ route('property.properties.units', absolute: false) }}"
            data-turbo-frame="property-main"
            data-property-nav="property.properties.units"
            class="mt-4 inline-flex rounded-xl bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600"
        >Go to Units</a>
    </div>
@else
    <section id="vacant-roster" class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Vacant units</h2>
        </div>

        <div class="flex flex-wrap items-end gap-2 print-hide">
            <input
                type="search"
                data-table-filter="parent"
                autocomplete="off"
                placeholder="Search unit, property, rent…"
                class="w-full min-w-0 sm:max-w-md min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
            />
            <select
                data-table-filter="parent"
                class="w-full min-w-0 sm:w-auto min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
            >
                <option value="">All statuses</option>
                <option value="featured">Featured</option>
                <option value="standard">On Discover</option>
            </select>
            <select
                data-table-filter="parent"
                class="w-full min-w-0 sm:w-auto min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
            >
                <option value="">All photos</option>
                <option value="with photos">With photos</option>
                <option value="no photos">No photos</option>
            </select>
        </div>

        <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Unit</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Property</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Asking rent</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Vacant since</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Photos</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Status</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vacantUnits as $u)
                        @php
                            $statusWord = $u->public_listing_published ? 'featured' : 'standard';
                            $photoWord = $u->publicImages->isNotEmpty() ? 'with photos' : 'no photos';
                            $isSelected = $selectedUnit && (int) $selectedUnit->id === (int) $u->id;
                            $filterText = mb_strtolower(
                                implode(' ', [
                                    (string) $u->label,
                                    (string) $u->property->name,
                                    (string) $u->rent_amount,
                                    \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount),
                                    $u->vacant_since?->format('Y-m-d') ?? '',
                                    (string) $u->publicImages->count(),
                                    $statusWord,
                                    $photoWord,
                                ])
                            );
                        @endphp
                        <tr
                            @class([
                                'border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40',
                                'bg-blue-50/80 dark:bg-blue-950/30 ring-1 ring-inset ring-blue-200/80 dark:ring-blue-800/60' => $isSelected,
                            ])
                            data-filter-text="{{ e($filterText) }}"
                        >
                            <td class="px-3 sm:px-4 py-3 text-slate-900 dark:text-white font-medium">{{ $u->label }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">{{ $u->property->name }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount) }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400">{{ $u->vacant_since?->format('d M Y') ?? '—' }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ $u->publicImages->count() }}</td>
                            <td class="px-3 sm:px-4 py-3">
                                @if ($u->public_listing_published)
                                    <span class="inline-flex rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-0.5 text-xs font-semibold">Featured</span>
                                @else
                                    <span class="inline-flex rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-200 px-2 py-0.5 text-xs font-semibold">On Discover</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-4 py-3">
                                <a
                                    href="{{ route('property.listings.create', ['selected_unit' => $u->id], absolute: false) }}#listing-publish"
                                    data-turbo-frame="property-main"
                                    data-property-nav="property.listings.create"
                                    class="text-blue-600 dark:text-blue-400 font-medium hover:underline"
                                >Photos &amp; publish</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedUnit)
        <section id="listing-publish" class="mt-8 scroll-mt-24 space-y-4">
            @include('property.agent.listings.partials.publish_editor', ['selectedUnit' => $selectedUnit])
        </section>
    @endif
@endif

@if ($selectedUnit)
    <script>
        (function scrollListingPublishEditor() {
            const scrollToEditor = () => {
                if (window.location.hash !== '#listing-publish') {
                    return;
                }
                const editor = document.getElementById('listing-publish');
                if (editor) {
                    editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            };
            document.addEventListener('turbo:load', scrollToEditor);
            document.addEventListener('turbo:frame-load', (event) => {
                if (event.target?.id === 'property-main') {
                    scrollToEditor();
                }
            });
            if (document.readyState !== 'loading') {
                scrollToEditor();
            }
        })();
    </script>
@endif
