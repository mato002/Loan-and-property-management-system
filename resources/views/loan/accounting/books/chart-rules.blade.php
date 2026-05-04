<x-loan-layout>
    <x-loan.page title="Chart of Accounts & Rules" subtitle="Define and manage the company account structure, hierarchies, and regulatory compliance rules.">
        @php
            $fmtN = fn (int|float $n) => number_format((float) $n, 0);
            $allAccounts = collect($accounts ?? []);
            $selectAccounts = $allAccounts
                ->where('is_active', true)
                ->sortBy('code')
                ->values();
            $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
            $typeLabels = [
                'asset' => 'Asset',
                'liability' => 'Liability',
                'equity' => 'Equity',
                'income' => 'Income',
                'expense' => 'Expense',
            ];
            $mappingRows = collect($eventMappings ?? [])->values();
            $isEditingAccount = isset($editingAccount) && $editingAccount;
            $isDuplicatingAccount = ! $isEditingAccount && isset($duplicateAccount) && $duplicateAccount;
            $modalAccount = $isEditingAccount ? $editingAccount : ($isDuplicatingAccount ? $duplicateAccount : null);
            $accountViewParam = request()->query('account_view') === 'table' ? 'table' : null;
            $withAccountView = static function (array $params = []) use ($accountViewParam): array {
                if ($accountViewParam === 'table') {
                    $params['account_view'] = 'table';
                }
                return array_filter($params, static fn ($v) => $v !== null && $v !== '');
            };
        @endphp
        <style>
            @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css');
        </style>
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books.chart_rules') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors">Refresh Chart Rules</a>
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books Hub</a>
        </x-slot>

        @include('loan.accounting.partials.flash')

        <div
            class="space-y-6"
            x-data="{
                showCreateAccountModal: {{ (($isEditingAccount || $isDuplicatingAccount) || $errors->hasAny(['name', 'account_type', 'income_statement_category', 'account_class', 'current_balance', 'min_balance_floor', 'overdraft_limit', 'controlled_approver_ids'])) ? 'true' : 'false' }},
                showImportModal: {{ $errors->has('import_file') ? 'true' : 'false' }},
                showEventMappingModal: {{ ($errors->has('debit_account_id') || $errors->has('credit_account_id')) ? 'true' : 'false' }},
                showMappingHistoryModal: false,
                showOverdrawnModal: false,
                showPendingApprovalsModal: false,
                selectedPendingAccount: null,
                selectedEventMapping: null,
                selectedMappingHistory: null,
                allowOverdraft: {{ old('allow_overdraft', (bool) ($modalAccount->allow_overdraft ?? false)) ? 'true' : 'false' }},
                isControlled: {{ old('is_controlled_account', (bool) ($modalAccount->is_controlled_account ?? false)) ? 'true' : 'false' }},
                floorEnabled: {{ old('floor_enabled', (bool) ($modalAccount->floor_enabled ?? false)) ? 'true' : 'false' }},
                generatedCode: '{{ old('generated_code', $modalAccount->code ?? '') }}',
                accountType: '{{ old('account_type', $modalAccount->account_type ?? 'asset') }}',
                accountClass: '{{ old('account_class', $modalAccount->account_class ?? 'Detail') }}',
                parentId: '{{ (string) old('parent_id', $modalAccount->parent_id ?? '') }}',
                activeTab: '{{ in_array(request()->string('tab')->toString(), ['overview', 'accounts', 'rules', 'wallet'], true) ? request()->string('tab')->toString() : 'overview' }}',
                accountSearch: '',
                accountTypeFilter: 'all',
                accountView: '{{ $accountViewParam === 'table' ? 'table' : 'hierarchy' }}',
                collapsedCategories: {},
                collapsedRows: {},
                toggleCategory(key) {
                    this.collapsedCategories[key] = !this.collapsedCategories[key];
                },
                toggleRow(id) {
                    this.collapsedRows[id] = !this.collapsedRows[id];
                },
                async refreshGeneratedCode() {
                    if ({{ $isEditingAccount ? 'true' : 'false' }}) return;
                    const params = new URLSearchParams({
                        account_type: this.accountType || 'asset',
                        account_class: this.accountClass || 'Detail',
                    });
                    if (this.parentId) params.append('parent_id', this.parentId);
                    try {
                        const response = await fetch(`{{ route('loan.accounting.chart.next_code') }}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) return;
                        const data = await response.json();
                        this.generatedCode = data.code || '';
                    } catch (e) { /* no-op */ }
                },
                openEventMappingModal(row) {
                    this.selectedEventMapping = row;
                    this.showEventMappingModal = true;
                },
                openHistoryModal(row) {
                    this.selectedMappingHistory = row;
                    this.showMappingHistoryModal = true;
                },
            }"
            x-init="refreshGeneratedCode()"
            @keydown.escape.window="showCreateAccountModal = false; showImportModal = false; showEventMappingModal = false; showMappingHistoryModal = false; showOverdrawnModal = false; showPendingApprovalsModal = false; selectedPendingAccount = null; selectedEventMapping = null; selectedMappingHistory = null"
        >
            <section class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Chart of Accounts</h1>
                        <p class="mt-1 text-sm text-slate-600">Manage your organization&rsquo;s chart of accounts and financial structure.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('loan.accounting.books.chart_rules') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Refresh</a>
                        <button type="button" @click="showCreateAccountModal = true" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors">New Account</button>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                    <button type="button" @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'bg-teal-700 text-white border-teal-700' : 'bg-white text-slate-700 border-slate-200'" class="rounded-lg border px-4 py-2 text-sm font-semibold">Overview</button>
                    <button type="button" @click="activeTab = 'accounts'" :class="activeTab === 'accounts' ? 'bg-teal-700 text-white border-teal-700' : 'bg-white text-slate-700 border-slate-200'" class="rounded-lg border px-4 py-2 text-sm font-semibold">Accounts</button>
                    <button type="button" @click="activeTab = 'rules'" :class="activeTab === 'rules' ? 'bg-teal-700 text-white border-teal-700' : 'bg-white text-slate-700 border-slate-200'" class="rounded-lg border px-4 py-2 text-sm font-semibold">Accounting Rules</button>
                    <button type="button" @click="activeTab = 'wallet'" :class="activeTab === 'wallet' ? 'bg-teal-700 text-white border-teal-700' : 'bg-white text-slate-700 border-slate-200'" class="rounded-lg border px-4 py-2 text-sm font-semibold">Wallet Accounts</button>
                </div>
            </section>

            <section x-show="activeTab === 'overview'" x-cloak class="space-y-4">
                @php
                    $totalAccounts = (int) $allAccounts->count();
                    $inactiveAccounts = (int) $allAccounts->where('is_active', false)->count();
                    $activeAccountsCount = max(0, $totalAccounts - $inactiveAccounts);
                    $overdrawnCount = (int) collect($overdrawnAccounts ?? [])->count();
                    $totalBalance = (float) $allAccounts->sum(fn ($row) => (float) ($row->current_balance ?? 0));
                    $typeStats = collect($typeOrder)->map(function ($typeKey) use ($allAccounts, $totalBalance) {
                        $rows = $allAccounts->where('account_type', $typeKey)->values();
                        $count = (int) $rows->count();
                        $active = (int) $rows->where('is_active', true)->count();
                        $balance = (float) $rows->sum(fn ($row) => (float) ($row->current_balance ?? 0));
                        $pct = $totalBalance > 0 ? round(($balance / $totalBalance) * 100, 1) : 0;

                        return [
                            'key' => $typeKey,
                            'count' => $count,
                            'active' => $active,
                            'balance' => $balance,
                            'pct' => $pct,
                        ];
                    })->values();
                    $assetBalance = (float) ($typeStats->firstWhere('key', 'asset')['balance'] ?? 0);
                    $liabilityBalance = (float) ($typeStats->firstWhere('key', 'liability')['balance'] ?? 0);
                    $equityBalance = (float) ($typeStats->firstWhere('key', 'equity')['balance'] ?? 0);
                    $incomeBalance = (float) ($typeStats->firstWhere('key', 'income')['balance'] ?? 0);
                    $expenseBalance = (float) ($typeStats->firstWhere('key', 'expense')['balance'] ?? 0);
                    $netIncome = $incomeBalance - $expenseBalance;
                @endphp

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between">
                            <p class="text-xs font-semibold text-slate-700">Audit Status</p>
                            <button type="button" @click="activeTab = 'rules'" class="text-xs font-semibold text-teal-700 hover:text-teal-800">View Issues</button>
                        </div>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $fmtN((int) ($pendingApprovals ?? 0)) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Pending account approvals</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $fmtN((int) ($missingRules ?? 0)) }} accounts missing rules</p>
                    </article>
                    <article class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold text-slate-700">Financial Pulse</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $fmtN((int) ($activeAccounts ?? 0)) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Active G/L Accounts</p>
                        <p class="mt-1 text-xs text-slate-500">New Accounts (30 Days): +{{ $fmtN((int) ($newAccounts30d ?? 0)) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Overdrawn Accounts: {{ $fmtN($overdrawnCount) }}</p>
                    </article>
                    <article class="rounded-xl border border-teal-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold text-slate-700">Balanced Books Meter</p>
                        <p class="mt-2 text-lg font-semibold {{ ($isBalanced ?? false) ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ ($isBalanced ?? false) ? 'Balanced' : 'Out of Balance' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Total debits balanced with total credits</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold text-slate-700">System Readiness</p>
                        <p class="mt-2 text-sm font-semibold text-emerald-700">Cash-Basis Mode: Active</p>
                        <p class="mt-1 text-sm font-semibold text-emerald-700">Liquidity Guardrails: Enforced</p>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Total Assets</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">KSh {{ number_format($assetBalance, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Current asset balance</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Total Liabilities</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">KSh {{ number_format($liabilityBalance, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Outstanding obligations</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Total Equity</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">KSh {{ number_format($equityBalance, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Owner&rsquo;s position</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Net Income</p>
                        <p class="mt-2 text-lg font-semibold {{ $netIncome >= 0 ? 'text-emerald-700' : 'text-red-700' }}">KSh {{ number_format($netIncome, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Revenue less expenses</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Total Balance</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">KSh {{ number_format($totalBalance, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Net organizational value</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Active Accounts</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $fmtN($activeAccountsCount) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $totalAccounts > 0 ? round(($activeAccountsCount / $totalAccounts) * 100) : 0 }}% of chart</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Inactive Accounts</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $fmtN($inactiveAccounts) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $totalAccounts > 0 ? round(($inactiveAccounts / $totalAccounts) * 100) : 0 }}% of chart</p>
                    </article>
                </div>

                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Account Type Breakdown</h3>
                            <p class="text-xs text-slate-500">Distribution by type and balance share.</p>
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ ($isBalanced ?? false) ? 'Balanced Books Meter: Balanced' : 'Balanced Books Meter: Out of Balance' }}
                        </div>
                    </div>
                    <div class="space-y-3">
                        @foreach ($typeStats as $stat)
                            @php
                                $barClass = match ($stat['key']) {
                                    'asset' => 'bg-emerald-600',
                                    'liability' => 'bg-pink-600',
                                    'equity' => 'bg-blue-500',
                                    'income' => 'bg-cyan-600',
                                    default => 'bg-orange-500',
                                };
                            @endphp
                            <button type="button" @click="activeTab = 'accounts'; accountTypeFilter = '{{ $stat['key'] }}'" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-left hover:bg-slate-50">
                                <div class="mb-2 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">{{ ucfirst($stat['key']) }}</span>
                                        <span class="text-slate-500">{{ $fmtN($stat['count']) }} accounts ({{ $fmtN($stat['active']) }} active)</span>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-slate-700">KSh {{ number_format((float) $stat['balance'], 2) }}</p>
                                        <p class="text-slate-500">{{ number_format((float) $stat['pct'], 1) }}% of total</p>
                                    </div>
                                </div>
                                <div class="h-2 w-full rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full {{ $barClass }}" style="width: {{ min(100, max(0, (float) $stat['pct'])) }}%"></div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </article>
            </section>

            <section x-show="activeTab === 'accounts'" x-cloak class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 md:grid-cols-12">
                        <div class="md:col-span-6">
                            <input x-model="accountSearch" type="text" placeholder="Search accounts by name or code..." class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-teal-700 focus:ring-teal-700" />
                        </div>
                        <div class="md:col-span-3">
                            <select x-model="accountTypeFilter" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                <option value="all">All Types</option>
                                @foreach ($typeOrder as $typeKey)
                                    <option value="{{ $typeKey }}">{{ $typeLabels[$typeKey] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-3 flex items-center justify-end gap-2">
                            <a href="{{ route('loan.accounting.books.chart_rules.template.download') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" title="Download import template">
                                <i class="fa-solid fa-download text-xs"></i>
                                <span>Download</span>
                            </a>
                            <button type="button" @click="showImportModal = true" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" title="Import chart accounts">
                                <i class="fa-solid fa-file-import text-xs"></i>
                                <span>Import</span>
                            </button>
                            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" title="Print current chart view">
                                <i class="fa-solid fa-print text-xs"></i>
                                <span>Print</span>
                            </button>
                            <button type="button" @click="window.location = '{{ request()->fullUrlWithoutQuery(['account_view']) }}'" :class="accountView === 'hierarchy' ? 'bg-teal-700 text-white border-teal-700' : 'bg-white text-slate-700 border-slate-200'" class="rounded-lg border px-3 py-2 text-sm font-semibold">Hierarchy</button>
                            <button type="button" @click="window.location = '{{ request()->fullUrlWithQuery(['account_view' => 'table']) }}'" :class="accountView === 'table' ? 'bg-teal-700 text-white border-teal-700' : 'bg-white text-slate-700 border-slate-200'" class="rounded-lg border px-3 py-2 text-sm font-semibold">Table View</button>
                        </div>
                    </div>
                </div>

                <div x-show="accountView === 'hierarchy'" x-cloak class="space-y-4">
                    <h2 class="text-lg font-semibold text-slate-900">Account Hierarchy</h2>
                    <p class="-mt-2 text-xs text-slate-500">Navigate and explore accounts in hierarchical tree structure</p>
                    @foreach ($typeOrder as $typeKey)
                        @php
                            $rows = $allAccounts
                                ->where('account_type', $typeKey)
                                ->sortBy(fn ($row) => (int) ($row->code ?? 0))
                                ->values();
                            $rowsByParent = $rows->groupBy(fn ($row) => (string) ($row->parent_id ?? 'root'));
                            $flattenRows = function (string $parentKey = 'root', int $level = 0, array $ancestors = []) use (&$flattenRows, $rowsByParent): array {
                                $branch = collect($rowsByParent->get($parentKey, []))
                                    ->sortBy(fn ($row) => (int) ($row->code ?? 0))
                                    ->values();
                                $result = [];
                                foreach ($branch as $node) {
                                    $id = (int) $node->id;
                                    $children = collect($rowsByParent->get((string) $id, []));
                                    $hasChildren = $children->isNotEmpty();
                                    $result[] = [
                                        'account' => $node,
                                        'level' => $level,
                                        'ancestors' => $ancestors,
                                        'hasChildren' => $hasChildren,
                                        'isHeaderLike' => in_array((string) ($node->account_class ?? ''), ['Header', 'Parent'], true),
                                    ];
                                    if ($hasChildren) {
                                        $result = array_merge(
                                            $result,
                                            $flattenRows((string) $id, $level + 1, array_merge($ancestors, [$id]))
                                        );
                                    }
                                }

                                return $result;
                            };
                            $flatRows = collect($flattenRows())->values();
                            $rootCount = $flatRows->where('level', 0)->count();
                            $typeBalance = (float) $rows->sum(fn ($row) => (float) ($row->current_balance ?? 0));
                        @endphp
                        <article
                            x-show="accountTypeFilter === 'all' || accountTypeFilter === '{{ $typeKey }}'"
                            x-cloak
                            class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
                        >
                            <button
                                type="button"
                                @click="toggleCategory('{{ $typeKey }}')"
                                class="flex w-full items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3 text-left"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-slate-500 text-xs" x-text="collapsedCategories['{{ $typeKey }}'] ? '>' : '⌄'"></span>
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $typeLabels[$typeKey] }}</h3>
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $fmtN($rootCount) }} root accounts · Balance KSh {{ $fmtN($typeBalance) }}
                                </div>
                            </button>
                            <div x-show="!collapsedCategories['{{ $typeKey }}']" class="overflow-x-auto px-5 py-3">
                                <div class="mb-2 grid w-full min-w-[760px] grid-cols-[6rem_minmax(0,1fr)_9rem] border-b border-slate-100 pb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    <div class="w-24">Account Code</div>
                                    <div>Account Name</div>
                                    <div class="text-right">Balance</div>
                                </div>
                                @forelse ($flatRows as $node)
                                    @php
                                        $row = $node['account'];
                                        $levelClass = match ((int) $node['level']) {
                                            0 => 'pl-2',
                                            1 => 'pl-6',
                                            2 => 'pl-10',
                                            default => 'pl-14',
                                        };
                                        $isHeaderRow = $node['isHeaderLike'];
                                    @endphp
                                    <div
                                        x-show="
                                            {{ \Illuminate\Support\Js::from(strtolower((string) $row->code.' '.(string) $row->name)) }}.includes(accountSearch.toLowerCase())
                                            && {{ \Illuminate\Support\Js::from($node['ancestors']) }}.every(id => !collapsedRows[id])
                                        "
                                        class="grid w-full min-w-[760px] grid-cols-[6rem_minmax(0,1fr)_9rem] items-center gap-2 border-b border-slate-100 py-2 text-sm last:border-b-0 {{ $isHeaderRow ? 'bg-slate-50/70' : 'bg-white' }}"
                                    >
                                        <div class="w-24 min-w-0">
                                            <p class="font-mono text-[11px] text-slate-500">{{ $row->code }}</p>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 {{ $levelClass }}">
                                                @if ($node['hasChildren'])
                                                    <button type="button" @click="toggleRow({{ (int) $row->id }})" class="text-xs text-slate-500 hover:text-slate-700">
                                                        <span x-text="collapsedRows[{{ (int) $row->id }}] ? '>' : '⌄'"></span>
                                                    </button>
                                                @else
                                                    <span class="inline-block w-3 text-xs text-slate-300">•</span>
                                                @endif
                                                <p class="max-w-[300px] overflow-hidden text-ellipsis whitespace-nowrap {{ $isHeaderRow ? 'font-semibold text-slate-900' : 'font-medium text-slate-700' }}">
                                                    {{ strtoupper((string) $row->name) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-end gap-2 text-right text-xs">
                                            <span class="text-slate-600">KSh {{ $fmtN((float) ($row->current_balance ?? 0)) }}</span>
                                            <span class="inline-block h-1.5 w-1.5 rounded-full {{ (bool) $row->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}" title="{{ (bool) $row->is_active ? 'Active' : 'Inactive' }}"></span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No {{ strtolower($typeLabels[$typeKey]) }} accounts available.</p>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>

                <div x-show="accountView === 'table'" x-cloak class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Account Code</th>
                                <th class="px-3 py-2">Account Name</th>
                                <th class="px-3 py-2">Account Type</th>
                                <th class="px-3 py-2">Parent Group</th>
                                <th class="px-3 py-2">Current Balance</th>
                                <th class="px-3 py-2">Overdraft Policy</th>
                                <th class="px-3 py-2">Active State</th>
                                <th class="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($allAccounts as $row)
                                <tr
                                    x-show="(accountTypeFilter === 'all' || accountTypeFilter === {{ \Illuminate\Support\Js::from((string) $row->account_type) }}) && {{ \Illuminate\Support\Js::from(strtolower((string) $row->code.' '.(string) $row->name)) }}.includes(accountSearch.toLowerCase())"
                                    class="hover:bg-teal-50/60"
                                >
                                    <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $row->code }}</td>
                                    <td class="px-3 py-2 font-medium text-slate-800">{{ $row->name }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ ucfirst($row->account_type) }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $row->parent?->name ?? 'Top Level' }}</td>
                                    <td class="px-3 py-2 font-semibold text-emerald-700">KSh {{ $fmtN((float) ($row->current_balance ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-slate-600">
                                        @if ($row->allow_overdraft)
                                            Allowed @if(!is_null($row->overdraft_limit)) (Limit: KSh {{ $fmtN((float) $row->overdraft_limit) }}) @endif
                                        @else
                                            Strict Mode
                                        @endif
                                    </td>
                                    <td class="px-3 py-2"><input type="checkbox" @checked($row->is_active) disabled class="h-4 w-8 rounded-full border border-slate-300 text-emerald-600"></td>
                                    <td class="px-3 py-2 text-slate-500">
                                        <div class="flex items-center gap-1">
                                            <a href="{{ route('loan.accounting.books.chart_rules', $withAccountView(['tab' => 'accounts', 'edit_account' => $row->id])) }}" class="rounded p-1 hover:bg-blue-50 hover:text-blue-700" title="Edit"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></a>
                                            <a href="{{ route('loan.accounting.books.chart_rules', $withAccountView(['tab' => 'accounts', 'duplicate_account' => $row->id])) }}" class="rounded p-1 hover:bg-blue-50 hover:text-blue-700" title="Duplicate"><i class="fa-solid fa-clone" aria-hidden="true"></i></a>
                                            <form method="post" action="{{ route('loan.accounting.chart.destroy', $row->id) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_to" value="{{ route('loan.accounting.books.chart_rules', $withAccountView(['tab' => 'accounts'])) }}">
                                                <button
                                                    type="submit"
                                                    data-swal-confirm="Delete this account? If it has journal history, deletion will be blocked."
                                                    class="rounded p-1 hover:bg-red-50 hover:text-red-700"
                                                    title="Delete Account"
                                                >
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('loan.accounting.ledger', ['account_id' => $row->id, 'recent' => 1, 'limit' => 50, 'tab' => 'accounts']) }}" class="rounded p-1 hover:bg-purple-50 hover:text-purple-700" title="Account History (General Ledger)"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-3 py-4 text-center text-slate-500">No accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section x-show="activeTab === 'rules'" x-cloak class="space-y-6">
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Global Cash-Basis Settings</h2>
                        <span class="rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">Editable</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            <span>System Accounting Mode: Cash-Basis Only</span>
                            <input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-blue-600 focus:ring-blue-500">
                        </label>
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            <span>Enforce Liquidity Guardrails</span>
                            <input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-blue-600 focus:ring-blue-500">
                        </label>
                    </div>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Automated Cash Mappings</h2>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Governance Layer</span>
                        </div>
                    </div>
                    <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">Client wallet GL behaviour</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>Wallet-to-loan repayments use the existing allocation events (no extra trigger): <span class="font-mono text-xs">PrincipalAllocated</span>, <span class="font-mono text-xs">InterestReceived</span>, <span class="font-mono text-xs">FeeReceived</span>, <span class="font-mono text-xs">PenaltyReceived</span>.</li>
                            <li>Manual wallet corrections post under <span class="font-mono text-xs">WalletAdjustment</span> once the offset account slot is mapped below.</li>
                        </ul>
                    </div>
                    <div class="max-h-[75vh] overflow-auto rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-2">Trigger (Business Event)</th>
                                    <th class="px-3 py-2">Debit Account (COA)</th>
                                    <th class="px-3 py-2">Credit Account (COA)</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($mappingRows as $rule)
                                    @php
                                        $ruleState = (string) ($rule['status'] ?? 'Needs Setup');
                                        $ruleClass = match ($ruleState) {
                                            'Active' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'Awaiting Approval' => 'border-orange-200 bg-orange-50 text-orange-700',
                                            'Disabled' => 'border-slate-200 bg-slate-100 text-slate-600',
                                            default => 'border-amber-200 bg-amber-50 text-amber-700',
                                        };
                                    @endphp
                                    <tr class="hover:bg-teal-50/60">
                                        <td class="px-3 py-2 text-slate-800">
                                            <p class="font-medium">{{ $rule['event_name'] }}</p>
                                            <p class="text-xs text-slate-500">{{ $rule['description'] }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">
                                            @if ($rule['debit_account'])
                                                <span>{{ $rule['debit_account']->code }} - {{ $rule['debit_account']->name }}</span>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">
                                            @if ($rule['credit_account'])
                                                <span>{{ $rule['credit_account']->code }} - {{ $rule['credit_account']->name }}</span>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $ruleClass }}">{{ $ruleState }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-500">
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    @click="openEventMappingModal({{ \Illuminate\Support\Js::from($rule) }})"
                                                    class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                                                >
                                                    Edit Mapping
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="openHistoryModal({{ \Illuminate\Support\Js::from($rule) }})"
                                                    class="inline-flex items-center justify-center rounded-md border border-purple-200 bg-purple-50 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-100"
                                                >
                                                    View History / Audit
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">No mapping rules found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Approval Settings</h3>
                        <p class="mt-2 text-sm text-slate-600">Route pending chart accounts and controlled journal approvals.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="showPendingApprovalsModal = true" class="rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700 hover:bg-orange-100">View Pending Account Approvals</button>
                            <a href="{{ route('loan.accounting.journal.approval_queue') }}" class="rounded-lg border border-purple-300 bg-purple-50 px-3 py-2 text-xs font-semibold text-purple-700 hover:bg-purple-100">View Journal Approval Queue</a>
                        </div>
                    </div>
                    <div class="rounded-xl border border-red-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Liquidity Guardrails</h3>
                        <p class="mt-2 text-sm text-slate-600">Monitor floor and overdraft controls for sensitive accounts.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="showOverdrawnModal = true" class="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">View Overdrawn Accounts</button>
                        </div>
                    </div>
                </article>
            </section>

            <section x-show="activeTab === 'wallet'" x-cloak class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Wallet accounts</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Client wallet <strong>general ledger</strong> mappings are maintained under <strong>Accounting Rules</strong> in <strong>Automated Cash Mappings</strong> (same event registry as loan pay-ins). Map <span class="font-mono text-xs">client_wallet_liability_account</span>, <span class="font-mono text-xs">collection_cash_account</span>, allocation events, <span class="font-mono text-xs">LoanOverpayment</span>, <span class="font-mono text-xs">RefundIssued</span>, and <span class="font-mono text-xs">WalletAdjustment</span> there.
                </p>
                <p class="mt-3 text-sm text-slate-600">
                    This tab is reserved for future wallet-specific tooling (e.g. quick links or slot filters). Use <strong>Accounting Rules → Automated Cash Mappings</strong> to review and approve account links today.
                </p>
            </section>

            <div
                x-cloak
                x-show="showImportModal"
                x-transition.opacity
                class="fixed inset-0 z-50 bg-black/50"
                @click.self="showImportModal = false"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Import Chart of Accounts</h3>
                                <p class="text-sm text-slate-500">Upload a CSV created from the template download.</p>
                            </div>
                            <button type="button" @click="showImportModal = false" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Close modal">x</button>
                        </div>
                        <form method="post" action="{{ route('loan.accounting.books.chart_rules.import') }}" enctype="multipart/form-data" class="space-y-4 px-6 py-5">
                            @csrf
                            <div>
                                <label for="import_file" class="mb-1 block text-xs font-semibold text-slate-600">CSV File</label>
                                <input id="import_file" type="file" name="import_file" accept=".csv,.txt" required class="w-full rounded-lg border-slate-200 text-sm" />
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <a href="{{ route('loan.accounting.books.chart_rules.template.download') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <i class="fa-solid fa-download text-[11px]"></i>
                                        <span>Download Template</span>
                                    </a>
                                    <span class="text-xs text-slate-500">Required columns: name, account_type, account_class, parent_code (optional).</span>
                                </div>
                                <ul class="mt-3 space-y-1 text-xs text-slate-600">
                                    <li>- Do not include <span class="font-semibold">code</span> (system auto-generates it).</li>
                                    <li>- If you use <span class="font-semibold">parent_code</span>, that parent must already exist (or appear earlier in a prior import).</li>
                                    <li>- <span class="font-semibold">parent_code</span> must point to a Header or Parent account.</li>
                                    <li>- Parent-child account type is enforced by system behavior.</li>
                                </ul>
                                @error('import_file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                                <button type="button" @click="showImportModal = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Import</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div
                x-cloak
                x-show="showCreateAccountModal"
                x-transition.opacity
                class="fixed inset-0 z-50 bg-black/50"
                @click.self="showCreateAccountModal = false"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div
                        x-show="showCreateAccountModal"
                        x-transition
                        class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    >
                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $isEditingAccount ? 'Edit Account' : ($isDuplicatingAccount ? 'Duplicate Account' : 'Create New Account') }}</h3>
                                <p class="text-sm text-slate-500">{{ $isEditingAccount ? 'Update account details in this modal window.' : ($isDuplicatingAccount ? 'Create a new account from an existing one.' : 'Add a new chart of account without leaving this page.') }}</p>
                            </div>
                            <button type="button" @click="showCreateAccountModal = false" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Close modal">x</button>
                        </div>

                        <form method="post" action="{{ $isEditingAccount ? route('loan.accounting.chart.update', $editingAccount) : route('loan.accounting.chart.store') }}" class="space-y-5 px-6 py-5">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ route('loan.accounting.books.chart_rules', $withAccountView(request()->only(['tab']))) }}" />
                            @if($isEditingAccount)
                                @method('patch')
                            @endif

                            <div class="grid gap-4 md:grid-cols-4">
                                <div>
                                    <label for="modal_generated_code" class="mb-1 block text-xs font-semibold text-slate-600">Generated Account Code</label>
                                    <input id="modal_generated_code" name="generated_code" x-model="generatedCode" readonly class="w-full rounded-lg border-slate-200 bg-slate-50 text-sm font-mono" />
                                    <p class="mt-1 text-[11px] text-slate-500">Code is generated automatically based on account type, class, and parent sequence.</p>
                                </div>
                                <div>
                                    <label for="modal_name" class="mb-1 block text-xs font-semibold text-slate-600">Name</label>
                                    <input id="modal_name" name="name" value="{{ old('name', $isDuplicatingAccount ? (($modalAccount->name ?? '').' Copy') : ($modalAccount->name ?? '')) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="modal_account_type" class="mb-1 block text-xs font-semibold text-slate-600">Type</label>
                                    <select id="modal_account_type" name="account_type" x-model="accountType" @change="refreshGeneratedCode()" required class="w-full rounded-lg border-slate-200 text-sm">
                                        @foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $t)
                                            <option value="{{ $t }}" @selected(old('account_type', $modalAccount->account_type ?? 'asset') === $t)>{{ ucfirst($t) }}</option>
                                        @endforeach
                                    </select>
                                    @error('account_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="modal_income_statement_category" class="mb-1 block text-xs font-semibold text-slate-600">Income Statement Category</label>
                                    <select id="modal_income_statement_category" name="income_statement_category" class="w-full rounded-lg border-slate-200 text-sm">
                                        <option value="">Not applicable</option>
                                        <option value="revenue" @selected(old('income_statement_category', $modalAccount->income_statement_category ?? '') === 'revenue')>Revenue</option>
                                        <option value="direct_cost" @selected(old('income_statement_category', $modalAccount->income_statement_category ?? '') === 'direct_cost')>Direct Cost</option>
                                        <option value="operating_expense" @selected(old('income_statement_category', $modalAccount->income_statement_category ?? '') === 'operating_expense')>Operating Expense</option>
                                        <option value="tax_expense" @selected(old('income_statement_category', $modalAccount->income_statement_category ?? '') === 'tax_expense')>Tax Expense</option>
                                        <option value="other_income" @selected(old('income_statement_category', $modalAccount->income_statement_category ?? '') === 'other_income')>Other Income</option>
                                        <option value="other_expense" @selected(old('income_statement_category', $modalAccount->income_statement_category ?? '') === 'other_expense')>Other Expense</option>
                                    </select>
                                    <p class="mt-1 text-[11px] text-slate-500">Required for income and expense accounts.</p>
                                    @error('income_statement_category')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label for="modal_account_class" class="mb-1 block text-xs font-semibold text-slate-600">Account Class</label>
                                    <select id="modal_account_class" name="account_class" x-model="accountClass" @change="refreshGeneratedCode()" class="w-full rounded-lg border-slate-200 text-sm">
                                        @foreach (['Header', 'Parent', 'Detail'] as $class)
                                            <option value="{{ $class }}" @selected(old('account_class', $modalAccount->account_class ?? 'Detail') === $class)>{{ $class }}</option>
                                        @endforeach
                                    </select>
                                    @error('account_class')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="modal_parent_id" class="mb-1 block text-xs font-semibold text-slate-600">Parent Account (Header)</label>
                                    <select id="modal_parent_id" name="parent_id" x-model="parentId" @change="refreshGeneratedCode()" class="w-full rounded-lg border-slate-200 text-sm">
                                        <option value="">Top-level account</option>
                                        @foreach (($headerAccounts ?? collect()) as $header)
                                            @continue($isEditingAccount && (int) $modalAccount->id === (int) $header->id)
                                            <option value="{{ $header->id }}" @selected((string) old('parent_id', $modalAccount->parent_id ?? '') === (string) $header->id)>{{ $header->code }} - {{ $header->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('parent_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="modal_current_balance" class="mb-1 block text-xs font-semibold text-slate-600">Current Balance</label>
                                    <input id="modal_current_balance" type="number" step="0.01" name="current_balance" value="{{ old('current_balance', $modalAccount->current_balance ?? 0) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                    @error('current_balance')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                        <input type="hidden" name="floor_enabled" value="0" />
                                        <input type="checkbox" name="floor_enabled" value="1" x-model="floorEnabled" class="rounded border-slate-300" @checked(old('floor_enabled', $modalAccount->floor_enabled ?? false)) />
                                        Enable Minimum Balance Floor
                                    </label>
                                    <div x-show="floorEnabled" x-cloak class="grid gap-2">
                                        <input id="modal_min_balance_floor" type="number" step="0.01" min="0" name="min_balance_floor" value="{{ old('min_balance_floor', $modalAccount->min_balance_floor ?? 0) }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Minimum balance amount" />
                                        <select name="floor_action" class="w-full rounded-lg border-slate-200 text-sm">
                                            <option value="block" @selected(old('floor_action', $modalAccount->floor_action ?? 'block') === 'block')>Block posting below floor</option>
                                            <option value="require_approval" @selected(old('floor_action', $modalAccount->floor_action ?? '') === 'require_approval')>Require approval below floor</option>
                                        </select>
                                        <p class="text-[11px] text-orange-700">Below floor transactions require approval or are blocked.</p>
                                    </div>
                                    @error('min_balance_floor')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <label class="flex items-center justify-between text-sm text-slate-700">
                                        <span>Allow Overdraft</span>
                                        <span>
                                            <input type="hidden" name="allow_overdraft" value="0" />
                                            <input x-model="allowOverdraft" type="checkbox" name="allow_overdraft" value="1" class="rounded border-slate-300" @checked(old('allow_overdraft', $modalAccount->allow_overdraft ?? false)) />
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div x-show="allowOverdraft" x-cloak>
                                    <label for="modal_overdraft_limit" class="mb-1 block text-xs font-semibold text-slate-600">Overdraft Limit</label>
                                    <input id="modal_overdraft_limit" type="number" step="0.01" min="0" name="overdraft_limit" value="{{ old('overdraft_limit', $modalAccount->overdraft_limit ?? '') }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Leave blank for unlimited" />
                                    @error('overdraft_limit')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <input type="hidden" name="is_cash_account" value="0" />
                                    <input type="checkbox" name="is_cash_account" value="1" class="rounded border-slate-300" @checked(old('is_cash_account', $modalAccount->is_cash_account ?? false)) />
                                    Cash / bank account
                                </label>
                                @if (!($coaApprovalEnabled ?? false))
                                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                        <input type="hidden" name="is_active" value="0" />
                                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $modalAccount->is_active ?? true)) />
                                        Active
                                    </label>
                                @else
                                    <div class="flex items-center rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700">
                                        New accounts will be saved as pending until approvers complete the workflow.
                                    </div>
                                @endif
                                <div class="flex items-center md:justify-end">
                                    <span x-show="isControlled" x-cloak class="inline-flex rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Controlled Account</span>
                                </div>
                            </div>

                            <section class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="hidden" name="is_controlled_account" value="0" />
                                    <input type="checkbox" name="is_controlled_account" value="1" x-model="isControlled" class="rounded border-slate-300" @checked(old('is_controlled_account', $modalAccount->is_controlled_account ?? false)) />
                                    Controlled Account
                                </label>
                                <div x-show="isControlled" x-cloak class="space-y-3 rounded-lg border border-purple-200 bg-white p-3">
                                    <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                        <span>Requires Approval</span>
                                        <span>
                                            <input type="hidden" name="control_requires_approval" value="0" />
                                            <input type="checkbox" name="control_requires_approval" value="1" class="rounded border-slate-300" @checked(old('control_requires_approval', $modalAccount->control_requires_approval ?? true)) />
                                        </span>
                                    </label>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <select name="control_approval_type" class="w-full rounded-lg border-slate-200 text-sm">
                                            <option value="any" @selected(old('control_approval_type', $modalAccount->control_approval_type ?? 'any') === 'any')>Any selected approver can approve</option>
                                            <option value="all" @selected(old('control_approval_type', $modalAccount->control_approval_type ?? '') === 'all')>All selected approvers must approve</option>
                                            <option value="role" @selected(old('control_approval_type', $modalAccount->control_approval_type ?? '') === 'role')>Specific role approval required</option>
                                        </select>
                                        <input type="text" name="control_approval_role" value="{{ old('control_approval_role', $modalAccount->control_approval_role ?? '') }}" placeholder="Role (if role-based)" class="w-full rounded-lg border-slate-200 text-sm" />
                                    </div>
                                    <div>
                                        <p class="mb-1 text-xs font-semibold text-slate-600">Approvers</p>
                                        @php
                                            $selectedApprovers = old('controlled_approver_ids', isset($modalAccount) ? $modalAccount->controlledApprovers()->pluck('users.id')->all() : []);
                                            $selectedApprovers = collect($selectedApprovers)->map(fn ($v) => (int) $v)->all();
                                        @endphp
                                        <select multiple name="controlled_approver_ids[]" class="w-full rounded-lg border-slate-200 text-sm">
                                            @foreach (($availableApprovers ?? collect()) as $approver)
                                                <option value="{{ $approver->id }}" @selected(in_array((int) $approver->id, $selectedApprovers, true))>
                                                    {{ $approver->name }}{{ $approver->email ? ' ('.$approver->email.')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                            <input type="hidden" name="control_threshold_enabled" value="0" />
                                            <input type="checkbox" name="control_threshold_enabled" value="1" class="rounded border-slate-300" @checked(old('control_threshold_enabled', $modalAccount->control_threshold_enabled ?? false)) />
                                            Require approval only above threshold
                                        </label>
                                        <input type="number" step="0.01" min="0" name="control_threshold_amount" value="{{ old('control_threshold_amount', $modalAccount->control_threshold_amount ?? '') }}" placeholder="Threshold amount" class="w-full rounded-lg border-slate-200 text-sm" />
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                            <input type="hidden" name="control_always_require_approval" value="0" />
                                            <input type="checkbox" name="control_always_require_approval" value="1" class="rounded border-slate-300" @checked(old('control_always_require_approval', $modalAccount->control_always_require_approval ?? false)) />
                                            Always Require Approval
                                        </label>
                                        <select name="control_applies_to" class="w-full rounded-lg border-slate-200 text-sm">
                                            <option value="both" @selected(old('control_applies_to', $modalAccount->control_applies_to ?? 'both') === 'both')>Applies To: Both</option>
                                            <option value="debit" @selected(old('control_applies_to', $modalAccount->control_applies_to ?? '') === 'debit')>Applies To: Debit entries</option>
                                            <option value="credit" @selected(old('control_applies_to', $modalAccount->control_applies_to ?? '') === 'credit')>Applies To: Credit entries</option>
                                        </select>
                                    </div>
                                    <input type="text" name="control_reason_note" value="{{ old('control_reason_note', $modalAccount->control_reason_note ?? '') }}" maxlength="500" placeholder="Reason / Governance Note" class="w-full rounded-lg border-slate-200 text-sm" />
                                    <p class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-700">Journal entries involving this account may require approval before posting.</p>
                                </div>
                            </section>

                            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                                <button type="button" @click="showCreateAccountModal = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $isEditingAccount ? 'Update Account' : ($isDuplicatingAccount ? 'Create Copy' : 'Save Account') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div
                x-cloak
                x-show="showPendingApprovalsModal"
                x-transition.opacity
                class="fixed inset-0 z-50 bg-black/50"
                @click.self="showPendingApprovalsModal = false; selectedPendingAccount = null"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="w-full max-w-7xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Pending Account Approvals</h3>
                                <p class="text-sm text-slate-500">Review pending chart accounts and take approval actions where permitted.</p>
                            </div>
                            <button type="button" @click="showPendingApprovalsModal = false; selectedPendingAccount = null" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Close modal">x</button>
                        </div>
                        <div class="max-h-[65vh] overflow-y-auto p-6 space-y-4">
                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-[1280px] w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        <tr>
                                            <th class="px-3 py-2">Account Name</th>
                                            <th class="px-3 py-2">Account Code</th>
                                            <th class="px-3 py-2">Account Type</th>
                                            <th class="px-3 py-2">Parent Group</th>
                                            <th class="px-3 py-2">Opening Balance</th>
                                            <th class="px-3 py-2">Minimum Balance Floor</th>
                                            <th class="px-3 py-2">Created By</th>
                                            <th class="px-3 py-2">Created At</th>
                                            <th class="px-3 py-2">Status</th>
                                            <th class="px-3 py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse (($pendingAccounts ?? collect()) as $pending)
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-3 py-2 font-medium text-slate-900">{{ $pending['name'] }}</td>
                                                <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $pending['code'] }}</td>
                                                <td class="px-3 py-2">{{ $pending['account_type'] }}</td>
                                                <td class="px-3 py-2">{{ $pending['parent_group'] }}</td>
                                                <td class="px-3 py-2">KSh {{ $fmtN((float) $pending['opening_balance']) }}</td>
                                                <td class="px-3 py-2">KSh {{ $fmtN((float) $pending['min_balance_floor']) }}</td>
                                                <td class="px-3 py-2">{{ $pending['created_by'] }}</td>
                                                <td class="px-3 py-2">{{ $pending['created_at'] }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="inline-flex rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700">{{ $pending['status'] }}</span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-2">
                                                        @if ($pending['can_approve'])
                                                            <form method="post" action="{{ route('loan.accounting.books.chart_rules.approve', $pending['id']) }}">
                                                                @csrf
                                                                <button type="submit" class="rounded-lg border border-green-300 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 hover:bg-green-100">Approve / Activate</button>
                                                            </form>
                                                            <form method="post" action="{{ route('loan.accounting.books.chart_rules.reject', $pending['id']) }}" class="flex items-center gap-2">
                                                                @csrf
                                                                <input type="text" name="rejection_reason" required maxlength="500" placeholder="Reject reason" class="w-36 rounded-lg border border-red-200 px-2 py-1 text-xs" />
                                                                <button type="submit" class="rounded-lg border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Reject</button>
                                                            </form>
                                                        @else
                                                            <span class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-600">Read-only (Assigned: {{ $pending['assigned_approver_name'] ?? 'N/A' }})</span>
                                                        @endif
                                                        <button type="button" @click='selectedPendingAccount = @json($pending)' class="rounded-lg border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100">View Details</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="px-3 py-4 text-center text-slate-500">No pending account approvals.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div x-show="selectedPendingAccount" x-cloak class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-slate-900">Account Details</h4>
                                    <button type="button" @click="selectedPendingAccount = null" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700">Close</button>
                                </div>
                                <dl class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-700 md:grid-cols-2">
                                    <div><dt class="font-semibold text-slate-500">Account</dt><dd x-text="selectedPendingAccount?.name"></dd></div>
                                    <div><dt class="font-semibold text-slate-500">Code</dt><dd x-text="selectedPendingAccount?.code"></dd></div>
                                    <div><dt class="font-semibold text-slate-500">Type</dt><dd x-text="selectedPendingAccount?.account_type"></dd></div>
                                    <div><dt class="font-semibold text-slate-500">Parent Group</dt><dd x-text="selectedPendingAccount?.parent_group"></dd></div>
                                    <div><dt class="font-semibold text-slate-500">Created By</dt><dd x-text="selectedPendingAccount?.created_by"></dd></div>
                                    <div><dt class="font-semibold text-slate-500">Created At</dt><dd x-text="selectedPendingAccount?.created_at"></dd></div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                x-cloak
                x-show="showOverdrawnModal"
                x-transition.opacity
                class="fixed inset-0 z-50 bg-black/50"
                @click.self="showOverdrawnModal = false"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Overdrawn Accounts</h3>
                                <p class="text-sm text-slate-500">Accounts where current balance is negative and overdraft is enabled.</p>
                            </div>
                            <button type="button" @click="showOverdrawnModal = false" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Close modal">x</button>
                        </div>
                        <div class="max-h-[65vh] overflow-y-auto p-6">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Code</th>
                                        <th class="px-3 py-2">Name</th>
                                        <th class="px-3 py-2">Current Balance</th>
                                        <th class="px-3 py-2">Overdraft Limit</th>
                                        <th class="px-3 py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse (($overdrawnAccounts ?? collect()) as $a)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-xs">{{ $a->code }}</td>
                                            <td class="px-3 py-2">{{ $a->name }}</td>
                                            <td class="px-3 py-2 font-semibold text-red-700">KSh {{ $fmtN((float) $a->current_balance) }}</td>
                                            <td class="px-3 py-2">{{ is_null($a->overdraft_limit) ? 'Unlimited' : 'KSh '.number_format((float) $a->overdraft_limit, 0) }}</td>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('loan.accounting.books.chart_rules', $withAccountView(['tab' => 'accounts', 'edit_account' => $a->id])) }}" class="rounded p-1 text-slate-500 hover:bg-blue-50 hover:text-blue-700" title="Edit"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></a>
                                                <a href="{{ route('loan.accounting.journal.index', ['q' => $a->code]) }}" class="rounded p-1 text-slate-500 hover:bg-purple-50 hover:text-purple-700" title="View Journal"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-3 py-4 text-center text-emerald-700">No overdrawn accounts. Treasury is stable.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div
                x-cloak
                x-show="showEventMappingModal && selectedEventMapping"
                x-transition.opacity
                class="fixed inset-0 z-50 bg-black/50"
                @click.self="showEventMappingModal = false; selectedEventMapping = null"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Edit Mapping</h3>
                                <p class="text-sm text-slate-500">Business event is fixed by the system; only mapping is editable.</p>
                            </div>
                            <button type="button" @click="showEventMappingModal = false; selectedEventMapping = null" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Close modal">x</button>
                        </div>
                        <form method="post" :action="'{{ route('loan.accounting.chart.event_mappings.update', ['eventKey' => '__EVENT_KEY__']) }}'.replace('__EVENT_KEY__', selectedEventMapping.event_key)" class="space-y-4 px-6 py-5">
                            @csrf
                            @method('PATCH')
                            <div class="grid gap-3 sm:grid-cols-1">
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <p class="text-sm font-semibold text-slate-800" x-text="selectedEventMapping?.event_name"></p>
                                    <p class="mt-1 text-xs text-slate-500" x-text="selectedEventMapping?.description"></p>
                                </div>
                                <label class="text-xs font-semibold text-slate-600">
                                    Debit Slot
                                    <input type="text" readonly :value="selectedEventMapping?.debit_slot || ''" class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm" />
                                </label>
                                <label class="text-xs font-semibold text-slate-600">
                                    Debit Account (COA)
                                    <select name="debit_account_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm" required>
                                        <option value="">Select debit account</option>
                                        @foreach ($selectAccounts as $acc)
                                            <option value="{{ $acc->id }}" :selected="String(selectedEventMapping?.debit_account?.id || '') === '{{ $acc->id }}'">{{ $acc->code }} - {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('debit_account_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                                <label class="text-xs font-semibold text-slate-600">
                                    Credit Slot
                                    <input type="text" readonly :value="selectedEventMapping?.credit_slot || ''" class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm" />
                                </label>
                                <label class="text-xs font-semibold text-slate-600">
                                    Credit Account (COA)
                                    <select name="credit_account_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm" required>
                                        <option value="">Select credit account</option>
                                        @foreach ($selectAccounts as $acc)
                                            <option value="{{ $acc->id }}" :selected="String(selectedEventMapping?.credit_account?.id || '') === '{{ $acc->id }}'">{{ $acc->code }} - {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('credit_account_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                            </div>
                            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                                <button type="button" @click="showEventMappingModal = false; selectedEventMapping = null" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Save Mapping</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div
                x-cloak
                x-show="showMappingHistoryModal && selectedMappingHistory"
                x-transition.opacity
                class="fixed inset-0 z-50 bg-black/50"
                @click.self="showMappingHistoryModal = false; selectedMappingHistory = null"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Mapping History / Audit</h3>
                                <p class="text-sm text-slate-500" x-text="selectedMappingHistory?.event_name"></p>
                            </div>
                            <button type="button" @click="showMappingHistoryModal = false; selectedMappingHistory = null" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Close modal">x</button>
                        </div>
                        <div class="max-h-[65vh] overflow-y-auto px-6 py-5">
                            <template x-if="!selectedMappingHistory?.history || selectedMappingHistory.history.length === 0">
                                <p class="text-sm text-slate-500">No mapping history yet.</p>
                            </template>
                            <template x-if="selectedMappingHistory?.history && selectedMappingHistory.history.length > 0">
                                <div class="space-y-2">
                                    <template x-for="(item, idx) in selectedMappingHistory.history" :key="idx">
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                            <p><span class="font-semibold">Time:</span> <span x-text="item.at || '—'"></span></p>
                                            <p><span class="font-semibold">Slot:</span> <span x-text="item.slot_key || '—'"></span></p>
                                            <p><span class="font-semibold">Status:</span> <span x-text="item.status || '—'"></span></p>
                                            <p><span class="font-semibold">Old Account ID:</span> <span x-text="item.old_account_id ?? '—'"></span></p>
                                            <p><span class="font-semibold">New Account ID:</span> <span x-text="item.new_account_id ?? '—'"></span></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
