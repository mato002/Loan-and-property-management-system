<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Landlords"
    subtitle="Landlord intelligence desk: profile, ownership shares, collections, pending receivables, and your earnings in one place."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="above">
        @php
            $landlordCreateFormHasErrors = $errors->has('name')
                || $errors->has('email')
                || $errors->has('password')
                || $errors->has('property_id')
                || $errors->has('ownership_percent');
            $landlordFieldCfg = $landlordFields ?? [];
            $landlordRequired = fn (string $k, bool $d = false) => (bool) (($landlordFieldCfg[$k]['required'] ?? $d) && ($landlordFieldCfg[$k]['enabled'] ?? true));
        @endphp
        <div x-data="{ showLandlordCreateForm: @js($landlordCreateFormHasErrors) }" class="space-y-4">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 md:p-4 shadow-sm">
            <x-property.responsive.quick-action-grid>
                <a href="{{ route('property.landlords.index', absolute: false) }}" class="quick-action-btn border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All</a>
                <a href="{{ route('property.landlords.index', array_merge((array) ($filters ?? []), ['linked' => 'linked']), absolute: false) }}" class="quick-action-btn border border-indigo-300 text-indigo-700 hover:bg-indigo-50">Linked</a>
                <a href="{{ route('property.landlords.index', array_merge((array) ($filters ?? []), ['linked' => 'unlinked']), absolute: false) }}" class="quick-action-btn border border-amber-300 text-amber-700 hover:bg-amber-50">Unlinked</a>
                <a href="{{ route('property.landlords.index', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="quick-action-btn border border-emerald-300 text-emerald-700 hover:bg-emerald-50">Export</a>
            </x-property.responsive.quick-action-grid>
        </div>

        <div class="rounded-xl sm:rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-3 md:p-5 shadow-sm max-w-3xl">
            <p class="text-base sm:text-lg font-semibold text-slate-900">Landlord command center</p>
            <p class="mt-1 text-xs sm:text-sm text-slate-600">Track each landlord's portfolio share, money position, and your commission. Use the directives below for instant deep-dives and actions.</p>
            <x-property.responsive.quick-action-grid class="mt-3">
                <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="quick-action-btn bg-blue-600 text-white hover:bg-blue-700">
                    Link landlord
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.properties.list', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                    Properties
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.financials.owner_balances', absolute: false, parameters: ['month' => $monthValue, 'fy' => $fyValue]) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                    Balances
                    <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.financials.commission', absolute: false, parameters: ['month' => $monthValue, 'fy' => $fyValue]) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                    Commission
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                </a>
            </x-property.responsive.quick-action-grid>
            <p class="mt-2 text-xs text-slate-500">Period: <span class="font-semibold">{{ $periodLabel }}</span> · Commission rate used: <span class="font-semibold">{{ number_format((float) ($commissionPct ?? 0), 2) }}%</span></p>
        </div>

        <div class="max-w-3xl">
            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-6 py-4 text-base font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 sm:w-auto"
                @click="showLandlordCreateForm = !showLandlordCreateForm"
            >
                <i class="fa-solid fa-user-plus text-lg" aria-hidden="true"></i>
                <span x-text="showLandlordCreateForm ? 'Hide landlord form' : 'Create landlord account'"></span>
            </button>
        </div>

        <form
            method="post"
            action="{{ route('property.landlords.onboard') }}"
            x-show="showLandlordCreateForm"
            x-cloak
            class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl"
        >
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Onboard landlord</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Full name</label>
                    <input type="text" name="name" value="{{ old('name') }}" @required($landlordRequired('name', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" @required($landlordRequired('email', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Temporary password</label>
                    <input type="text" name="password" value="{{ old('password') }}" required minlength="8" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Link to property (optional)</label>
                    <x-property.quick-create-select
                        name="property_id"
                        :required="false"
                        placeholder="Not now"
                        :options="collect($properties)->map(fn($p) => ['value' => $p->id, 'label' => $p->name, 'selected' => (string) old('property_id') === (string) $p->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create property',
                            'endpoint' => route('property.properties.store_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Property name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Prady Court'],
                                ['name' => 'code', 'label' => 'Code (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Auto if blank'],
                                ['name' => 'address_line', 'label' => 'Address (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Street / building'],
                                ['name' => 'city', 'label' => 'City (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Nairobi'],
                            ],
                        ]"
                    />
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ownership % (if property selected)</label>
                <input type="number" name="ownership_percent" value="{{ old('ownership_percent', '100') }}" min="0" max="100" step="0.01" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('ownership_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create landlord account</button>
        </form>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.landlords', [
            'filters' => $filters,
            'properties' => $properties,
            'monthValue' => $monthValue,
            'fyValue' => $fyValue,
        ])
    </x-slot>

    <div class="overflow-x-auto overflow-y-visible w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-normal break-words">Landlord</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-normal break-words">Links</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-normal break-words">Shares (KES)</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-normal break-words">Last collection</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-normal break-words">Buildings</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-normal break-words">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($landlords as $u)
                    @php
                        $props = $u->landlordProperties;
                        $names = $props->pluck('name')->all();
                        $namesLine = $props->isEmpty() ? '' : implode(', ', $names);
                        $buildingPreview = $props->pluck('name')->take(2)->implode(', ');
                        $remainingBuildings = max(0, $props->count() - 2);
                        $filterText = mb_strtolower(
                            implode(' ', array_filter([$u->name, $u->email, $namesLine]))
                        );
                    @endphp
                    <tr
                        class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40 cursor-pointer"
                        data-filter-text="{{ e($filterText) }}"
                        data-row-href="{{ route('property.landlords.show', ['landlord' => $u->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}"
                        tabindex="0"
                        role="link"
                        aria-label="Open landlord profile"
                    >
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">
                            <div class="font-medium text-slate-900 dark:text-white">{{ $u->name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 break-words">{{ $u->email }}</div>
                        </td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">
                            <div>{{ (int) ($u->linked_count ?? $props->count()) }} properties</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ number_format((float) ($u->ownership_sum ?? 0), 2) }}% ownership</div>
                        </td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">
                            <div class="text-xs">Owner: {{ \App\Services\Property\PropertyMoney::kes((float) ($u->available_share ?? 0)) }}</div>
                            <div class="text-xs">Pending: {{ \App\Services\Property\PropertyMoney::kes((float) ($u->pending_share ?? 0)) }}</div>
                            <div class="text-xs font-semibold">My: {{ \App\Services\Property\PropertyMoney::kes((float) ($u->agent_earning ?? 0)) }}</div>
                        </td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">
                            {{ !empty($u->last_paid_at) ? \Illuminate\Support\Carbon::parse((string) $u->last_paid_at)->format('Y-m-d') : '—' }}
                        </td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-300">
                            @if ($props->isEmpty())
                                <span class="text-slate-400 dark:text-slate-500">Not linked — use “Link landlord to property”</span>
                            @else
                                <span class="leading-relaxed break-words">
                                    {{ $buildingPreview }}
                                    @if ($remainingBuildings > 0)
                                        , +{{ $remainingBuildings }} more
                                    @endif
                                </span>
                            @endif
                        </td>
                        <td class="px-3 sm:px-4 py-3 overflow-visible">
                            <div class="relative inline-block text-left" data-row-ignore-click>
                                <details data-dropdown-root>
                                    <summary data-dropdown-trigger class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 min-h-[44px] sm:min-h-0 inline-flex items-center">
                                        Actions <span class="text-slate-400">▼</span>
                                    </summary>
                                    <div data-dropdown-menu class="absolute right-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                        <a href="{{ route('property.landlords.show', ['landlord' => $u->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50">View</a>
                                        <a href="{{ route('property.landlords.statement', ['landlord' => $u->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50">Statement</a>
                                        <a href="{{ route('property.financials.owner_balances', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Owner balances</a>
                                        <a href="{{ route('property.financials.commission', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50">Commission</a>
                                        @if (auth()->check() && auth()->user()?->hasPmPermission('users.impersonate'))
                                            <form
                                                method="post"
                                                action="{{ route('property.landlords.impersonate', ['landlord' => $u->id], false) }}"
                                                data-turbo="false"
                                                data-turbo-frame="property-main"
                                                data-swal-title="Login as landlord?"
                                                data-swal-confirm="You will temporarily view the portal as this landlord. Use “Stop impersonating” to return."
                                                data-swal-confirm-text="Yes, continue"
                                            >
                                                @csrf
                                                <button type="submit" class="block w-full px-3 py-2 text-left text-xs text-amber-800 hover:bg-amber-50">Login as</button>
                                            </form>
                                        @endif
                                        @if ($props->isNotEmpty())
                                            <a href="{{ route('property.properties.show', ['property' => $props->first()->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Open property</a>
                                        @endif
                                        <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Adjust links</a>
                                        <a href="mailto:{{ $u->email }}" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Email</a>
                                    </div>
                                </details>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-14 text-center align-middle">
                            <p class="font-medium text-slate-700 dark:text-slate-200">No landlord accounts yet</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">Register users with the landlord portal role, then attach them to properties from the property list.</p>
                            <a href="{{ route('property.properties.list') }}" class="mt-4 inline-flex text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Open property list</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const interactiveSelector = 'a, button, input, select, textarea, label, [role="button"], [data-row-ignore-click]';

            document.querySelectorAll('tr[data-row-href]').forEach(function (row) {
                const href = row.getAttribute('data-row-href');
                if (!href) return;

                row.addEventListener('click', function (event) {
                    if (event.target && event.target.closest(interactiveSelector)) return;
                    window.location.href = href;
                });

                row.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    if (event.target && event.target.closest(interactiveSelector)) return;
                    event.preventDefault();
                    window.location.href = href;
                });
            });
        });
    </script>
</x-property.workspace>
