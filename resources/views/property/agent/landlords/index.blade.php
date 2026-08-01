<x-property.workspace
    title="Landlords"
    subtitle="Landlord intelligence desk: profile, ownership shares, collections, pending receivables, and your earnings in one place."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    :column-config="$columnConfig"
    :responsive-cards="false"
    :legacy-toolbar="false"
    :show-search="false"
    table-min-width="880px"
    empty-title="No landlord accounts yet"
    empty-hint="Register users with the landlord portal role, then attach them to properties from the property list."
>
    @php
        $landlordCreateFormHasErrors = $errors->has('name')
            || $errors->has('email')
            || $errors->has('phone')
            || $errors->has('property_id')
            || $errors->has('ownership_percent');
        $landlordFieldCfg = $landlordFields ?? [];
        $landlordRequired = fn (string $k, bool $d = false) => (bool) (($landlordFieldCfg[$k]['required'] ?? $d) && ($landlordFieldCfg[$k]['enabled'] ?? true));
    @endphp

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.landlords')
    </x-slot>

    <x-slot name="above">
        @php
            $onboardLandlord = null;
            if (is_array($portalCredentials ?? null)) {
                $onboardLandlord = \App\Models\User::query()->find((int) ($portalCredentials['landlord_id'] ?? 0));
            } elseif (is_array(session('landlord_portal_credentials'))) {
                $onboardLandlord = \App\Models\User::query()->find((int) session('landlord_portal_credentials.landlord_id'));
            }
        @endphp
        @if ($onboardLandlord)
            @include('property.agent.landlords.partials.portal-credentials-banner', [
                'landlord' => $onboardLandlord,
                'portalCredentials' => $portalCredentials ?? session('landlord_portal_credentials'),
            ])
        @endif

        <div x-data="{ showLandlordCreateForm: @js($landlordCreateFormHasErrors) }" class="space-y-3 sm:space-y-4 w-full min-w-0">
            <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 sm:p-4 shadow-sm w-full min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('property.landlords.index', absolute: false) }}" class="inline-flex min-h-[40px] items-center rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All landlords</a>
                    <a href="{{ route('property.landlords.index', array_merge((array) ($filters ?? []), ['linked' => 'linked']), absolute: false) }}" class="inline-flex min-h-[40px] items-center rounded-lg border border-indigo-300 px-3 py-2 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Linked only</a>
                    <a href="{{ route('property.landlords.index', array_merge((array) ($filters ?? []), ['linked' => 'unlinked']), absolute: false) }}" class="inline-flex min-h-[40px] items-center rounded-lg border border-amber-300 px-3 py-2 text-xs font-medium text-amber-700 hover:bg-amber-50">Unlinked only</a>
                    <a href="{{ route('property.landlords.index', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="inline-flex min-h-[40px] items-center rounded-lg border border-emerald-300 px-3 py-2 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Export CSV</a>
                </div>
            </div>

            <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-950/30 dark:to-gray-900/40 dark:border-indigo-900/40 p-4 sm:p-5 shadow-sm w-full min-w-0">
                <p class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Landlord command center</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Track each landlord's portfolio share, money position, and your commission.</p>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-wrap gap-2">
                    <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                        Link landlord to property
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('property.properties.list', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">
                        Properties
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('property.financials.owner_balances', absolute: false, parameters: ['month' => $monthValue, 'fy' => $fyValue]) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">
                        Owner balances
                        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('property.financials.commission', absolute: false, parameters: ['month' => $monthValue, 'fy' => $fyValue]) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">
                        Commission report
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                    </a>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Period: <span class="font-semibold">{{ $periodLabel }}</span> · Default commission: <span class="font-semibold">{{ number_format((float) ($commissionDefaultPct ?? $commissionPct ?? 0), 2) }}%</span> (property overrides apply)</p>
            </div>

            <button
                type="button"
                class="inline-flex w-full sm:w-auto min-h-[48px] items-center justify-center gap-3 rounded-xl sm:rounded-2xl bg-blue-600 px-5 py-3.5 text-sm sm:text-base font-bold text-white shadow-lg shadow-blue-200/70 transition hover:bg-blue-700"
                @click="showLandlordCreateForm = true"
            >
                <i class="fa-solid fa-user-plus text-lg" aria-hidden="true"></i>
                <span>Create landlord account</span>
            </button>

            <x-property.modal
                show="showLandlordCreateForm"
                close="showLandlordCreateForm = false"
                name="landlord-create"
                title="Onboard landlord"
                max-width="3xl"
            >
            <form
                method="post"
                action="{{ route('property.landlords.onboard') }}"
                class="space-y-3 w-full min-w-0"
            >
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Onboard landlord</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Provide at least one of email or phone. A temporary password is generated automatically and sent by email and/or SMS when available.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Full name</label>
                        <input type="text" name="name" value="{{ old('name') }}" @required($landlordRequired('name', true)) class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email <span class="font-normal text-slate-400">(optional if phone provided)</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" @required($landlordRequired('email', false)) class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone <span class="font-normal text-slate-400">(optional if email provided)</span></label>
                        <input type="text" name="phone" value="{{ old('phone') }}" @required($landlordRequired('phone', false)) placeholder="e.g. 0712345678" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
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
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ownership % (if property selected)</label>
                    <input type="number" name="ownership_percent" value="{{ old('ownership_percent', '100') }}" min="0" max="100" step="0.01" class="mt-1 w-full sm:max-w-xs min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('ownership_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">Create landlord account</button>
            </form>
            </x-property.modal>
        </div>
    </x-slot>

    @if (session()->has('next_steps') && ($ns = session('next_steps')) && is_array($ns))
        <x-slot name="footer">
            <div
                x-data="{ open: true }"
                x-show="open"
                x-cloak
                class="fixed inset-0 z-40 flex items-end sm:items-center justify-center bg-slate-900/40 p-3 sm:p-4"
            >
                <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-gray-800 shadow-xl ring-1 ring-slate-900/10 max-h-[90vh] overflow-y-auto">
                    <div class="border-b border-slate-100 dark:border-slate-700 px-4 sm:px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Landlord onboarding</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $ns['title'] ?? 'Landlord onboarded' }}</h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $ns['message'] ?? 'Continue with the onboarding steps below.' }}</p>
                        @if (is_array($summary = $ns['landlord'] ?? null))
                            <dl class="mt-3 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                                <div class="flex justify-between gap-3"><dt class="font-medium">Landlord</dt><dd class="text-right">{{ $summary['name'] ?? '' }}</dd></div>
                                @if (!empty($summary['email']))<div class="flex justify-between gap-3"><dt class="font-medium">Email</dt><dd class="text-right break-all">{{ $summary['email'] }}</dd></div>@endif
                                @if (!empty($summary['phone']))<div class="flex justify-between gap-3"><dt class="font-medium">Phone</dt><dd class="text-right">{{ $summary['phone'] }}</dd></div>@endif
                            </dl>
                        @endif
                    </div>
                    <div class="px-4 sm:px-5 py-4 space-y-3">
                        <p class="text-xs font-medium text-slate-500">Choose where to go next:</p>
                        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2">
                            @foreach ($ns['actions'] ?? [] as $action)
                                @php
                                    $isPrimary = ($action['kind'] ?? 'secondary') === 'primary';
                                    $frame = $action['turbo_frame'] ?? null;
                                @endphp
                                <a
                                    href="{{ $action['href'] ?? '#' }}"
                                    @if ($frame) data-turbo-frame="{{ $frame }}" @endif
                                    class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold {{ $isPrimary ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200' }}"
                                    @click="open = false"
                                >
                                    @if (!empty($action['icon']))<i class="{{ $action['icon'] }}" aria-hidden="true"></i>@endif
                                    <span>{{ $action['label'] ?? 'Continue' }}</span>
                                </a>
                            @endforeach
                        </div>
                        <button type="button" class="min-h-[44px] text-xs font-medium text-slate-500 hover:text-slate-700" @click="open = false">Close</button>
                    </div>
                </div>
            </div>
        </x-slot>
    @endif
</x-property.workspace>
