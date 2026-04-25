<x-loan-layout>
    <x-loan.page title="Chart of Accounts & Rules" subtitle="Define and manage the company account structure, hierarchies, and regulatory compliance rules.">
        @php
            $fmtN = fn (int|float $n) => number_format((float) $n, 0);
            $assetRows = collect($accounts ?? [])->where('account_type', 'asset')->take(8)->values();
            $mappingRows = collect($postingRules ?? [])->take(8);
            $isEditingAccount = isset($editingAccount) && $editingAccount;
            $isDuplicatingAccount = ! $isEditingAccount && isset($duplicateAccount) && $duplicateAccount;
            $modalAccount = $isEditingAccount ? $editingAccount : ($isDuplicatingAccount ? $duplicateAccount : null);
        @endphp
        <style>
            @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css');
        </style>
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.chart.index') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors">Open Chart of Accounts</a>
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books Hub</a>
        </x-slot>

        <div
            class="space-y-6"
            x-data="{ showCreateAccountModal: {{ ($errors->any() || $isEditingAccount || $isDuplicatingAccount) ? 'true' : 'false' }}, showOverdrawnModal: false, allowOverdraft: {{ old('allow_overdraft', (bool) ($modalAccount->allow_overdraft ?? false)) ? 'true' : 'false' }} }"
            @keydown.escape.window="showCreateAccountModal = false; showOverdrawnModal = false"
        >
            <section class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Chart of Accounts &amp; Rules</h1>
                        <p class="mt-1 text-sm text-slate-600">Define and manage the company account structure, hierarchies, and regulatory compliance rules.</p>
                    </div>
                    <div class="space-y-3 lg:text-right">
                        <p class="text-sm font-medium text-slate-600">{{ now()->format('l, F j, Y') }}</p>
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        <div class="ml-auto w-full max-w-md rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-left">
                            <p class="text-sm font-semibold text-slate-900">Global Cash-Basis Settings</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                                    <span>System Accounting Mode: Cash-Basis Only</span>
                                    <input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                                    <span>Enforce Liquidity Guardrails</span>
                                    <input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900">Audit Status</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between rounded-lg bg-orange-50 px-3 py-2 text-orange-700">
                            <span>Pending Account Approvals</span>
                            <span class="font-semibold">{{ $fmtN((int) ($pendingApprovals ?? 0)) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-orange-50 px-3 py-2 text-orange-700">
                            <span>Accounts Missing Rules</span>
                            <span class="font-semibold">{{ $fmtN((int) ($missingRules ?? 0)) }}</span>
                        </div>
                    </div>
                </article>
                <article class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900">Financial Pulse</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-emerald-700">
                            <span>Active G/L Accounts</span>
                            <span class="font-semibold">{{ $fmtN((int) ($activeAccounts ?? 0)) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-emerald-700">
                            <span>New Accounts (30 Days)</span>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold">+{{ $fmtN((int) ($newAccounts30d ?? 0)) }}</span>
                        </div>
                        <button type="button" @click="showOverdrawnModal = true" class="flex w-full items-center justify-between rounded-lg border px-3 py-2 {{ (int) ($overdrawnCount ?? 0) > 0 ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                            <span class="inline-flex items-center gap-1">
                                @if ((int) ($overdrawnCount ?? 0) > 0)
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                @else
                                    <i class="fa-solid fa-circle-check"></i>
                                @endif
                                Overdrawn Accounts
                            </span>
                            <span class="font-semibold">{{ $fmtN((int) ($overdrawnCount ?? 0)) }}</span>
                        </button>
                    </div>
                </article>
                <article class="rounded-xl border border-teal-200 bg-white p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900">Balanced Books Meter</h2>
                    <p class="mt-3 text-sm text-slate-600">Total Debits Balance with Total Credits</p>
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 12A8 8 0 1 1 4 12" stroke-linecap="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ ($isBalanced ?? false) ? 'Balanced' : 'Out of Balance' }}
                        </span>
                    </div>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-10">
                <div class="space-y-6 xl:col-span-7">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Manage Chart of Accounts (Cash Flow View)</h2>
                            <button
                                type="button"
                                @click="showCreateAccountModal = true"
                                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                            >
                                Create New Account
                            </button>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
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
                                    <tr class="bg-emerald-50/60 text-xs font-semibold uppercase tracking-wide text-emerald-800">
                                        <td colspan="8" class="px-3 py-2">Assets</td>
                                    </tr>
                                    @forelse ($assetRows as $row)
                                        <tr class="group cursor-pointer hover:bg-teal-50/60">
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
                                                    <a href="{{ route('loan.accounting.books.chart_rules', ['edit_account' => $row->id]) }}" class="rounded p-1 hover:bg-blue-50 hover:text-blue-700" title="Edit"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></a>
                                                    <a href="{{ route('loan.accounting.books.chart_rules', ['duplicate_account' => $row->id]) }}" class="rounded p-1 hover:bg-blue-50 hover:text-blue-700" title="Duplicate"><i class="fa-solid fa-clone" aria-hidden="true"></i></a>
                                                    <a href="{{ route('loan.accounting.journal.index', ['q' => $row->code]) }}" class="rounded p-1 hover:bg-purple-50 hover:text-purple-700" title="Audit History"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="px-3 py-4 text-center text-slate-500">No asset accounts found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-900">Automated Cash Mappings (Maker-Checker)</h2>
                            <span class="rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Governance Layer</span>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
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
                                            $ruleState = $rule->debit_account_id && $rule->credit_account_id ? 'Active' : 'Awaiting Approval';
                                            $ruleClass = $ruleState === 'Active'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-orange-200 bg-orange-50 text-orange-700';
                                        @endphp
                                        <tr class="hover:bg-teal-50/60">
                                            <td class="px-3 py-2 font-medium text-slate-800">{{ $rule->label }}</td>
                                            <td class="px-3 py-2 text-slate-700">{{ $rule->debitAccount?->name ?? '—' }}</td>
                                            <td class="px-3 py-2 text-slate-700">{{ $rule->creditAccount?->name ?? '—' }}</td>
                                            <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $ruleClass }}">{{ $ruleState }}</span></td>
                                            <td class="px-3 py-2 text-slate-500">
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('loan.accounting.chart.index') }}" class="hover:text-blue-700" title="Edit Mapping"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></a>
                                                    <a href="{{ route('loan.accounting.journal.index') }}" class="hover:text-purple-700" title="Mapping History"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></a>
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
                </div>

                <aside class="xl:col-span-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Create / Edit Account Details</h2>
                        <div class="mt-4 space-y-5">
                            <section>
                                <h3 class="mb-2 text-sm font-semibold text-slate-800">General Information</h3>
                                <div class="space-y-3">
                                    <input type="text" placeholder="Account Name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <input type="text" placeholder="Account Code (mask-able)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <select class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option>Account Type</option></select>
                                        <select class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option>Parent Group</option></select>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <input type="text" placeholder="Opening Balance (KSh)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <input type="text" placeholder="Min. Required Balance (Floor)" class="rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm">
                                    </div>
                                    <p class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-700">WARNING: Blocks transaction if balance goes below floor.</p>
                                </div>
                            </section>
                            <section class="border-t border-slate-200 pt-4">
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <span>Account Active</span>
                                    <input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-emerald-600">
                                </label>
                            </section>
                            <section class="border-t border-slate-200 pt-4">
                                <h3 class="mb-3 text-sm font-semibold text-slate-800">Rules &amp; Governance Layer</h3>
                                <div class="space-y-3">
                                    <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><span>Requires Dual Authorization (Maker-Checker)</span><input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-blue-600"></label>
                                    <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option>Maker (e.g., Loan Officer)</option></select>
                                    <select class="w-full rounded-lg border border-slate-300 bg-purple-50 px-3 py-2 text-sm"><option>Checker required (Approver)</option></select>
                                    <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><span>Enforce Transaction Limit</span><input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-blue-600"></label>
                                    <input type="text" value="KSh 100k" class="w-full rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-900">
                                    <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><span>Requires Mandatory Cost Center Tag</span><input type="checkbox" class="h-4 w-8 rounded-full border border-slate-300 text-blue-600"></label>
                                </div>
                            </section>
                            <section class="border-t border-slate-200 pt-4">
                                <button type="button" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Save Account</button>
                                <p class="mt-3 inline-flex rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Proposed (Awaiting Director Approval)</p>
                            </section>
                        </div>
                    </div>
                </aside>
            </section>

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
                            <input type="hidden" name="redirect_to" value="{{ route('loan.accounting.books.chart_rules') }}" />
                            @if($isEditingAccount)
                                @method('patch')
                            @endif

                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label for="modal_code" class="mb-1 block text-xs font-semibold text-slate-600">Code</label>
                                    <input id="modal_code" name="code" value="{{ old('code', $isDuplicatingAccount ? (($modalAccount->code ?? '').'-COPY') : ($modalAccount->code ?? '')) }}" required class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                                    @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="modal_name" class="mb-1 block text-xs font-semibold text-slate-600">Name</label>
                                    <input id="modal_name" name="name" value="{{ old('name', $isDuplicatingAccount ? (($modalAccount->name ?? '').' Copy') : ($modalAccount->name ?? '')) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="modal_account_type" class="mb-1 block text-xs font-semibold text-slate-600">Type</label>
                                    <select id="modal_account_type" name="account_type" required class="w-full rounded-lg border-slate-200 text-sm">
                                        @foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $t)
                                            <option value="{{ $t }}" @selected(old('account_type', $modalAccount->account_type ?? 'asset') === $t)>{{ ucfirst($t) }}</option>
                                        @endforeach
                                    </select>
                                    @error('account_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label for="modal_account_class" class="mb-1 block text-xs font-semibold text-slate-600">Account Class</label>
                                    <select id="modal_account_class" name="account_class" class="w-full rounded-lg border-slate-200 text-sm">
                                        @foreach (['Header', 'Detail'] as $class)
                                            <option value="{{ $class }}" @selected(old('account_class', $modalAccount->account_class ?? 'Detail') === $class)>{{ $class }}</option>
                                        @endforeach
                                    </select>
                                    @error('account_class')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="modal_parent_id" class="mb-1 block text-xs font-semibold text-slate-600">Parent Account (Header)</label>
                                    <select id="modal_parent_id" name="parent_id" class="w-full rounded-lg border-slate-200 text-sm">
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
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <input type="hidden" name="is_active" value="0" />
                                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $modalAccount->is_active ?? true)) />
                                    Active
                                </label>
                                <div class="flex items-center md:justify-end">
                                    <span class="inline-flex rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Proposed</span>
                                </div>
                            </div>

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
                                                <a href="{{ route('loan.accounting.books.chart_rules', ['edit_account' => $a->id]) }}" class="rounded p-1 text-slate-500 hover:bg-blue-50 hover:text-blue-700" title="Edit"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></a>
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
        </div>
    </x-loan.page>
</x-loan-layout>
