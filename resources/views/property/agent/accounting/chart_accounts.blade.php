<x-property.workspace
    title="Chart of accounts"
    subtitle="Grouped account hierarchy with protection and usage controls."
    back-route="property.accounting.index"
    :stats="[
        ['label' => 'Total Accounts', 'value' => (string) ($summary['total_accounts'] ?? 0), 'hint' => 'All filtered accounts'],
        ['label' => 'Assets Balance', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($summary['assets_balance'] ?? 0)), 'hint' => 'Debit-normal totals'],
        ['label' => 'Liabilities Balance', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($summary['liabilities_balance'] ?? 0)), 'hint' => 'Credit-normal totals'],
        ['label' => 'Income Balance', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($summary['income_balance'] ?? 0)), 'hint' => 'Posted income'],
        ['label' => 'Expenses Balance', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($summary['expenses_balance'] ?? 0)), 'hint' => 'Posted expenses'],
        ['label' => 'Disabled Accounts', 'value' => (string) ($summary['disabled_accounts'] ?? 0), 'hint' => 'Inactive chart rows'],
    ]"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        <button type="button" id="coa-open-create" class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add Account</button>
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.gl.chart_accounts.export', array_merge(request()->query(), ['format' => 'csv'])),
            'xlsUrl' => route('property.accounting.gl.chart_accounts.export', array_merge(request()->query(), ['format' => 'xls'])),
        ])
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.gl.chart_accounts') }}" class="flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search code/name…" class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-52" />
            <select name="type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Type: All</option>
                @foreach (($typeOptions ?? []) as $t)
                    <option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Status: All</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="disabled" @selected(($filters['status'] ?? '') === 'disabled')>Disabled</option>
            </select>
            <select name="system_filter" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">System: All</option>
                <option value="system" @selected(($filters['system_filter'] ?? '') === 'system')>System only</option>
                <option value="custom" @selected(($filters['system_filter'] ?? '') === 'custom')>Custom only</option>
            </select>
            <select name="usage" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Usage: All</option>
                @foreach (($usageOptions ?? []) as $u)
                    <option value="{{ $u }}" @selected(($filters['usage'] ?? '') === $u)>{{ ucfirst($u) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Apply</button>
            <a href="{{ route('property.accounting.gl.chart_accounts') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Clear filters</a>
        </form>
    </x-slot>

    @if (collect($groups ?? [])->isEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-center">
            <p class="text-sm text-slate-700">No accounts found for the selected filters.</p>
            <a href="{{ route('property.accounting.gl.chart_accounts') }}" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Clear filters</a>
        </div>
    @endif

    <div class="space-y-4">
        @foreach (($groups ?? []) as $group)
            @php $groupKey = 'coa-group-'.strtolower((string) $group['type']); @endphp
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm" x-data="propertySidebarGroup(@js($groupKey), false)">
                <button type="button" class="w-full flex items-center justify-between px-4 py-3 text-left" @click="toggleGroup()">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $group['label'] }}</p>
                        <p class="text-xs text-slate-500">Total: {{ \App\Services\Property\PropertyMoney::kes((float) $group['total_balance']) }} | {{ $group['count'] }} accounts</p>
                    </div>
                    <i class="fa-solid fa-chevron-down text-xs text-slate-500 transition-transform" :class="{ 'rotate-180': open }"></i>
                </button>
                <div x-show="open" x-cloak class="px-4 pb-4">
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                            <tr class="text-left text-slate-500 border-b">
                                <th class="py-2 pr-3">Code</th>
                                <th class="py-2 pr-3">Account Name</th>
                                <th class="py-2 pr-3">Type</th>
                                <th class="py-2 pr-3">Parent</th>
                                <th class="py-2 pr-3">Balance</th>
                                <th class="py-2 pr-3">Usage</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 pr-3">Protection</th>
                                <th class="py-2">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach (($group['rows'] ?? []) as $row)
                                @php
                                    $account = $row['model'];
                                    $indent = (int) ($row['level'] ?? 0) * 16;
                                    $usage = collect($row['usage'] ?? []);
                                    $protection = $row['is_control'] ? 'Control' : ($row['is_system'] ? 'System' : 'Custom');
                                @endphp
                                <tr class="border-b last:border-b-0">
                                    <td class="py-2 pr-3 font-mono">{{ $row['code'] }}</td>
                                    <td class="py-2 pr-3">
                                        <div style="padding-left: {{ $indent }}px">
                                            {{ $row['name'] }}
                                        </div>
                                    </td>
                                    <td class="py-2 pr-3">{{ ucfirst((string) $row['type']) }}</td>
                                    <td class="py-2 pr-3">{{ $row['parent_name'] ?: '—' }}</td>
                                    <td class="py-2 pr-3">
                                        <a href="{{ route('property.accounting.entries', ['q' => $row['name']]) }}" class="text-indigo-600 hover:text-indigo-700">{{ \App\Services\Property\PropertyMoney::kes((float) $row['balance']) }}</a>
                                    </td>
                                    <td class="py-2 pr-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($usage as $tag)
                                                <span class="rounded px-2 py-0.5 text-[11px] bg-slate-100 text-slate-700">{{ ucfirst((string) $tag) }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-2 pr-3">
                                        <span class="rounded px-2 py-0.5 text-[11px] {{ $row['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                            {{ $row['is_active'] ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td class="py-2 pr-3">
                                        <span class="rounded px-2 py-0.5 text-[11px] {{ $protection === 'Custom' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-700' }}">{{ $protection }}</span>
                                    </td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('property.accounting.entries', ['q' => $row['name']]) }}" class="text-indigo-600 hover:text-indigo-700">View ledger</a>
                                            <a href="{{ route('property.accounting.entries', ['q' => $row['code']]) }}" class="text-slate-700 hover:text-slate-900">Edit</a>
                                            <details class="relative">
                                                <summary class="list-none cursor-pointer text-slate-700 hover:text-slate-900">More</summary>
                                                <div class="absolute right-0 mt-1 z-20 w-44 rounded-lg border bg-white shadow p-2 space-y-1">
                                                    <a class="block text-xs px-2 py-1 hover:bg-slate-50 rounded" href="{{ route('property.accounting.entries', ['q' => $row['name']]) }}">View transactions</a>
                                                    <form method="post" action="{{ route('property.accounting.gl.chart_accounts.clone', ['account' => $account->id]) }}">
                                                        @csrf
                                                        <button class="w-full text-left text-xs px-2 py-1 hover:bg-slate-50 rounded" type="submit">Clone account</button>
                                                    </form>
                                                    <form method="post" action="{{ route('property.accounting.gl.chart_accounts.usage_default', ['account' => $account->id]) }}">
                                                        @csrf
                                                        <input type="hidden" name="usage" value="{{ $usage->first() ?: 'manual' }}">
                                                        <button class="w-full text-left text-xs px-2 py-1 hover:bg-slate-50 rounded" type="submit">Set as default mapping</button>
                                                    </form>
                                                    <button type="button"
                                                        class="w-full text-left text-xs px-2 py-1 rounded {{ $row['is_protected'] ? 'text-slate-400 cursor-not-allowed' : 'text-rose-700 hover:bg-rose-50' }}"
                                                        @if (! $row['is_protected'])
                                                            onclick="openDisableModal({{ $account->id }}, '{{ addslashes($row['code']) }}', '{{ addslashes($row['name']) }}', '{{ \App\Services\Property\PropertyMoney::kes((float) $row['balance']) }}', {{ (int) $row['tx_count'] }}, {{ $row['mapping_used'] ? 'true' : 'false' }}, {{ $row['is_protected'] ? 'true' : 'false' }})"
                                                        @endif
                                                    >Disable account</button>
                                                </div>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div id="coa-page-modals-root">
        @include('property.agent.partials.chart_accounts_modals', [
            'typeOptions' => $typeOptions ?? [],
            'parentOptions' => $parentOptions ?? [],
            'usageOptions' => $usageOptions ?? [],
        ])
    </div>
</x-property.workspace>

