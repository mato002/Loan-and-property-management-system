<x-property.workspace
    title="Landlords"
    subtitle="Landlord intelligence desk: profile, ownership shares, collections, pending receivables, and your earnings in one place."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.landlords.index', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All landlords</a>
                <a href="{{ route('property.landlords.index', array_merge((array) ($filters ?? []), ['linked' => 'linked']), absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Linked only</a>
                <a href="{{ route('property.landlords.index', array_merge((array) ($filters ?? []), ['linked' => 'unlinked']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Unlinked only</a>
                <a href="{{ route('property.landlords.index', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Export CSV</a>
            </div>
        </div>

        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Landlord command center</p>
            <p class="mt-1 text-sm text-slate-600">Track each landlord's portfolio share, money position, and your commission. Use the directives below for instant deep-dives and actions.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Link landlord to property
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.properties.list', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Properties
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.financials.owner_balances', absolute: false, parameters: ['month' => $monthValue, 'fy' => $fyValue]) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Owner balances
                    <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.financials.commission', absolute: false, parameters: ['month' => $monthValue, 'fy' => $fyValue]) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Commission report
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                </a>
            </div>
            <p class="mt-2 text-xs text-slate-500">Period: <span class="font-semibold">{{ $periodLabel }}</span> · Commission rate used: <span class="font-semibold">{{ number_format((float) ($commissionPct ?? 0), 2) }}%</span></p>
        </div>

        <form method="post" action="{{ route('property.landlords.onboard') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Onboard landlord</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Full name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
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
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.landlords.index') }}" class="w-full flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Name, email, property…" class="w-full min-w-0 sm:min-w-[15rem] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Link status</label>
                <select name="linked" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                    <option value="all" @selected(($filters['linked'] ?? 'all') === 'all')>All</option>
                    <option value="linked" @selected(($filters['linked'] ?? '') === 'linked')>Linked only</option>
                    <option value="unlinked" @selected(($filters['linked'] ?? '') === 'unlinked')>Unlinked only</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                    <option value="0">All</option>
                    @foreach($properties as $p)
                        <option value="{{ $p->id }}" @selected((int) ($filters['property_id'] ?? 0) === (int) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Share level</label>
                <select name="share_level" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                    <option value="all" @selected(($filters['share_level'] ?? 'all') === 'all')>All</option>
                    <option value="high" @selected(($filters['share_level'] ?? '') === 'high')>High (>=100%)</option>
                    <option value="medium" @selected(($filters['share_level'] ?? '') === 'medium')>Medium (30-99%)</option>
                    <option value="low" @selected(($filters['share_level'] ?? '') === 'low')>Low (1-29%)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label>
                <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">FY</label>
                <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 w-24" />
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.landlords.index', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="inline-flex items-center justify-center rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
            <a href="{{ route('property.landlords.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>
    </x-slot>

    <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Name</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Email</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Properties</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Ownership %</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Owner share</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Pending share</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">My earnings</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Last collection</th>
                    <th class="px-3 sm:px-4 py-3 min-w-[16rem]">Buildings</th>
                    <th class="px-3 sm:px-4 py-3 min-w-[18rem]">Actions & directives</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($landlords as $u)
                    @php
                        $props = $u->landlordProperties;
                        $names = $props->pluck('name')->all();
                        $namesLine = $props->isEmpty() ? '' : implode(', ', $names);
                        $filterText = mb_strtolower(
                            implode(' ', array_filter([$u->name, $u->email, $namesLine]))
                        );
                    @endphp
                    <tr
                        class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                        data-filter-text="{{ e($filterText) }}"
                    >
                        <td class="px-3 sm:px-4 py-3 text-slate-900 dark:text-white font-medium">{{ $u->name }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ $u->email }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ (int) ($u->linked_count ?? $props->count()) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ number_format((float) ($u->ownership_sum ?? 0), 2) }}%</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($u->available_share ?? 0)) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($u->pending_share ?? 0)) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) ($u->agent_earning ?? 0)) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">
                            {{ !empty($u->last_paid_at) ? \Illuminate\Support\Carbon::parse((string) $u->last_paid_at)->format('Y-m-d') : '—' }}
                        </td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-300">
                            @if ($props->isEmpty())
                                <span class="text-slate-400 dark:text-slate-500">Not linked — use “Link landlord to property”</span>
                            @else
                                <span class="leading-relaxed">{{ $namesLine }}</span>
                            @endif
                        </td>
                        <td class="px-3 sm:px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('property.landlords.show', ['landlord' => $u->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-blue-300 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-50">View</a>
                                <a href="{{ route('property.landlords.statement', ['landlord' => $u->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-emerald-300 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Statement</a>
                                <a href="{{ route('property.financials.owner_balances', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Owner balances</a>
                                <a href="{{ route('property.financials.commission', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">Commission</a>
                                @if ($props->isNotEmpty())
                                    <a href="{{ route('property.properties.show', ['property' => $props->first()->id], false) }}" data-turbo-frame="_top" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Open property</a>
                                @endif
                                <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Adjust links</a>
                                <a href="mailto:{{ $u->email }}" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Email</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-14 text-center align-middle">
                            <p class="font-medium text-slate-700 dark:text-slate-200">No landlord accounts yet</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">Register users with the landlord portal role, then attach them to properties from the property list.</p>
                            <a href="{{ route('property.properties.list') }}" class="mt-4 inline-flex text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Open property list</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (session()->has('next_steps') && ($ns = session('next_steps')) && is_array($ns))
        <div
            x-data="{ open: true }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40"
        >
            <div class="mx-4 max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/10">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                        Landlord onboarding
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">
                        {{ $ns['title'] ?? 'Landlord onboarded' }}
                    </h2>
                    @php
                        $summary = $ns['landlord'] ?? null;
                    @endphp
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $ns['message'] ?? 'Continue with the onboarding steps below.' }}
                    </p>
                    @if (is_array($summary))
                        <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-slate-600">
                            <div class="flex justify-between gap-3">
                                <dt class="font-medium text-slate-700">Landlord</dt>
                                <dd class="text-right">{{ $summary['name'] ?? '' }}</dd>
                            </div>
                            @if (!empty($summary['email']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Email</dt>
                                    <dd class="text-right">{{ $summary['email'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endif
                </div>
                <div class="px-5 py-4 space-y-3">
                    <p class="text-xs font-medium text-slate-500">
                        Choose where to go next:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($ns['actions'] ?? [] as $action)
                            @php
                                $kind = $action['kind'] ?? 'secondary';
                                $isPrimary = $kind === 'primary';
                                $frame = $action['turbo_frame'] ?? null;
                            @endphp
                            <a
                                href="{{ $action['href'] ?? '#' }}"
                                @if ($frame) data-turbo-frame="{{ $frame }}" @endif
                                class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold
                                    {{ $isPrimary ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-slate-300 text-slate-700 bg-white hover:bg-slate-50' }}"
                                @click="open = false"
                            >
                                @if (!empty($action['icon']))
                                    <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                                @endif
                                <span>{{ $action['label'] ?? 'Continue' }}</span>
                            </a>
                        @endforeach
                    </div>
                    <button
                        type="button"
                        class="mt-1 text-xs font-medium text-slate-500 hover:text-slate-700"
                        @click="open = false"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-property.workspace>
