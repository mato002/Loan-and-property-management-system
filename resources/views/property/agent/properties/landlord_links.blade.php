<x-property.workspace
    :legacy-toolbar="false"
    title="Landlord links"
    subtitle="Manage ownership shares and agreed payment schedules for each property and landlord."
    back-route="property.properties.index"
    :stats="$stats"
    :show-search="false"
    empty-title="No landlord links"
    empty-hint="Link a landlord to a property to manage the ownership share and payment schedule here."
>
    <x-slot name="actions">
        <a href="{{ route('property.properties.list', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
            <i class="fa-solid fa-link" aria-hidden="true"></i>
            <span>Link a landlord</span>
        </a>
    </x-slot>

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
        <p class="text-xs text-slate-500 dark:text-slate-400">Update ownership % or detach. New links are rejected if total ownership on a property would exceed 100%.</p>
        @if ($landlordLinks->isEmpty())
            <div class="rounded-lg border border-dashed border-slate-300 dark:border-slate-600 px-4 py-10 text-center">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">No landlord links yet</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Link a landlord from the Properties page to see it here.</p>
            </div>
        @else
            <div class="max-h-[70vh] overflow-auto">
                <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200 dark:[&_th]:border-slate-700 dark:[&_td]:border-slate-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-600">
                            <th class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-3 py-2">Property</th>
                            <th class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-3 py-2">Landlord</th>
                            <th class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-3 py-2">Email</th>
                            <th class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-3 py-2">Ownership &amp; pay schedule</th>
                            <th class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($landlordLinks as $link)
                            <tr class="border-b border-slate-100 dark:border-slate-700/80">
                                <td class="px-3 py-2 text-slate-900 dark:text-white">{{ $link->property_name }}</td>
                                <td class="px-3 py-2">{{ $link->user_name }}</td>
                                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $link->user_email ?: '—' }}</td>
                                <td class="px-3 py-2">
                                    <form
                                        method="post"
                                        action="{{ route('property.properties.landlords.ownership') }}"
                                        data-turbo-frame="property-main"
                                        data-turbo="false"
                                        class="min-w-[14rem] space-y-2"
                                    >
                                        @csrf
                                        <input type="hidden" name="property_id" value="{{ $link->property_id }}" />
                                        <input type="hidden" name="user_id" value="{{ $link->user_id }}" />
                                        <input type="hidden" name="return_to" value="landlord_links" />
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input
                                                type="number"
                                                name="ownership_percent"
                                                value="{{ old('ownership_percent', $link->ownership_percent) }}"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                class="w-24 rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1 text-sm"
                                            />
                                            <span class="text-xs text-slate-500">%</span>
                                            <button type="submit" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">Save</button>
                                        </div>
                                        @include('property.agent.properties.partials.agreed_pay_fields', [
                                            'agreedPayDay' => $link->agreed_pay_day ?? null,
                                            'agreedPayNotes' => $link->agreed_pay_notes ?? null,
                                            'settlementBasis' => $link->settlement_basis ?? 'collected',
                                            'commissionBasis' => $link->commission_basis ?? 'all_collected',
                                            'providerCostPayer' => $link->provider_cost_payer ?? 'agency',
                                            'advancesAllowed' => $link->advances_allowed ?? true,
                                            'compact' => true,
                                        ])
                                    </form>
                                </td>
                                <td class="px-3 py-2">
                                    <form
                                        method="post"
                                        action="{{ route('property.properties.landlords.detach') }}"
                                        data-turbo-frame="property-main"
                                        data-turbo="false"
                                        data-swal-title="Detach landlord?"
                                        data-swal-confirm="Unlink this landlord from this property?"
                                        data-swal-confirm-text="Yes, detach"
                                    >
                                        @csrf
                                        <input type="hidden" name="property_id" value="{{ $link->property_id }}" />
                                        <input type="hidden" name="user_id" value="{{ $link->user_id }}" />
                                        <input type="hidden" name="return_to" value="landlord_links" />
                                        <button type="submit" class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Detach</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-property.workspace>
