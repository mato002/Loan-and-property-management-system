<x-loan-layout>
    <x-loan.page title="Journal Entry Command Center" subtitle="Temporary reset checkpoint.">
        <div class="p-4 text-sm text-slate-700">Reset checkpoint.</div>
    </x-loan.page>
</x-loan-layout>
@php
    $accountOptions = collect($selectAccounts ?? $accounts ?? [])->map(function ($account) {
        $name = (string) data_get($account, 'name', 'Account');
        return [
            'id' => (int) data_get($account, 'id'),
            'name' => $name,
            'code' => (string) data_get($account, 'code', ''),
            'restricted' => str_contains(strtolower($name), 'director') || str_contains(strtolower($name), 'equity'),
            'starting_balance' => str_contains(strtolower($name), 'cash') || str_contains(strtolower($name), 'bank') || str_contains(strtolower($name), 'm-pesa') ? 52000 : 120000,
            'floor' => str_contains(strtolower($name), 'cash') || str_contains(strtolower($name), 'bank') || str_contains(strtolower($name), 'm-pesa') ? 5000 : 0,
        ];
    })->values();
@endphp

<x-loan-layout>
    <x-loan.page title="Journal Entry Command Center" subtitle="Manage manual journal entries, adjustments, and non-routine cash movements.">
        <div class="min-h-full space-y-4 bg-slate-50 p-3 sm:p-5 lg:p-6" x-data="journalEntryCommandCenter(@js($accountOptions))" @keydown.escape.window="closeAllOverlays()">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Journal Entry Command Center</h1>
                        <p class="mt-1 text-sm text-slate-600">Manage manual journal entries, adjustments, and non-routine cash movements.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 lg:items-end">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium text-slate-600">Thursday, April 23, 2026</span>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center gap-2 rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v5H4zM4 14h10v5H4zM17 14h3v5h-3"></path></svg>
                                Quick Access &amp; Templates
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Create template">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-12">
                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="text-base font-semibold text-slate-900">Templates &amp; Quick Access</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Personal Favorites</p>
                                <div class="mt-2 space-y-2">
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-2 text-left text-sm text-blue-700">M-Pesa to Bank Transfer <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-2 text-left text-sm text-blue-700">Petty Cash Replenishment <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-purple-100 bg-purple-50/80 px-3 py-2 text-left text-sm text-purple-700">Director Equity Contribution <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-purple-100 bg-purple-50/80 px-3 py-2 text-left text-sm text-purple-700">Salary Advance Issuance <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-orange-100 bg-orange-50/80 px-3 py-2 text-left text-sm text-orange-700">Reversal of Previous Journal <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></button>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">System Defined</p>
                                <div class="mt-2 space-y-2">
                                    <button type="button" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700">Inter-Account Transfer</button>
                                    <button type="button" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700">Bank Charges Allocation</button>
                                </div>
                            </div>
                            <button type="button" class="w-full rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">View Full Posted History</button>
                        </div>
                    </article>
                </aside>

                <main class="space-y-4 xl:col-span-6">
                    <section class="grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-orange-700">Blocked Drafts (The Liquidity Queue)</p><p class="mt-2 text-3xl font-semibold text-orange-700">7 Entries</p><p class="mt-1 text-xs text-slate-500">Violations of Min. Balance Floor rules.</p></article>
                        <article class="rounded-xl border border-purple-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Approval Queue</p><p class="mt-2 text-3xl font-semibold text-purple-700">3 Entries</p><p class="mt-1 text-xs text-slate-500">Awaiting Director authorization</p></article>
                        <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Drafts &amp; Unposted</p><p class="mt-2 text-3xl font-semibold text-blue-700">5 Entries</p><p class="mt-1 text-xs text-slate-500">Not yet posted to the general ledger</p></article>
                    </section>

                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div><h2 class="text-lg font-semibold text-slate-900">Smart Journal Entry Form</h2><p class="text-sm text-slate-600">New Journal Entry</p></div>
                            <span class="inline-flex items-center gap-2 self-start rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">DNA Check: Director Approval Required</span>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <input type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                            <input type="text" placeholder="Reference" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                            <input type="text" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                        </div>
                        <p class="mt-3 text-xs text-slate-500">DNA Check is enabled for selected accounts if applicable based on account rules.</p>

                        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-[760px] w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    <tr><th class="px-3 py-2">Account</th><th class="px-3 py-2 text-right">Debit</th><th class="px-3 py-2 text-right">Credit</th><th class="px-3 py-2">Notes</th><th class="px-3 py-2 text-right">Projected Balance</th><th class="px-3 py-2 text-center">Audit</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(row, index) in rows" :key="index">
                                        <tr>
                                            <td class="px-3 py-2"><select x-model.number="row.accountId" @change="syncRow(index)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"><template x-for="opt in accountOptions" :key="opt.id"><option :value="opt.id" x-text="`${opt.code} · ${opt.name}`"></option></template></select></td>
                                            <td class="px-3 py-2 text-right"><input type="number" min="0" step="0.01" x-model.number="row.debit" @input="syncRow(index)" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                            <td class="px-3 py-2 text-right"><input type="number" min="0" step="0.01" x-model.number="row.credit" @input="syncRow(index)" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                            <td class="px-3 py-2"><input type="text" x-model="row.notes" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></td>
                                            <td class="px-3 py-2 text-right"><span class="font-semibold" :class="row.belowFloor ? 'text-orange-700' : 'text-emerald-700'" x-text="formatKsh(row.projectedBalance)"></span></td>
                                            <td class="px-3 py-2 text-center"><svg class="mx-auto h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="button" x-show="!hasFloorViolation && !requiresApproval" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Post Transaction</button>
                            <button type="button" x-show="!hasFloorViolation && requiresApproval" class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white">Submit for Approval</button>
                            <button type="button" x-show="hasFloorViolation" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white">Save as Blocked Draft</button>
                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Save as Draft</button>
                        </div>
                    </article>
                </main>

                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="text-base font-semibold text-slate-900">Last 15 Journal Activities</h2>
                        <div class="mt-3 max-h-[720px] space-y-2 overflow-y-auto pr-1">
                            <template x-for="item in activities" :key="item.id">
                                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                                    <p class="text-xs font-semibold text-slate-500" x-text="item.time"></p>
                                    <p class="text-sm font-medium text-slate-800" x-text="item.title"></p>
                                    <div class="mt-1 flex items-center justify-between"><span class="text-xs font-semibold" :class="item.status==='Posted' ? 'text-emerald-700' : (item.status==='Pending Approval' ? 'text-purple-700' : 'text-orange-700')" x-text="item.status"></span><span class="text-xs text-slate-500" x-text="item.amount"></span></div>
                                </div>
                            </template>
                        </div>
                    </article>
                </aside>
            </section>

            <div x-show="templateModalOpen" class="fixed inset-0 z-40 bg-slate-900/45"></div>
            <section x-show="templateModalOpen" class="fixed inset-x-3 top-[6%] z-50 mx-auto w-full max-w-5xl rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl sm:inset-x-6 sm:p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                    <div><h3 class="text-xl font-semibold text-slate-900">Create New Quick Access Template</h3><p class="mt-1 text-sm text-slate-500">Define accounting DNA mappings, governance controls, and maker-checker enforcement.</p></div>
                    <div class="flex items-center gap-2"><span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">Config Popup</span><button type="button" @click="templateModalOpen = false" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">x</button></div>
                </div>
                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="rounded-xl border border-slate-200 p-3"><h4 class="text-sm font-semibold text-slate-900">1. Template Details</h4><div class="mt-3 space-y-2"><input type="text" placeholder="Template Name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><textarea rows="2" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option>Personal</option><option>System</option></select></div></section>
                    <section class="rounded-xl border border-slate-200 p-3"><h4 class="text-sm font-semibold text-slate-900">2. Accounting DNA Mappings</h4><div class="mt-3 space-y-2"><select x-model.number="templateDebitId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Debit Account</option><template x-for="opt in accountOptions" :key="`d-${opt.id}`"><option :value="opt.id" x-text="`${opt.code} · ${opt.name}`"></option></template></select><select x-model.number="templateCreditId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Credit Account</option><template x-for="opt in accountOptions" :key="`c-${opt.id}`"><option :value="opt.id" x-text="`${opt.code} · ${opt.name}`"></option></template></select><input type="text" placeholder="Reference Prefix" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div></section>
                    <section class="rounded-xl border border-slate-200 p-3"><h4 class="text-sm font-semibold text-slate-900">3. Governance &amp; Rules Builder</h4><div class="mt-3 space-y-2 text-sm"><label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Requires Dual Authorization</span><input type="checkbox" checked class="h-4 w-4"></label><label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Enforce Transaction Limit</span><input type="checkbox" checked class="h-4 w-4"></label><input type="text" value="100,000.00" class="w-full rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-900"></div></section>
                </div>
                <div x-show="templateAccessRestricted" class="mt-4 rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-800">You are not authorized to create templates for this account. <span class="font-semibold">Access Restricted</span></div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
                    <span class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Active (COA Rules Applied)</span>
                    <div class="flex items-center gap-2"><button type="button" @click="templateModalOpen = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button><button type="button" :disabled="templateAccessRestricted" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-blue-300">Create Template</button></div>
                </div>
            </section>
        </div>
    </x-loan.page>

    <script>
        function journalEntryCommandCenter(accountOptionsInput) {
            const fallbackAccounts = [
                { id: 1, code: '4099', name: 'M-Pesa Utility', restricted: false, starting_balance: 52000, floor: 5000 },
                { id: 2, code: '4021', name: 'Cash Account', restricted: false, starting_balance: 9500, floor: 5000 },
                { id: 3, code: '61001', name: 'Equity Bank', restricted: false, starting_balance: 120000, floor: 10000 },
                { id: 4, code: '70010', name: 'Director Equity', restricted: true, starting_balance: 200000, floor: 0 },
            ];
            const accounts = Array.isArray(accountOptionsInput) && accountOptionsInput.length ? accountOptionsInput : fallbackAccounts;
            return {
                accountOptions: accounts,
                templateModalOpen: false,
                templateDebitId: '',
                templateCreditId: '',
                rows: [
                    { accountId: accounts[0]?.id || 1, debit: 5000, credit: 0, notes: 'Transfer from M-Pesa', projectedBalance: 0, floor: 0, belowFloor: false, restricted: false },
                    { accountId: accounts[1]?.id || 2, debit: 0, credit: 5000, notes: 'Cash deposit', projectedBalance: 0, floor: 0, belowFloor: false, restricted: false },
                    { accountId: accounts[2]?.id || 3, debit: 0, credit: 0, notes: 'Optional note', projectedBalance: 0, floor: 0, belowFloor: false, restricted: false },
                ],
                activities: [
                    { id: 1, time: '10:45 AM', title: 'KSh 1.2M M-Pesa to Bank', status: 'Posted', amount: 'KSh 1,200,000' },
                    { id: 2, time: '09:55 AM', title: 'Salary Payment Adjustment', status: 'Pending Approval', amount: 'KSh 180,000' },
                    { id: 3, time: '09:05 AM', title: 'Loan Recovery Reversal', status: 'Blocked Draft', amount: 'KSh 42,500' },
                ],
                init() { this.rows.forEach((_, i) => this.syncRow(i)); },
                get totalDebit() { return this.rows.reduce((s, r) => s + (Number(r.debit) || 0), 0); },
                get totalCredit() { return this.rows.reduce((s, r) => s + (Number(r.credit) || 0), 0); },
                get hasFloorViolation() { return this.rows.some((r) => r.belowFloor); },
                get requiresApproval() { return this.rows.some((r) => r.restricted) || this.totalDebit >= 100000; },
                get templateAccessRestricted() {
                    const d = this.accountOptions.find((a) => Number(a.id) === Number(this.templateDebitId));
                    const c = this.accountOptions.find((a) => Number(a.id) === Number(this.templateCreditId));
                    return Boolean(d?.restricted || c?.restricted);
                },
                syncRow(i) {
                    const row = this.rows[i];
                    const account = this.accountOptions.find((a) => Number(a.id) === Number(row.accountId));
                    if (!account) return;
                    row.floor = Number(account.floor) || 0;
                    row.restricted = Boolean(account.restricted);
                    row.projectedBalance = (Number(account.starting_balance) || 0) + (Number(row.debit) || 0) - (Number(row.credit) || 0);
                    row.belowFloor = row.floor > 0 && row.projectedBalance < row.floor;
                },
                formatKsh(v) { return `KSh ${Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; },
                openTemplateModal() { this.templateModalOpen = true; },
                closeAllOverlays() { this.templateModalOpen = false; },
            };
        }
    </script>
</x-loan-layout>
@php
    $accountOptions = collect($selectAccounts ?? $accounts ?? [])->map(function ($account) {
        $name = (string) data_get($account, 'name', 'Account');
        $isRestricted = str_contains(strtolower($name), 'director') || str_contains(strtolower($name), 'equity');
        $isCashLike = str_contains(strtolower($name), 'cash') || str_contains(strtolower($name), 'bank') || str_contains(strtolower($name), 'm-pesa');

        return [
            'id' => (int) data_get($account, 'id'),
            'name' => $name,
            'code' => (string) data_get($account, 'code', ''),
            'restricted' => $isRestricted,
            'starting_balance' => $isCashLike ? 52000 : 120000,
            'floor' => $isCashLike ? 5000 : 0,
        ];
    })->values();
@endphp

<x-loan-layout>
    <x-loan.page title="Journal Entry Command Center" subtitle="Manage manual journal entries, adjustments, and non-routine cash movements.">
        <div class="min-h-full space-y-4 bg-slate-50 p-3 sm:p-5 lg:p-6" x-data="journalEntryCommandCenter(@js($accountOptions))" @keydown.escape.window="closeAllOverlays()">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Journal Entry Command Center</h1>
                        <p class="mt-1 text-sm text-slate-600">Manage manual journal entries, adjustments, and non-routine cash movements.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 lg:items-end">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium text-slate-600">Thursday, April 23, 2026</span>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center gap-2 rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v5H4zM4 14h10v5H4zM17 14h3v5h-3"></path></svg>
                                Quick Access &amp; Templates
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Create template">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-12">
                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="text-base font-semibold text-slate-900">Templates &amp; Quick Access</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Personal Favorites</p>
                                <div class="mt-2 space-y-2">
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-2 text-left text-sm text-blue-700 transition hover:bg-blue-100">
                                        <span>M-Pesa to Bank Transfer</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                    </button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-2 text-left text-sm text-blue-700 transition hover:bg-blue-100">
                                        <span>Petty Cash Replenishment</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                    </button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-purple-100 bg-purple-50/80 px-3 py-2 text-left text-sm text-purple-700 transition hover:bg-purple-100">
                                        <span>Director Equity Contribution</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                    </button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-purple-100 bg-purple-50/80 px-3 py-2 text-left text-sm text-purple-700 transition hover:bg-purple-100">
                                        <span>Salary Advance Issuance</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                    </button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-orange-100 bg-orange-50/80 px-3 py-2 text-left text-sm text-orange-700 transition hover:bg-orange-100">
                                        <span>Reversal of Previous Journal</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">System Defined</p>
                                <div class="mt-2 space-y-2">
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700 transition hover:bg-slate-50"><span>Inter-Account Transfer</span><svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 12h10M13 8l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700 transition hover:bg-slate-50"><span>Bank Charges Allocation</span><svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 12h10M13 8l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700 transition hover:bg-slate-50"><span>Interest Income Recognition</span><svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 12h10M13 8l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-blue-700 transition hover:bg-slate-50"><span>Loan Write-Off Adjustment</span><svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 12h10M13 8l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                                </div>
                            </div>
                            <button type="button" class="w-full rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">View Full Posted History</button>
                        </div>
                    </article>
                </aside>

                <main class="space-y-4 xl:col-span-6">
                    <section class="grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-orange-700">Blocked Drafts (The Liquidity Queue)</p>
                                <svg class="h-4 w-4 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v4l3 2"></path><circle cx="12" cy="12" r="9"></circle></svg>
                            </div>
                            <p class="mt-2 text-3xl font-semibold text-orange-700">7 Entries</p>
                            <p class="mt-1 text-xs text-slate-500">Violations of Min. Balance Floor rules.</p>
                        </article>
                        <article class="rounded-xl border border-purple-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Approval Queue</p>
                                <svg class="h-4 w-4 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8.9 8 10 4.6-1.1 8-5 8-10V7l-8-4Z"></path></svg>
                            </div>
                            <p class="mt-2 text-3xl font-semibold text-purple-700">3 Entries</p>
                            <p class="mt-1 text-xs text-slate-500">Awaiting Director authorization</p>
                        </article>
                        <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Drafts &amp; Unposted</p>
                                <svg class="h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                            </div>
                            <p class="mt-2 text-3xl font-semibold text-blue-700">5 Entries</p>
                            <p class="mt-1 text-xs text-slate-500">Not yet posted to the general ledger</p>
                        </article>
                    </section>

                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Smart Journal Entry Form</h2>
                                <p class="text-sm text-slate-600">New Journal Entry</p>
                            </div>
                            <span class="inline-flex items-center gap-2 self-start rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8.9 8 10 4.6-1.1 8-5 8-10V7l-8-4Z"></path></svg>
                                DNA Check: Director Approval Required
                            </span>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="block text-sm">
                                <span class="mb-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Date <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></span>
                                <input type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Reference <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></span>
                                <input type="text" placeholder="Reference" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Description <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg></span>
                                <input type="text" placeholder="Enter description of transaction" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                            </label>
                        </div>

                        <p class="mt-3 text-xs text-slate-500">DNA Check is enabled for selected accounts <span class="font-medium text-slate-700">if applicable based on account rules.</span></p>

                        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-[760px] w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Account (DNA-checked)</th>
                                        <th class="px-3 py-2 text-right">Debit (KSh)</th>
                                        <th class="px-3 py-2 text-right">Credit (KSh)</th>
                                        <th class="px-3 py-2">Notes</th>
                                        <th class="px-3 py-2 text-right">Projected Balance (KSh)</th>
                                        <th class="px-3 py-2 text-center">Audit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(row, index) in rows" :key="index">
                                        <tr>
                                            <td class="px-3 py-2">
                                                <select x-model.number="row.accountId" @change="syncRow(index)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-800">
                                                    <template x-for="opt in accountOptions" :key="opt.id">
                                                        <option :value="opt.id" x-text="`${opt.code} · ${opt.name}`"></option>
                                                    </template>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <input type="number" min="0" step="0.01" x-model.number="row.debit" @input="syncRow(index)" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm">
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <input type="number" min="0" step="0.01" x-model.number="row.credit" @input="syncRow(index)" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="text" x-model="row.notes" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <span class="font-semibold" :class="row.belowFloor ? 'text-orange-700' : 'text-emerald-700'" x-text="formatKsh(row.projectedBalance)"></span>
                                                <p x-show="row.belowFloor" class="text-xs text-orange-600">below floor</p>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" class="text-slate-400 hover:text-slate-700" aria-label="View audit trail">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <button type="button" @click="addRow()" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"></path></svg>
                                Add Journal Line
                            </button>
                            <div class="text-xs font-semibold text-slate-600">
                                Total Debit (KSh): <span x-text="formatKsh(totalDebit)"></span>
                                <span class="mx-2 text-slate-300">|</span>
                                Total Credit (KSh): <span x-text="formatKsh(totalCredit)"></span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span x-show="attachmentRequired" class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 11V7a4 4 0 1 1 8 0v4"></path><rect x="5" y="11" width="14" height="10" rx="2"></rect></svg>
                                Attachment Required
                            </span>
                            <div x-show="hasFloorViolation" class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-700">
                                Projected Balance: <span x-text="formatKsh(lowestProjectedBalance)"></span> (Below COA Floor: <span x-text="formatKsh(lowestFloor)"></span>)
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="button" x-show="!hasFloorViolation && !requiresApproval" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Post Transaction</button>
                            <button type="button" x-show="!hasFloorViolation && requiresApproval" class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-800">Submit for Approval</button>
                            <button type="button" x-show="hasFloorViolation" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">Save as Blocked Draft</button>
                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save as Draft</button>
                        </div>

                        <p class="mt-3 text-xs text-slate-500">
                            When a high-value account is selected, or the amount pushes the projected balance below the COA floor, the amount field highlights in orange.
                        </p>
                    </article>
                </main>

                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="text-base font-semibold text-slate-900">Last 15 Journal Activities</h2>
                        <div class="mt-3 max-h-[720px] space-y-2 overflow-y-auto pr-1">
                            <template x-for="item in activities" :key="item.id">
                                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-xs font-semibold text-slate-500" x-text="item.time"></p>
                                            <p class="text-sm font-medium text-slate-800" x-text="item.title"></p>
                                            <p class="text-xs text-slate-500" x-text="item.ref"></p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" x-show="item.status==='Posted'" class="rounded p-1 text-orange-600 hover:bg-orange-50" aria-label="Reverse">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 7H3v4"></path><path d="M3 11a9 9 0 1 0 3-6.7"></path></svg>
                                            </button>
                                            <button type="button" x-show="item.status==='Blocked Draft'" class="rounded p-1 text-blue-600 hover:bg-blue-50" aria-label="Retry">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12a9 9 0 1 1-3-6.7"></path><path d="M21 3v6h-6"></path></svg>
                                            </button>
                                            <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Audit history">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between">
                                        <span class="text-xs font-semibold" :class="item.status==='Posted' ? 'text-emerald-700' : (item.status==='Pending Approval' ? 'text-purple-700' : 'text-orange-700')" x-text="item.status"></span>
                                        <span class="text-xs text-slate-500" x-text="item.amount"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </article>
                </aside>
            </section>

            <div x-show="templateModalOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/45"></div>
            <section
                x-show="templateModalOpen"
                x-transition:enter="transition duration-200 ease-out"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="fixed inset-x-3 top-[6%] z-50 mx-auto w-full max-w-5xl rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl sm:inset-x-6 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-xl font-semibold text-slate-900">Create New Quick Access Template</h3>
                        <p class="mt-1 text-sm text-slate-500">Define accounting DNA mappings, governance controls, and maker-checker enforcement.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">Config Popup</span>
                        <button type="button" @click="templateModalOpen = false" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close modal">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="rounded-xl border border-slate-200 p-3">
                        <h4 class="text-sm font-semibold text-slate-900">1. Template Details</h4>
                        <div class="mt-3 space-y-2">
                            <input type="text" placeholder="Template Name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <textarea rows="2" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                            <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option>Personal</option>
                                <option>System</option>
                            </select>
                            <button type="button" class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">View / Manage All</button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-slate-900">2. Accounting DNA Mappings</h4>
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">DNA-checked</span>
                        </div>
                        <div class="mt-3 space-y-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">Debit Account (COA) <span class="inline-block align-middle text-slate-400">🕒</span></label>
                            <select x-model.number="templateDebitId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Select account</option>
                                <template x-for="opt in accountOptions" :key="`d-${opt.id}`">
                                    <option :value="opt.id" x-text="`${opt.code} · ${opt.name}`"></option>
                                </template>
                            </select>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">Credit Account (COA) <span class="inline-block align-middle text-slate-400">🕒</span></label>
                            <select x-model.number="templateCreditId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Select account</option>
                                <template x-for="opt in accountOptions" :key="`c-${opt.id}`">
                                    <option :value="opt.id" x-text="`${opt.code} · ${opt.name}`"></option>
                                </template>
                            </select>
                            <input type="text" placeholder="Reference Prefix (e.g. RENT-)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <div class="space-y-1 pt-1 text-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Amount Type</p>
                                <label class="flex items-center gap-2"><input type="radio" name="amount_type" checked class="h-4 w-4 border-slate-300 text-blue-600"> Fixed Amount</label>
                                <label class="flex items-center gap-2"><input type="radio" name="amount_type" class="h-4 w-4 border-slate-300 text-blue-600"> Variable Amount</label>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 p-3">
                        <h4 class="text-sm font-semibold text-slate-900">3. Governance &amp; Rules Builder</h4>
                        <div class="mt-3 space-y-2 text-sm">
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Requires Dual Authorization</span><input type="checkbox" checked class="h-4 w-4 border-slate-300 text-blue-600"></label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Requires Mandatory Cost Center Tag</span><input type="checkbox" class="h-4 w-4 border-slate-300 text-blue-600"></label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Requires Mandatory Client / Loan ID</span><input type="checkbox" class="h-4 w-4 border-slate-300 text-blue-600"></label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><span>Enforce Transaction Limit</span><input type="checkbox" checked class="h-4 w-4 border-slate-300 text-blue-600"></label>
                            <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option>Authorized Approver / Checker</option>
                            </select>
                            <input type="text" value="100,000.00" class="w-full rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-900">
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] font-semibold">
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">Active</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700">History</span>
                            <span class="rounded-full bg-orange-100 px-2 py-0.5 text-orange-700">Access Restricted</span>
                            <span class="rounded-full bg-purple-100 px-2 py-0.5 text-purple-700">COA Rules Applied</span>
                        </div>
                    </section>
                </div>

                <div x-show="templateAccessRestricted" class="mt-4 rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-800">
                    You are not authorized to create templates for this account. <span class="font-semibold">Access Restricted</span>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
                    <span class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Active (COA Rules Applied)</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="templateModalOpen = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="button" :disabled="templateAccessRestricted" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-blue-300">Create Template</button>
                    </div>
                </div>
            </section>
        </div>
    </x-loan.page>

    <script>
        function journalEntryCommandCenter(accountOptionsInput) {
            const fallbackAccounts = [
                { id: 1, code: '4099', name: 'M-Pesa Utility', restricted: false, starting_balance: 52000, floor: 5000 },
                { id: 2, code: '4021', name: 'Cash Account', restricted: false, starting_balance: 9500, floor: 5000 },
                { id: 3, code: '61001', name: 'Equity Bank', restricted: false, starting_balance: 120000, floor: 10000 },
                { id: 4, code: '70010', name: 'Director Equity', restricted: true, starting_balance: 200000, floor: 0 },
            ];
            const accounts = Array.isArray(accountOptionsInput) && accountOptionsInput.length > 0 ? accountOptionsInput : fallbackAccounts;

            const makeRow = (accountId, debit, credit, notes) => ({
                accountId,
                debit,
                credit,
                notes,
                projectedBalance: 0,
                floor: 0,
                belowFloor: false,
                restricted: false,
            });

            return {
                accountOptions: accounts,
                templateModalOpen: false,
                templateDebitId: '',
                templateCreditId: '',
                rows: [
                    makeRow(accounts[0]?.id || 1, 5000, 0, 'Transfer from M-Pesa'),
                    makeRow(accounts[1]?.id || 2, 0, 5000, 'Cash deposit'),
                    makeRow(accounts[2]?.id || 3, 0, 0, 'Optional note'),
                ],
                activities: [
                    { id: 1, time: '10:45 AM', title: 'KSh 1.2M M-Pesa to Bank', ref: 'Ref: JE-000245', status: 'Posted', amount: 'KSh 1,200,000' },
                    { id: 2, time: '10:20 AM', title: 'Petty Cash Replenishment', ref: 'Ref: JE-000244', status: 'Posted', amount: 'KSh 15,000' },
                    { id: 3, time: '09:55 AM', title: 'Salary Payment Adjustment', ref: 'Ref: JE-000243', status: 'Pending Approval', amount: 'KSh 180,000' },
                    { id: 4, time: '09:30 AM', title: 'Cash Deposit to Bank', ref: 'Ref: JE-000242', status: 'Posted', amount: 'KSh 300,000' },
                    { id: 5, time: '09:05 AM', title: 'Loan Recovery Reversal', ref: 'Ref: JE-000241', status: 'Blocked Draft', amount: 'KSh 42,500' },
                    { id: 6, time: '08:40 AM', title: 'Bank Charges Allocation', ref: 'Ref: JE-000240', status: 'Posted', amount: 'KSh 2,350' },
                    { id: 7, time: '08:15 AM', title: 'Suspense Account Clearance', ref: 'Ref: JE-000239', status: 'Pending Approval', amount: 'KSh 95,000' },
                    { id: 8, time: '07:55 AM', title: 'Inter-Account Transfer', ref: 'Ref: JE-000238', status: 'Posted', amount: 'KSh 73,000' },
                    { id: 9, time: '07:30 AM', title: 'Utility Float Top-Up', ref: 'Ref: JE-000237', status: 'Posted', amount: 'KSh 25,000' },
                    { id: 10, time: '07:05 AM', title: 'Manual Interest Recognition', ref: 'Ref: JE-000236', status: 'Pending Approval', amount: 'KSh 110,000' },
                    { id: 11, time: '06:50 AM', title: 'Suspense Reclass', ref: 'Ref: JE-000235', status: 'Blocked Draft', amount: 'KSh 12,000' },
                    { id: 12, time: '06:35 AM', title: 'Tax Withholding Adjustment', ref: 'Ref: JE-000234', status: 'Posted', amount: 'KSh 8,200' },
                    { id: 13, time: '06:10 AM', title: 'Cash Float Correction', ref: 'Ref: JE-000233', status: 'Posted', amount: 'KSh 5,400' },
                    { id: 14, time: '05:50 AM', title: 'Director Equity Contribution', ref: 'Ref: JE-000232', status: 'Pending Approval', amount: 'KSh 250,000' },
                    { id: 15, time: '05:30 AM', title: 'Error Reversal Draft', ref: 'Ref: JE-000231', status: 'Blocked Draft', amount: 'KSh 6,900' },
                ],
                init() {
                    this.rows.forEach((_, index) => this.syncRow(index));
                },
                get totalDebit() {
                    return this.rows.reduce((sum, row) => sum + (Number(row.debit) || 0), 0);
                },
                get totalCredit() {
                    return this.rows.reduce((sum, row) => sum + (Number(row.credit) || 0), 0);
                },
                get hasFloorViolation() {
                    return this.rows.some((row) => row.belowFloor);
                },
                get requiresApproval() {
                    return this.rows.some((row) => row.restricted) || this.totalDebit >= 100000;
                },
                get attachmentRequired() {
                    return this.requiresApproval;
                },
                get lowestProjectedBalance() {
                    return this.rows.reduce((min, row) => row.projectedBalance < min ? row.projectedBalance : min, this.rows[0]?.projectedBalance || 0);
                },
                get lowestFloor() {
                    return this.rows.reduce((min, row) => row.floor > 0 && row.floor < min ? row.floor : min, Number.MAX_SAFE_INTEGER) === Number.MAX_SAFE_INTEGER
                        ? 0
                        : this.rows.reduce((min, row) => row.floor > 0 && row.floor < min ? row.floor : min, Number.MAX_SAFE_INTEGER);
                },
                get templateAccessRestricted() {
                    const debit = this.accountOptions.find((a) => Number(a.id) === Number(this.templateDebitId));
                    const credit = this.accountOptions.find((a) => Number(a.id) === Number(this.templateCreditId));
                    return Boolean(debit?.restricted || credit?.restricted);
                },
                syncRow(index) {
                    const row = this.rows[index];
                    const account = this.accountOptions.find((a) => Number(a.id) === Number(row.accountId));
                    if (!account) return;
                    row.floor = Number(account.floor) || 0;
                    row.restricted = Boolean(account.restricted);
                    const startBalance = Number(account.starting_balance) || 0;
                    row.projectedBalance = startBalance + (Number(row.debit) || 0) - (Number(row.credit) || 0);
                    row.belowFloor = row.floor > 0 && row.projectedBalance < row.floor;
                },
                addRow() {
                    const first = this.accountOptions[0];
                    this.rows.push({
                        accountId: first?.id || null,
                        debit: 0,
                        credit: 0,
                        notes: '',
                        projectedBalance: Number(first?.starting_balance || 0),
                        floor: Number(first?.floor || 0),
                        belowFloor: false,
                        restricted: Boolean(first?.restricted),
                    });
                    this.syncRow(this.rows.length - 1);
                },
                formatKsh(value) {
                    const num = Number(value || 0);
                    return `KSh ${num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                },
                openTemplateModal() {
                    this.templateModalOpen = true;
                },
                closeAllOverlays() {
                    this.templateModalOpen = false;
                },
            };
        }
    </script>
</x-loan-layout>
@php
    $fmt = fn (int|float $n) => number_format((float) $n, 2);
    $financialOverview = $financialOverview ?? [
        'net_cash_surplus' => 0,
        'cash_collected' => 0,
        'cash_spent' => 0,
        'aggregate_cash_position' => 0,
        'bank_cash_position' => 0,
        'mpesa_cash_position' => 0,
        'tax_estimate' => 0,
        'operating_margin_pct' => 0,
        'cost_per_client' => 0,
        'collection_efficiency_pct' => 0,
    ];
    $reports = $reports ?? [];
    $dateRangeLabel = $dateRangeLabel ?? now()->startOfMonth()->format('F j').' - '.now()->format('F j, Y');
@endphp

<x-loan-layout>
    <x-loan.page title="Books of Account" subtitle="Financial intelligence command context for cash-basis executive oversight.">
        <div class="space-y-6" x-data="financialIntelligencePage()" @keydown.escape.window="closeAllPanels()">
            <section class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Real-Time Financial Intelligence (Cash-Basis: Fortress Lenders)</h1>
                        <p class="mt-1 text-sm text-slate-600">Real-time visibility into liquidity, performance, and compliance.</p>
                    </div>
                    <div class="space-y-3 xl:text-right">
                        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M8 3v4M16 3v4M3 10h18"></path></svg>
                                {{ now()->format('l, F j, Y') }}
                            </span>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row xl:justify-end">
                            <button type="button" @click="openDateModal()" class="inline-flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:border-teal-200 hover:bg-teal-50/40">
                                <span>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-teal-700">Time Traveler</span>
                                    <span class="block font-semibold text-slate-900" x-text="dateRange"></span>
                                    <span class="block text-xs text-slate-500">Real-time data synced to this range</span>
                                </span>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">Quick Access &amp; Templates</button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Create template">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">TIER 1 (Top): The "Real-Time Profitability" Dashboard</h2>
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                        <input type="checkbox" x-model="compareMode" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Compare (Prev. Month/Year)
                    </label>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Real-Time Income Statement</h3>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">Net Cash Surplus</p>
                        <p class="mt-1 text-left text-4xl font-semibold leading-tight text-emerald-700">KSh {{ $fmt((float) data_get($financialOverview, 'net_cash_surplus', 0)) }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2">
                                <p class="font-semibold text-emerald-700">Total Cash Collected</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cash_collected', 0)) }}</p>
                                <p class="mt-1 text-slate-600">Live from income-account credits.</p>
                            </div>
                            <div class="rounded-lg bg-orange-50 px-3 py-2">
                                <p class="font-semibold text-orange-700">Total Cash Spent</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cash_spent', 0)) }}</p>
                                <p class="mt-1 text-slate-600">Live from expense-account debits.</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Liquidity &amp; Statutory Shield</h3>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">Aggregate Cash Position</p>
                        <p class="mt-1 text-4xl font-semibold leading-tight text-blue-700">KSh {{ $fmt((float) data_get($financialOverview, 'aggregate_cash_position', 0)) }}</p>
                        <ul class="mt-4 space-y-1 text-sm text-slate-700">
                            <li class="flex justify-between"><span>Equity Bank (all accounts)</span><span class="font-semibold">KSh {{ $fmt((float) data_get($financialOverview, 'bank_cash_position', 0)) }}</span></li>
                            <li class="flex justify-between"><span>M-Pesa utility (till &amp; float)</span><span class="font-semibold">KSh {{ $fmt((float) data_get($financialOverview, 'mpesa_cash_position', 0)) }}</span></li>
                        </ul>
                        <p class="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800">Estimated KRA liability: KSh {{ $fmt((float) data_get($financialOverview, 'tax_estimate', 0)) }}</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Management Efficiency</h3>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">Operating Margin</p>
                        <p class="mt-1 text-4xl font-semibold leading-tight text-orange-700">{{ $fmt((float) data_get($financialOverview, 'operating_margin_pct', 0)) }}%</p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><p class="text-slate-500">Cost per client</p><p class="font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cost_per_client', 0)) }}</p></div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><p class="text-slate-500">Collection efficiency</p><p class="font-semibold text-slate-900">{{ $fmt((float) data_get($financialOverview, 'collection_efficiency_pct', 0)) }}%</p></div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-base font-semibold text-slate-900">TIER 2: The Statement Vault (2x3 Grid of Report Tiles)</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $report)
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $report['title'] }}</h3>
                                    <p class="mt-1 text-xs text-slate-600">{{ $report['description'] }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
                                <button type="button" @click="exportPdf('{{ $report['title'] }}')" class="font-semibold text-blue-700 transition hover:text-blue-800">PDF Export</button>
                                <span class="inline-flex items-center gap-1 text-slate-500">Last generated: {{ $report['last'] }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <div x-show="peekOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/30" @click="peekOpen = false"></div>
            <aside x-show="peekOpen" class="fixed inset-x-4 top-20 z-50 mx-auto w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-xl sm:inset-x-0">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="mt-1 text-lg font-semibold text-slate-900" x-text="peekTitle"></h3>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="peekOpen = false" aria-label="Close panel">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                    </button>
                </div>
            </aside>
        </div>
    </x-loan.page>

    <script>
        function financialIntelligencePage() {
            return {
                compareMode: false,
                modalOpen: false,
                modalMode: 'template',
                peekOpen: false,
                peekTitle: 'Net Cash Surplus',
                dateRange: @js($dateRangeLabel),
                openTemplateModal() { this.modalMode = 'template'; this.modalOpen = true; },
                openDateModal() { this.modalMode = 'date'; this.modalOpen = true; },
                openPeekPanel(title) { this.peekTitle = title; this.peekOpen = true; },
                closeAllPanels() { this.modalOpen = false; this.peekOpen = false; },
                exportPdf(title) {
                    const safeTitle = String(title || 'report').replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase();
                    const fileName = `${safeTitle || 'report'}-as-of-${this.dateRange.replace(/\s+/g, '-')}.pdf`;
                    const escapePdf = (value) => String(value || '').replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
                    const lines = [`Report: ${title}`, `Date Range: ${this.dateRange}`, `Generated At: ${new Date().toLocaleString()}`];
                    const textOps = lines.map((line, idx) => `1 0 0 1 50 ${760 - (idx * 18)} Tm (${escapePdf(line)}) Tj`).join('\n');
                    const stream = `BT\n/F1 12 Tf\n${textOps}\nET`;
                    const objects = [
                        '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj',
                        '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj',
                        '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj',
                        `4 0 obj\n<< /Length ${stream.length} >>\nstream\n${stream}\nendstream\nendobj`,
                        '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj',
                    ];
                    let pdf = '%PDF-1.4\n';
                    const offsets = [0];
                    objects.forEach((obj) => { offsets.push(pdf.length); pdf += `${obj}\n`; });
                    const xrefOffset = pdf.length;
                    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
                    offsets.slice(1).forEach((offset) => { pdf += `${String(offset).padStart(10, '0')} 00000 n \n`; });
                    pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;
                    const blob = new Blob([pdf], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = fileName;
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                },
            };
        }
    </script>
</x-loan-layout>
@php
    $fmt = fn (int|float $n) => number_format((float) $n, 2);
    $financialOverview = $financialOverview ?? [];
    $reports = $reports ?? [];
    $periodLabel = $periodLabel ?? now()->format('F Y').' (Open)';
    $dateRangeLabel = $dateRangeLabel ?? now()->startOfMonth()->format('F j').' - '.now()->format('F j, Y');
@endphp

<x-loan-layout>
    <x-loan.page title="Books of Account" subtitle="Financial intelligence command context for cash-basis executive oversight.">
        <div class="space-y-6" x-data="financialIntelligencePage()" @keydown.escape.window="closeAllPanels()">
            <section class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Real-Time Financial Intelligence (Cash-Basis: Fortress Lenders)</h1>
                        <p class="mt-1 text-sm text-slate-600">Real-time visibility into liquidity, performance, and compliance.</p>
                    </div>
                    <div class="space-y-3 xl:text-right">
                        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">{{ now()->format('l, F j, Y') }}</span>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: {{ $periodLabel }}</span>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row xl:justify-end">
                            <button type="button" @click="openDateModal()" class="inline-flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-sm text-slate-700">
                                <span>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-teal-700">Time Traveler</span>
                                    <span class="block font-semibold text-slate-900" x-text="dateRange"></span>
                                    <span class="block text-xs text-slate-500">Real-time data synced to this range</span>
                                </span>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Quick Access &amp; Templates</button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white" aria-label="Create template">+</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">TIER 1 (Top): The "Real-Time Profitability" Dashboard</h2>
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                        <input type="checkbox" x-model="compareMode" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Compare (Prev. Month/Year)
                    </label>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Real-Time Income Statement</h3>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">Net Cash Surplus</p>
                        <button type="button" @click="openPeekPanel('Net Cash Surplus')" class="mt-1 text-left text-4xl font-semibold leading-tight text-emerald-700">KSh {{ $fmt((float) data_get($financialOverview, 'net_cash_surplus', 0)) }}</button>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2">
                                <p class="font-semibold text-emerald-700">Total Cash Collected</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cash_collected', 0)) }}</p>
                                <p class="mt-1 text-slate-600">Interest: KSh {{ $fmt((float) data_get($financialOverview, 'interest_collected', 0)) }}<br>Processing fees: KSh {{ $fmt((float) data_get($financialOverview, 'processing_fees_collected', 0)) }}</p>
                            </div>
                            <div class="rounded-lg bg-orange-50 px-3 py-2">
                                <p class="font-semibold text-orange-700">Total Cash Spent</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cash_spent', 0)) }}</p>
                                <p class="mt-1 text-slate-600">OPEX: KSh {{ $fmt((float) data_get($financialOverview, 'opex_spent', 0)) }}<br>Salaries: KSh {{ $fmt((float) data_get($financialOverview, 'salaries_spent', 0)) }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Liquidity &amp; Statutory Shield</h3>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">Aggregate Cash Position</p>
                        <p class="mt-1 text-4xl font-semibold leading-tight text-blue-700">KSh {{ $fmt((float) data_get($financialOverview, 'aggregate_cash_position', 0)) }}</p>
                        <ul class="mt-4 space-y-1 text-sm text-slate-700">
                            <li class="flex justify-between"><span>Equity Bank (all accounts)</span><span class="font-semibold">KSh {{ $fmt((float) data_get($financialOverview, 'bank_cash_position', 0)) }}</span></li>
                            <li class="flex justify-between"><span>M-Pesa utility (till &amp; float)</span><span class="font-semibold">KSh {{ $fmt((float) data_get($financialOverview, 'mpesa_cash_position', 0)) }}</span></li>
                        </ul>
                        <p class="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800">Estimated KRA liability: KSh {{ $fmt((float) data_get($financialOverview, 'tax_estimate', 0)) }}</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">Management Efficiency</h3>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">Operating Margin</p>
                        <p class="mt-1 text-4xl font-semibold leading-tight text-orange-700">{{ $fmt((float) data_get($financialOverview, 'operating_margin_pct', 0)) }}%</p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><p class="text-slate-500">Cost per client</p><p class="font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cost_per_client', 0)) }}</p></div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><p class="text-slate-500">Collection efficiency</p><p class="font-semibold text-slate-900">{{ $fmt((float) data_get($financialOverview, 'collection_efficiency_pct', 0)) }}%</p></div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-base font-semibold text-slate-900">TIER 2: The Statement Vault (2x3 Grid of Report Tiles)</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $report)
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-slate-900">{{ $report['title'] }}</h3>
                            <p class="mt-1 text-xs text-slate-600">{{ $report['description'] }}</p>
                            @if ($report['title'] === 'Tax / KRA Ledger')
                                <p class="mt-2 inline-flex items-center rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-800">Next KRA due in {{ (int) data_get($financialOverview, 'kra_deadline_days', 0) }} days</p>
                            @endif
                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
                                <button type="button" class="font-semibold text-blue-700">PDF Export</button>
                                <span class="text-slate-500">Last generated: {{ $report['last'] }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <div x-show="peekOpen" class="fixed inset-0 z-40 bg-slate-900/30" @click="peekOpen = false"></div>
            <aside x-show="peekOpen" class="fixed inset-x-4 top-20 z-50 mx-auto w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-xl sm:inset-x-0">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-lg font-semibold text-slate-900" x-text="peekTitle"></h3>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="peekOpen = false">x</button>
                </div>
            </aside>

            <div x-show="modalOpen" class="fixed inset-0 z-40 bg-slate-900/45" @click="modalOpen = false"></div>
            <section x-show="modalOpen" class="fixed inset-x-4 top-[12%] z-50 mx-auto w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:inset-x-0">
                <h3 class="text-xl font-semibold text-slate-900" x-text="modalMode === 'date' ? 'Time Traveler Control' : 'Template Creation System'"></h3>
                <template x-if="modalMode === 'date'">
                    <div class="mt-4 space-y-3">
                        <input type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800" x-model="dateRange">
                        <div class="flex justify-end"><button type="button" @click="modalOpen = false" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply Range</button></div>
                    </div>
                </template>
                <template x-if="modalMode === 'template'">
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select class="rounded-lg border border-slate-300 px-3 py-2"><option>COA Mapping (Debit/Credit)</option></select>
                        <select class="rounded-lg border border-slate-300 px-3 py-2"><option>Governance Rules (Maker-Checker)</option></select>
                        <select class="rounded-lg border border-slate-300 px-3 py-2"><option>Amount Logic (fixed/variable)</option></select>
                        <select class="rounded-lg border border-slate-300 px-3 py-2"><option>Approval Controls</option></select>
                        <div class="sm:col-span-2 flex justify-end"><button type="button" class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Create Template</button></div>
                    </div>
                </template>
            </section>
        </div>
    </x-loan.page>

    <script>
        function financialIntelligencePage() {
            return {
                compareMode: false,
                modalOpen: false,
                modalMode: 'template',
                peekOpen: false,
                peekTitle: 'Net Cash Surplus',
                dateRange: @js($dateRangeLabel),
                openTemplateModal() { this.modalMode = 'template'; this.modalOpen = true; },
                openDateModal() { this.modalMode = 'date'; this.modalOpen = true; },
                openPeekPanel(title) { this.peekTitle = title; this.peekOpen = true; },
                closeAllPanels() { this.modalOpen = false; this.peekOpen = false; },
            };
        }
    </script>
</x-loan-layout>
@php
    $fmt = fn (int|float $n) => number_format((float) $n, 2);
    $financialOverview = $financialOverview ?? [
        'cash_collected' => 0,
        'cash_spent' => 0,
        'net_cash_surplus' => 0,
        'aggregate_cash_position' => 0,
        'bank_cash_position' => 0,
        'mpesa_cash_position' => 0,
        'tax_estimate' => 0,
        'operating_margin_pct' => 0,
        'cost_per_client' => 0,
        'collection_efficiency_pct' => 0,
    ];
    $reports = $reports ?? [];
@endphp

<x-loan-layout>
    <x-loan.page title="Books of Account" subtitle="Financial intelligence command context for cash-basis executive oversight.">
        <div class="space-y-6" x-data="financialIntelligencePage()" @keydown.escape.window="closeAllPanels()">
            <section class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Real-Time Financial Intelligence (Cash-Basis: Fortress Lenders)</h1>
                        <p class="mt-1 text-sm text-slate-600">Real-time visibility into liquidity, performance, and compliance.</p>
                    </div>
                    <div class="space-y-3 xl:text-right">
                        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M8 3v4M16 3v4M3 10h18"></path></svg>
                                {{ now()->format('l, F j, Y') }}
                            </span>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row xl:justify-end">
                            <button type="button" @click="openDateModal()" class="inline-flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:border-teal-200 hover:bg-teal-50/40">
                                <span>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-teal-700">Time Traveler</span>
                                    <span class="block font-semibold text-slate-900" x-text="dateRange"></span>
                                    <span class="block text-xs text-slate-500">Real-time data synced to this range</span>
                                </span>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">Quick Access &amp; Templates</button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Create template">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">TIER 1 (Top): The "Real-Time Profitability" Dashboard</h2>
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                        <input type="checkbox" x-model="compareMode" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Compare (Prev. Month/Year)
                    </label>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10M10 20V4M16 20v-6M22 20v-9"></path></svg>
                                </span>
                                <h3 class="text-sm font-semibold text-slate-900">Real-Time Income Statement</h3>
                            </div>
                            <button type="button" @click="openAuditTrail('Real-Time Income Statement')" class="text-slate-400 hover:text-slate-700" aria-label="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Net Cash Surplus</p>
                        <button type="button" @click="openPeekPanel('Net Cash Surplus')" class="mt-1 text-left text-4xl font-semibold leading-tight text-emerald-700">KSh {{ $fmt((float) data_get($financialOverview, 'net_cash_surplus', 0)) }}</button>
                        <p class="text-sm text-slate-500">(Revenue - OPEX)</p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2">
                                <p class="font-semibold text-emerald-700">Total Cash Collected</p>
                                <button type="button" @click="openPeekPanel('Total Cash Collected')" class="mt-1 text-sm font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cash_collected', 0)) }}</button>
                                <p class="mt-1 text-slate-600">Live from income-account credits.</p>
                            </div>
                            <div class="rounded-lg bg-orange-50 px-3 py-2">
                                <p class="font-semibold text-orange-700">Total Cash Spent</p>
                                <button type="button" @click="openPeekPanel('Total Cash Spent')" class="mt-1 text-sm font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cash_spent', 0)) }}</button>
                                <p class="mt-1 text-slate-600">Live from expense-account debits.</p>
                            </div>
                        </div>
                        <svg class="mt-4 h-20 w-full" viewBox="0 0 360 80" aria-hidden="true">
                            <rect x="40" y="20" width="100" height="46" rx="6" fill="#14b8a6"></rect>
                            <rect x="210" y="29" width="100" height="37" rx="6" fill="#f97316"></rect>
                            <text x="90" y="15" text-anchor="middle" fill="#0f172a" font-size="10">{{ number_format(((float) data_get($financialOverview, 'cash_collected', 0)) / 1000000, 2) }}M</text>
                            <text x="260" y="24" text-anchor="middle" fill="#0f172a" font-size="10">{{ number_format(((float) data_get($financialOverview, 'cash_spent', 0)) / 1000000, 2) }}M</text>
                        </svg>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Cash-Basis: Realized Income Only</span>
                            <p class="text-xs font-semibold text-emerald-700">{{ $fmt((float) data_get($financialOverview, 'operating_margin_pct', 0)) }}% operating margin</p>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8.9 8 10 4.6-1.1 8-5 8-10V7l-8-4Z"></path></svg>
                                </span>
                                <h3 class="text-sm font-semibold text-slate-900">Liquidity &amp; Statutory Shield</h3>
                            </div>
                            <button type="button" @click="openAuditTrail('Liquidity & Statutory Shield')" class="text-slate-400 hover:text-slate-700" aria-label="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Aggregate Cash Position</p>
                        <button type="button" @click="openPeekPanel('Aggregate Cash Position')" class="mt-1 text-left text-4xl font-semibold leading-tight text-blue-700">KSh {{ $fmt((float) data_get($financialOverview, 'aggregate_cash_position', 0)) }}</button>
                        <ul class="mt-4 space-y-1 text-sm text-slate-700">
                            <li class="flex justify-between"><span>Equity Bank (all accounts)</span><span class="font-semibold">KSh {{ $fmt((float) data_get($financialOverview, 'bank_cash_position', 0)) }}</span></li>
                            <li class="flex justify-between"><span>M-Pesa utility (till &amp; float)</span><span class="font-semibold">KSh {{ $fmt((float) data_get($financialOverview, 'mpesa_cash_position', 0)) }}</span></li>
                        </ul>
                        <div class="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800">
                            <p class="font-semibold">Estimated KRA liability: KSh {{ $fmt((float) data_get($financialOverview, 'tax_estimate', 0)) }}</p>
                            <p class="text-xs">Estimated at 8% of net cash surplus.</p>
                        </div>
                        <svg class="mt-4 h-20 w-full" viewBox="0 0 240 80" aria-hidden="true">
                            <path d="M30 60 A50 50 0 0 1 210 60" fill="none" stroke="#cbd5e1" stroke-width="14"></path>
                            <path d="M30 60 A50 50 0 0 1 125 18" fill="none" stroke="#22c55e" stroke-width="14"></path>
                            <path d="M125 18 A50 50 0 0 1 175 32" fill="none" stroke="#facc15" stroke-width="14"></path>
                            <path d="M175 32 A50 50 0 0 1 210 60" fill="none" stroke="#ef4444" stroke-width="14"></path>
                        </svg>
                        <p class="mt-2 text-xs font-semibold text-orange-700">Approaching statutory deadline &amp; minimum cash floors</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 17V7M10 17V9M16 17V5M22 17v-3"></path></svg>
                                </span>
                                <h3 class="text-sm font-semibold text-slate-900">Management Efficiency</h3>
                            </div>
                            <button type="button" @click="openAuditTrail('Management Efficiency')" class="text-slate-400 hover:text-slate-700" aria-label="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Operating Margin</p>
                        <button type="button" @click="openPeekPanel('Operating Margin')" class="mt-1 text-left text-4xl font-semibold leading-tight text-orange-700">{{ $fmt((float) data_get($financialOverview, 'operating_margin_pct', 0)) }}%</button>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><p class="text-slate-500">Cost per client</p><button type="button" @click="openPeekPanel('Cost per Client')" class="font-semibold text-slate-900">KSh {{ $fmt((float) data_get($financialOverview, 'cost_per_client', 0)) }}</button></div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"><p class="text-slate-500">Collection efficiency</p><button type="button" @click="openPeekPanel('Collection Efficiency')" class="font-semibold text-slate-900">{{ $fmt((float) data_get($financialOverview, 'collection_efficiency_pct', 0)) }}%</button></div>
                        </div>
                        <svg class="mt-4 h-20 w-full" viewBox="0 0 320 80" aria-hidden="true">
                            <polyline points="20,55 80,50 140,47 200,40 260,36 300,34" fill="none" stroke="#f97316" stroke-width="3"></polyline>
                            <polyline x-show="compareMode" points="20,60 80,57 140,54 200,50 260,46 300,43" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="4 4"></polyline>
                        </svg>
                    </article>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-base font-semibold text-slate-900">TIER 2: The Statement Vault (2x3 Grid of Report Tiles)</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $report)
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h8l4 4v14H7z"></path><path d="M15 3v4h4"></path></svg>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">{{ $report['title'] }}</h3>
                                        <p class="mt-1 text-xs text-slate-600">{{ $report['description'] }}</p>
                                        @if ($report['title'] === 'Tax / KRA Ledger')
                                            <p class="mt-2 inline-flex items-center rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-800">Next KRA due in 10 days</p>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" @click="openAuditTrail('{{ $report['title'] }}')" class="text-slate-400 hover:text-slate-700" aria-label="Audit history">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                </button>
                            </div>
                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
                                <button type="button" @click="exportPdf('{{ $report['title'] }}')" class="font-semibold text-blue-700 transition hover:text-blue-800">PDF Export</button>
                                <span class="inline-flex items-center gap-1 text-slate-500">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                    Last generated: {{ $report['last'] }}
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <div x-show="peekOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/30" @click="peekOpen = false"></div>
            <aside x-show="peekOpen" class="fixed inset-x-4 top-20 z-50 mx-auto w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-xl sm:inset-x-0">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Drill-down Peek Panel</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900" x-text="peekTitle"></h3>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="peekOpen = false" aria-label="Close panel">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                    </button>
                </div>
            </aside>

            <div x-show="modalOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/45" @click="modalOpen = false"></div>
            <section x-show="modalOpen" class="fixed inset-x-4 top-[12%] z-50 mx-auto w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:inset-x-0">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Quick Access &amp; Templates</p>
                        <h3 class="mt-1 text-xl font-semibold text-slate-900" x-text="modalMode === 'date' ? 'Time Traveler Control' : 'Template Creation System'"></h3>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="modalOpen = false" aria-label="Close modal">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                    </button>
                </div>
                <template x-if="modalMode === 'date'">
                    <div class="space-y-4 text-sm">
                        <input type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800" x-model="dateRange">
                        <div class="flex justify-end"><button type="button" @click="modalOpen = false" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Apply Range</button></div>
                    </div>
                </template>
                <template x-if="modalMode === 'template'">
                    <div class="space-y-4 text-sm">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <select class="rounded-lg border border-slate-300 px-3 py-2"><option>COA Mapping (Debit/Credit)</option></select>
                            <select class="rounded-lg border border-slate-300 px-3 py-2"><option>Governance Rules (Maker-Checker)</option></select>
                            <select class="rounded-lg border border-slate-300 px-3 py-2"><option>Amount Logic (fixed/variable)</option></select>
                            <select class="rounded-lg border border-slate-300 px-3 py-2"><option>Approval Controls</option></select>
                        </div>
                        <div class="flex justify-end"><button type="button" class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Create Template</button></div>
                    </div>
                </template>
            </section>
        </div>
    </x-loan.page>

    <script>
        function financialIntelligencePage() {
            return {
                compareMode: false,
                modalOpen: false,
                modalMode: 'template',
                peekOpen: false,
                peekTitle: 'Net Cash Surplus',
                dateRange: 'April 1 - April 24, 2026',
                openTemplateModal() { this.modalMode = 'template'; this.modalOpen = true; },
                openDateModal() { this.modalMode = 'date'; this.modalOpen = true; },
                openPeekPanel(title) { this.peekTitle = title; this.peekOpen = true; },
                openAuditTrail(title) { this.peekTitle = `${title} Audit Trail`; this.peekOpen = true; },
                exportPdf(title) {
                    const safeTitle = String(title || 'report').replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase();
                    const fileName = `${safeTitle || 'report'}-as-of-${this.dateRange.replace(/\s+/g, '-')}.pdf`;
                    const escapePdf = (value) => String(value || '').replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
                    const lines = [
                        `Report: ${title}`,
                        `Date Range: ${this.dateRange}`,
                        `Generated At: ${new Date().toLocaleString()}`,
                        '',
                        'This export is generated from the Financial Intelligence dashboard.',
                    ];
                    const textOps = lines.map((line, idx) => `1 0 0 1 50 ${760 - (idx * 18)} Tm (${escapePdf(line)}) Tj`).join('\n');
                    const stream = `BT\n/F1 12 Tf\n${textOps}\nET`;
                    const objects = [
                        '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj',
                        '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj',
                        '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj',
                        `4 0 obj\n<< /Length ${stream.length} >>\nstream\n${stream}\nendstream\nendobj`,
                        '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj',
                    ];
                    let pdf = '%PDF-1.4\n';
                    const offsets = [0];
                    objects.forEach((obj) => {
                        offsets.push(pdf.length);
                        pdf += `${obj}\n`;
                    });
                    const xrefOffset = pdf.length;
                    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
                    offsets.slice(1).forEach((offset) => {
                        pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
                    });
                    pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;
                    const blob = new Blob([pdf], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = fileName;
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                },
                closeAllPanels() { this.modalOpen = false; this.peekOpen = false; },
            };
        }
    </script>
</x-loan-layout>
<x-loan-layout>
    <x-loan.page title="Journal Entry Command Center" subtitle="Manage manual journal entries, adjustments, and non-routine cash movements.">
        <div class="min-h-full bg-slate-50 p-3 sm:p-5 lg:p-6" x-data="journalEntryCommandCenter()" @keydown.escape.window="closeTemplateModal()">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Journal Entry Command Center</h1>
                        <p class="mt-1 text-sm text-slate-600">Manage manual journal entries, adjustments, and non-routine cash movements.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 lg:items-end">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <p class="font-medium text-slate-600">Thursday, April 23, 2026</p>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center gap-2 rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v5H4zM4 14h10v5H4zM17 14h3v5h-3"/></svg>
                                Quick Access &amp; Templates
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Create quick access template">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-12">
                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="text-base font-semibold text-slate-900">Templates &amp; Quick Access</h2>
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Personal Favorites</p>
                                <div class="mt-2 space-y-2">
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-sm text-blue-700 transition hover:bg-blue-100">
                                        <span class="truncate">M-Pesa to Bank Transfer</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-sm text-blue-700 transition hover:bg-blue-100">
                                        <span class="truncate">Petty Cash Replenishment</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-purple-100 bg-purple-50/70 px-3 py-2 text-sm text-purple-700 transition hover:bg-purple-100">
                                        <span class="truncate">Director Equity Contribution</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-purple-100 bg-purple-50/70 px-3 py-2 text-sm text-purple-700 transition hover:bg-purple-100">
                                        <span class="truncate">Salary Advance Issuance</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">System Defined</p>
                                <div class="mt-2 space-y-2">
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-blue-700 transition hover:bg-slate-50">
                                        <span class="truncate">Inter-Account Transfer</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-blue-700 transition hover:bg-slate-50">
                                        <span class="truncate">Bank Charges Allocation</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-orange-100 bg-orange-50/70 px-3 py-2 text-sm text-orange-700 transition hover:bg-orange-100">
                                        <span class="truncate">Reversal of Previous Journal</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                </div>
                            </div>
                            <button type="button" class="w-full rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">View Full Posted History</button>
                        </div>
                    </article>
                </aside>

                <main class="space-y-4 xl:col-span-6">
                    <section class="grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Blocked Drafts (The Liquidity Queue)</h3>
                                <svg class="h-4 w-4 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18.2a1.5 1.5 0 0 0 1.3 2.3h17.8a1.5 1.5 0 0 0 1.3-2.3L13.7 3.9a1.5 1.5 0 0 0-3.4 0Z" stroke-linejoin="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">7 Entries</p>
                            <p class="mt-1 text-xs text-orange-700">Violations of Min. Balance Floor rules.</p>
                        </article>
                        <article class="rounded-xl border border-purple-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Approval Queue</h3>
                                <svg class="h-4 w-4 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 5 3.2 8.2 7 10 3.8-1.8 7-5 7-10V6l-7-3Z"/><path d="m9.5 12 2 2 3.5-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">3 Entries</p>
                            <p class="mt-1 text-xs text-purple-700">Awaiting Director authorization</p>
                        </article>
                        <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Drafts &amp; Unposted</h3>
                                <svg class="h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">5 Entries</p>
                            <p class="mt-1 text-xs text-blue-700">Not yet posted to the general ledger</p>
                        </article>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Smart Journal Entry Form</h2>
                                <p class="mt-0.5 text-sm text-slate-600">New Journal Entry</p>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold" :class="requiresApproval ? 'border-purple-200 bg-purple-50 text-purple-700' : 'border-teal-200 bg-teal-50 text-teal-700'">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 5 3.2 8.2 7 10 3.8-1.8 7-5 7-10V6l-7-3Z"/></svg>
                                <span x-text="requiresApproval ? 'DNA Check: Director Approval Required' : 'DNA Check'"></span>
                            </span>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <label class="text-xs font-medium text-slate-600">Date
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="date" x-model="entryDate" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                            <label class="text-xs font-medium text-slate-600">Reference
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="text" placeholder="Reference" x-model="reference" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                            <label class="text-xs font-medium text-slate-600">Description
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="text" placeholder="Enter description of the transaction" x-model="description" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">if applicable based on account rules</p>

                        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-[860px] w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Journal Lines</th>
                                        <th class="px-3 py-2 text-right">Debit (KSh)</th>
                                        <th class="px-3 py-2 text-right">Credit (KSh)</th>
                                        <th class="px-3 py-2">Notes</th>
                                        <th class="px-3 py-2 text-right">Projected Balance (KSh)</th>
                                        <th class="px-3 py-2 text-center">Audit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <template x-for="(line, index) in lines" :key="index">
                                        <tr>
                                            <td class="px-3 py-2">
                                                <select x-model="line.account" @change="onLineChange(index)" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                                    <template x-for="account in accountOptions" :key="account.name"><option :value="account.name" x-text="account.name"></option></template>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" min="0" step="100" x-model.number="line.debit" @input="onLineChange(index)" class="w-full rounded-lg border px-2 py-1.5 text-right text-sm focus:outline-none focus:ring-1" :class="line.belowFloor ? 'border-orange-300 bg-orange-50 focus:border-orange-500 focus:ring-orange-500' : 'border-slate-300 focus:border-teal-700 focus:ring-teal-700'">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" min="0" step="100" x-model.number="line.credit" @input="onLineChange(index)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="text" x-model="line.notes" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-700 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                            </td>
                                            <td class="px-3 py-2 text-right font-semibold" :class="line.belowFloor ? 'text-orange-600' : 'text-emerald-700'" x-text="formatKsh(line.projectedBalance)"></td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center justify-center gap-2 text-slate-500">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                                    <button type="button" @click="removeLine(index)" class="rounded p-1 hover:bg-slate-100" title="Remove line"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 5 14 14M19 5 5 19" stroke-linecap="round"/></svg></button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-slate-50 text-sm font-semibold text-slate-700">
                                    <tr>
                                        <td class="px-3 py-2">
                                            <button type="button" @click="addLine()" class="inline-flex items-center gap-2 rounded-md border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                                                Add Journal Line
                                            </button>
                                        </td>
                                        <td class="px-3 py-2 text-right" x-text="formatKsh(totalDebit)"></td>
                                        <td class="px-3 py-2 text-right" x-text="formatKsh(totalCredit)"></td>
                                        <td class="px-3 py-2"></td><td class="px-3 py-2 text-right"></td><td class="px-3 py-2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <span x-show="attachmentRequired" class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 7v10a4 4 0 0 1-8 0V6a3 3 0 0 1 6 0v10a2 2 0 0 1-4 0V8" stroke-linecap="round"/></svg>
                                Attachment Required
                            </span>
                            <span x-show="hasFloorViolation" class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700" x-text="guardrailMessage"></span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" x-show="!hasFloorViolation && !requiresApproval" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Post Transaction</button>
                            <button type="button" x-show="!hasFloorViolation && requiresApproval" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700">Submit for Approval</button>
                            <button type="button" x-show="hasFloorViolation" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">Save as Blocked Draft</button>
                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Save as Draft</button>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">When a high-value account is selected, or the amount pushes the projected balance below the COA floor, the amount field highlights in orange.</p>
                    </section>
                </main>

                <aside class="xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold text-slate-900">Last 15 Journal Activities</h2>
                            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                        </div>
                        <div class="mt-3 max-h-[42rem] space-y-2 overflow-y-auto pr-1">
                            <template x-for="(activity, index) in activities" :key="index">
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-xs text-slate-500" x-text="activity.time"></p>
                                            <p class="mt-1 text-sm font-semibold text-slate-800" x-text="activity.title"></p>
                                            <p class="text-xs text-slate-600" x-text="activity.ref"></p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" x-show="activity.status === 'Posted'" class="rounded p-1 text-orange-500 hover:bg-orange-50" title="Reverse"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 5v6h6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                            <button type="button" x-show="activity.status === 'Blocked Draft'" class="rounded p-1 text-blue-600 hover:bg-blue-50" title="Retry / Top-up"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12a8 8 0 0 1 14.6-4.4"/><path d="M20 12a8 8 0 0 1-14.6 4.4"/><path d="M19 4v5h-5M5 20v-5h5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-700" x-text="activity.amount"></span>
                                        <span class="rounded-full border px-2 py-0.5 text-xs font-semibold" :class="activity.status === 'Posted' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : (activity.status === 'Pending Approval' ? 'border-purple-200 bg-purple-50 text-purple-700' : 'border-orange-200 bg-orange-50 text-orange-700')" x-text="activity.status"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </article>
                </aside>
            </section>

            <div x-show="showTemplateModal" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50" @click="closeTemplateModal()"></div>
            <section x-show="showTemplateModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-xl" @click.stop role="dialog" aria-modal="true">
                    <header class="flex items-start justify-between border-b border-slate-200 p-4 sm:p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Config Popup</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Create New Quick Access Template</h3>
                        </div>
                        <button type="button" @click="closeTemplateModal()" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Close modal"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg></button>
                    </header>
                    <div class="max-h-[72vh] space-y-4 overflow-y-auto p-4 sm:p-5">
                        <section class="rounded-xl border border-slate-200 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Template Details</h4>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600">Template Name<input type="text" x-model="templateForm.name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700"></label>
                                <label class="text-xs font-medium text-slate-600">Category<select x-model="templateForm.category" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700"><option>Personal</option><option>System</option></select></label>
                                <label class="sm:col-span-2 text-xs font-medium text-slate-600">Description<input type="text" x-model="templateForm.description" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700"></label>
                            </div>
                            <button type="button" class="mt-3 text-xs font-semibold text-blue-600 hover:text-blue-700">View / Manage All</button>
                        </section>
                        <section class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-900">Accounting DNA Mappings (CPA-level fields)</h4>
                                <span class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700">DNA-checked</span>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600">Debit Account (COA)<select x-model="templateForm.debitAccount" @change="validateTemplateAccess()" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700"><template x-for="account in coaOptions" :key="'d-'+account"><option :value="account" x-text="account"></option></template></select></label>
                                <label class="text-xs font-medium text-slate-600">Credit Account (COA)<select x-model="templateForm.creditAccount" @change="validateTemplateAccess()" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700"><template x-for="account in coaOptions" :key="'c-'+account"><option :value="account" x-text="account"></option></template></select></label>
                                <label class="text-xs font-medium text-slate-600">Reference Prefix<input type="text" x-model="templateForm.referencePrefix" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700"></label>
                                <fieldset class="text-xs font-medium text-slate-600"><legend>Amount Type</legend><div class="mt-2 flex items-center gap-4 text-sm"><label class="inline-flex items-center gap-1.5"><input type="radio" name="amountType" value="Fixed Amount" x-model="templateForm.amountType" class="text-blue-600 focus:ring-blue-500">Fixed Amount</label><label class="inline-flex items-center gap-1.5"><input type="radio" name="amountType" value="Variable Amount" x-model="templateForm.amountType" class="text-blue-600 focus:ring-blue-500">Variable Amount</label></div></fieldset>
                            </div>
                            <div x-show="templateAccessRestricted" class="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700">You are not authorized to create templates for this account.<span class="ml-2 inline-flex rounded-full border border-orange-300 bg-white px-2 py-0.5 text-[11px] font-bold">Access Restricted</span></div>
                        </section>
                        <section class="rounded-xl border border-slate-200 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Governance &amp; Rules Builder</h4>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><span>Requires Dual Authorization (Maker-Checker)</span><input type="checkbox" x-model="templateForm.dualAuthorization" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"></label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><span>Requires Mandatory Cost Center Tag</span><input type="checkbox" x-model="templateForm.costCenterRequired" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"></label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><span>Requires Mandatory Client / Loan ID</span><input type="checkbox" x-model="templateForm.clientLoanRequired" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"></label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"><span>Enforce Transaction Limit</span><input type="checkbox" x-model="templateForm.limitEnforced" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"></label>
                                <label class="text-xs font-medium text-slate-600">Authorized Approver / Checker<select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700"><option>Finance Director</option><option>Head of Finance</option></select></label>
                                <label class="text-xs font-medium text-slate-600">Maker Role<select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700"><option>Accountant</option><option>Branch Operations</option></select></label>
                                <label class="text-xs font-medium text-slate-600 sm:col-span-2">Maximum amount input<input type="number" min="0" x-model.number="templateForm.maxAmount" class="mt-1 w-full rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-900 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"></label>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
                                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">Active</span><span class="rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-slate-700">History</span><span class="rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-orange-700">Access Restricted</span><span class="rounded-full border border-purple-200 bg-purple-50 px-2.5 py-1 text-purple-700">COA Rules Applied</span>
                            </div>
                        </section>
                    </div>
                    <footer class="flex items-center justify-between gap-2 border-t border-slate-200 p-4 sm:p-5">
                        <div class="inline-flex rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Active (COA Rules Applied)</div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="closeTemplateModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" :disabled="templateAccessRestricted" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300">Create Template</button>
                        </div>
                    </footer>
                </div>
            </section>
        </div>
    </x-loan.page>
    <script>
        function journalEntryCommandCenter() {
            return {
                showTemplateModal: false,
                entryDate: '2026-04-23',
                reference: '',
                description: '',
                accountOptions: [{ name: 'M-Pesa Utility', balance: 52000, floor: 5000 }, { name: 'Cash Account', balance: 9500, floor: 5000 }, { name: 'Equity Bank', balance: 120000, floor: 10000 }, { name: 'Director Reserve Account', balance: 250000, floor: 30000 }],
                lines: [{ account: 'M-Pesa Utility', debit: 5000, credit: 0, notes: 'Transfer from M-Pesa', projectedBalance: 52000, belowFloor: false }, { account: 'Cash Account', debit: 0, credit: 5000, notes: 'Cash deposit', projectedBalance: 4500, belowFloor: true }, { account: 'Equity Bank', debit: 0, credit: 0, notes: '', projectedBalance: 120000, belowFloor: false }],
                activities: [{ time: '10:45 AM', title: 'KSh 1.2M M-Pesa to Bank', amount: 'Ref: JE-000245', ref: 'Posted', status: 'Posted' }, { time: '10:20 AM', title: 'Petty Cash Replenishment', amount: 'Ref: JE-000244', ref: 'Posted', status: 'Posted' }, { time: '09:55 AM', title: 'Salary Payment Adjustment', amount: 'Ref: JE-000243', ref: 'Pending Approval', status: 'Pending Approval' }, { time: '09:30 AM', title: 'Cash Deposit to Bank', amount: 'Ref: JE-000242', ref: 'Posted', status: 'Posted' }, { time: '09:05 AM', title: 'Loan Recovery Reversal', amount: 'Ref: JE-000241', ref: 'Blocked Draft', status: 'Blocked Draft' }, { time: '08:40 AM', title: 'Bank Charge Allocation', amount: 'Ref: JE-000240', ref: 'Posted', status: 'Posted' }, { time: '08:15 AM', title: 'Suspense Account Clearance', amount: 'Ref: JE-000239', ref: 'Pending Approval', status: 'Pending Approval' }],
                coaOptions: ['M-Pesa Utility', 'Cash Account', 'Equity Bank', 'Director Reserve Account'],
                templateForm: { name: '', description: '', category: 'Personal', debitAccount: 'M-Pesa Utility', creditAccount: 'Cash Account', referencePrefix: 'RENT-', amountType: 'Variable Amount', dualAuthorization: true, costCenterRequired: true, clientLoanRequired: false, limitEnforced: true, maxAmount: 100000 },
                templateAccessRestricted: false,
                openTemplateModal() { this.showTemplateModal = true; this.validateTemplateAccess(); },
                closeTemplateModal() { this.showTemplateModal = false; },
                formatKsh(value) { return `KSh ${new Intl.NumberFormat().format(Number(value || 0))}`; },
                onLineChange(index) { const line = this.lines[index]; const account = this.accountOptions.find((item) => item.name === line.account); if (!account) return; line.projectedBalance = Number(account.balance) + Number(line.debit || 0) - Number(line.credit || 0); line.belowFloor = line.projectedBalance < Number(account.floor); },
                addLine() { this.lines.push({ account: 'M-Pesa Utility', debit: 0, credit: 0, notes: '', projectedBalance: 52000, belowFloor: false }); },
                removeLine(index) { if (this.lines.length > 1) this.lines.splice(index, 1); },
                validateTemplateAccess() { this.templateAccessRestricted = this.templateForm.debitAccount === 'Director Reserve Account' || this.templateForm.creditAccount === 'Director Reserve Account'; },
                get totalDebit() { return this.lines.reduce((sum, row) => sum + Number(row.debit || 0), 0); },
                get totalCredit() { return this.lines.reduce((sum, row) => sum + Number(row.credit || 0), 0); },
                get hasFloorViolation() { return this.lines.some((row) => row.belowFloor); },
                get requiresApproval() { return this.lines.some((row) => row.account === 'Director Reserve Account') || this.totalDebit >= 500000; },
                get attachmentRequired() { return this.requiresApproval || this.totalDebit >= 100000; },
                get guardrailMessage() { const violating = this.lines.find((row) => row.belowFloor); if (!violating) return ''; const account = this.accountOptions.find((item) => item.name === violating.account); return `Projected Balance: ${this.formatKsh(violating.projectedBalance)} (Below COA Floor: ${this.formatKsh(account ? account.floor : 0)})`; },
                init() { this.lines.forEach((_, index) => this.onLineChange(index)); this.validateTemplateAccess(); },
            };
        }
    </script>
</x-loan-layout>
<x-loan-layout>
    <x-loan.page title="Journal Entry Command Center" subtitle="Manage manual journal entries, adjustments, and non-routine cash movements.">
        <div class="min-h-full bg-slate-50 p-3 sm:p-5 lg:p-6" x-data="journalEntryCommandCenter()" @keydown.escape.window="closeTemplateModal()">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Journal Entry Command Center</h1>
                        <p class="mt-1 text-sm text-slate-600">Manage manual journal entries, adjustments, and non-routine cash movements.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 lg:items-end">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <p class="font-medium text-slate-600">Thursday, April 23, 2026</p>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center gap-2 rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 5h16v5H4zM4 14h10v5H4zM17 14h3v5h-3"/>
                                </svg>
                                Quick Access &amp; Templates
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Create quick access template">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-12">
                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="text-base font-semibold text-slate-900">Templates &amp; Quick Access</h2>
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Personal Favorites</p>
                                <div class="mt-2 space-y-2">
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-sm text-blue-700 transition hover:bg-blue-100">
                                        <span class="truncate">M-Pesa to Bank Transfer</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-sm text-blue-700 transition hover:bg-blue-100">
                                        <span class="truncate">Petty Cash Replenishment</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-purple-100 bg-purple-50/70 px-3 py-2 text-sm text-purple-700 transition hover:bg-purple-100">
                                        <span class="truncate">Director Equity Contribution</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-purple-100 bg-purple-50/70 px-3 py-2 text-sm text-purple-700 transition hover:bg-purple-100">
                                        <span class="truncate">Salary Advance Issuance</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">System Defined</p>
                                <div class="mt-2 space-y-2">
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-blue-700 transition hover:bg-slate-50">
                                        <span class="truncate">Inter-Account Transfer</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-blue-700 transition hover:bg-slate-50">
                                        <span class="truncate">Bank Charges Allocation</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-orange-100 bg-orange-50/70 px-3 py-2 text-sm text-orange-700 transition hover:bg-orange-100">
                                        <span class="truncate">Reversal of Previous Journal</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                </div>
                            </div>
                            <button type="button" class="w-full rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">View Full Posted History</button>
                        </div>
                    </article>
                </aside>

                <main class="space-y-4 xl:col-span-6">
                    <section class="grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Blocked Drafts (The Liquidity Queue)</h3>
                                <svg class="h-4 w-4 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18.2a1.5 1.5 0 0 0 1.3 2.3h17.8a1.5 1.5 0 0 0 1.3-2.3L13.7 3.9a1.5 1.5 0 0 0-3.4 0Z" stroke-linejoin="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">7 Entries</p>
                            <p class="mt-1 text-xs text-orange-700">Violations of Min. Balance Floor rules.</p>
                        </article>
                        <article class="rounded-xl border border-purple-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Approval Queue</h3>
                                <svg class="h-4 w-4 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 5 3.2 8.2 7 10 3.8-1.8 7-5 7-10V6l-7-3Z"/><path d="m9.5 12 2 2 3.5-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">3 Entries</p>
                            <p class="mt-1 text-xs text-purple-700">Awaiting Director authorization</p>
                        </article>
                        <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Drafts &amp; Unposted</h3>
                                <svg class="h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">5 Entries</p>
                            <p class="mt-1 text-xs text-blue-700">Not yet posted to the general ledger</p>
                        </article>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Smart Journal Entry Form</h2>
                                <p class="mt-0.5 text-sm text-slate-600">New Journal Entry</p>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold" :class="requiresApproval ? 'border-purple-200 bg-purple-50 text-purple-700' : 'border-teal-200 bg-teal-50 text-teal-700'">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 5 3.2 8.2 7 10 3.8-1.8 7-5 7-10V6l-7-3Z"/></svg>
                                <span x-text="requiresApproval ? 'DNA Check: Director Approval Required' : 'DNA Check'"></span>
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <label class="text-xs font-medium text-slate-600">Date
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="date" x-model="entryDate" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                            <label class="text-xs font-medium text-slate-600">Reference
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="text" placeholder="Reference" x-model="reference" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                            <label class="text-xs font-medium text-slate-600">Description
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="text" placeholder="Enter description of the transaction" x-model="description" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">if applicable based on account rules</p>

                        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-[860px] w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Journal Lines</th>
                                        <th class="px-3 py-2 text-right">Debit (KSh)</th>
                                        <th class="px-3 py-2 text-right">Credit (KSh)</th>
                                        <th class="px-3 py-2">Notes</th>
                                        <th class="px-3 py-2 text-right">Projected Balance (KSh)</th>
                                        <th class="px-3 py-2 text-center">Audit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <template x-for="(line, index) in lines" :key="index">
                                        <tr>
                                            <td class="px-3 py-2">
                                                <select x-model="line.account" @change="onLineChange(index)" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                                    <template x-for="account in accountOptions" :key="account.name">
                                                        <option :value="account.name" x-text="account.name"></option>
                                                    </template>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" min="0" step="100" x-model.number="line.debit" @input="onLineChange(index)" class="w-full rounded-lg border px-2 py-1.5 text-right text-sm focus:outline-none focus:ring-1" :class="line.belowFloor ? 'border-orange-300 bg-orange-50 focus:border-orange-500 focus:ring-orange-500' : 'border-slate-300 focus:border-teal-700 focus:ring-teal-700'">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" min="0" step="100" x-model.number="line.credit" @input="onLineChange(index)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="text" x-model="line.notes" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-700 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                            </td>
                                            <td class="px-3 py-2 text-right font-semibold" :class="line.belowFloor ? 'text-orange-600' : 'text-emerald-700'" x-text="formatKsh(line.projectedBalance)"></td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center justify-center gap-2 text-slate-500">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                                    <button type="button" @click="removeLine(index)" class="rounded p-1 hover:bg-slate-100" title="Remove line">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 5 14 14M19 5 5 19" stroke-linecap="round"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-slate-50 text-sm font-semibold text-slate-700">
                                    <tr>
                                        <td class="px-3 py-2">
                                            <button type="button" @click="addLine()" class="inline-flex items-center gap-2 rounded-md border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                                                Add Journal Line
                                            </button>
                                        </td>
                                        <td class="px-3 py-2 text-right" x-text="formatKsh(totalDebit)"></td>
                                        <td class="px-3 py-2 text-right" x-text="formatKsh(totalCredit)"></td>
                                        <td class="px-3 py-2"></td>
                                        <td class="px-3 py-2 text-right"></td>
                                        <td class="px-3 py-2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <span x-show="attachmentRequired" class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 7v10a4 4 0 0 1-8 0V6a3 3 0 0 1 6 0v10a2 2 0 0 1-4 0V8" stroke-linecap="round"/></svg>
                                Attachment Required
                            </span>
                            <span x-show="hasFloorViolation" class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700" x-text="guardrailMessage"></span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" x-show="!hasFloorViolation && !requiresApproval" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Post Transaction</button>
                            <button type="button" x-show="!hasFloorViolation && requiresApproval" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700">Submit for Approval</button>
                            <button type="button" x-show="hasFloorViolation" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">Save as Blocked Draft</button>
                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Save as Draft</button>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">When a high-value account is selected, or the amount pushes the projected balance below the COA floor, the amount field highlights in orange.</p>
                    </section>
                </main>

                <aside class="xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold text-slate-900">Last 15 Journal Activities</h2>
                            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                        </div>
                        <div class="mt-3 max-h-[42rem] space-y-2 overflow-y-auto pr-1">
                            <template x-for="(activity, index) in activities" :key="index">
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-xs text-slate-500" x-text="activity.time"></p>
                                            <p class="mt-1 text-sm font-semibold text-slate-800" x-text="activity.title"></p>
                                            <p class="text-xs text-slate-600" x-text="activity.ref"></p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" x-show="activity.status === 'Posted'" class="rounded p-1 text-orange-500 hover:bg-orange-50" title="Reverse">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 5v6h6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                            <button type="button" x-show="activity.status === 'Blocked Draft'" class="rounded p-1 text-blue-600 hover:bg-blue-50" title="Retry / Top-up">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12a8 8 0 0 1 14.6-4.4"/><path d="M20 12a8 8 0 0 1-14.6 4.4"/><path d="M19 4v5h-5M5 20v-5h5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-700" x-text="activity.amount"></span>
                                        <span class="rounded-full border px-2 py-0.5 text-xs font-semibold"
                                            :class="activity.status === 'Posted' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : (activity.status === 'Pending Approval' ? 'border-purple-200 bg-purple-50 text-purple-700' : 'border-orange-200 bg-orange-50 text-orange-700')"
                                            x-text="activity.status">
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </article>
                </aside>
            </section>

            <div x-show="showTemplateModal" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50" @click="closeTemplateModal()"></div>
            <section x-show="showTemplateModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-xl" @click.stop role="dialog" aria-modal="true">
                    <header class="flex items-start justify-between border-b border-slate-200 p-4 sm:p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Config Popup</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Create New Quick Access Template</h3>
                        </div>
                        <button type="button" @click="closeTemplateModal()" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Close modal">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
                        </button>
                    </header>

                    <div class="max-h-[72vh] space-y-4 overflow-y-auto p-4 sm:p-5">
                        <section class="rounded-xl border border-slate-200 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Template Details</h4>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600">Template Name
                                    <input type="text" x-model="templateForm.name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                </label>
                                <label class="text-xs font-medium text-slate-600">Category
                                    <select x-model="templateForm.category" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                        <option>Personal</option>
                                        <option>System</option>
                                    </select>
                                </label>
                                <label class="sm:col-span-2 text-xs font-medium text-slate-600">Description
                                    <input type="text" x-model="templateForm.description" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                </label>
                            </div>
                            <button type="button" class="mt-3 text-xs font-semibold text-blue-600 hover:text-blue-700">View / Manage All</button>
                        </section>

                        <section class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-900">Accounting DNA Mappings (CPA-level fields)</h4>
                                <span class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700">DNA-checked</span>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600">Debit Account (COA)
                                    <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 px-2 py-1.5">
                                        <select x-model="templateForm.debitAccount" @change="validateTemplateAccess()" class="w-full border-0 bg-transparent text-sm text-slate-800 focus:ring-0">
                                            <template x-for="account in coaOptions" :key="'d-'+account">
                                                <option :value="account" x-text="account"></option>
                                            </template>
                                        </select>
                                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </div>
                                </label>
                                <label class="text-xs font-medium text-slate-600">Credit Account (COA)
                                    <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 px-2 py-1.5">
                                        <select x-model="templateForm.creditAccount" @change="validateTemplateAccess()" class="w-full border-0 bg-transparent text-sm text-slate-800 focus:ring-0">
                                            <template x-for="account in coaOptions" :key="'c-'+account">
                                                <option :value="account" x-text="account"></option>
                                            </template>
                                        </select>
                                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </div>
                                </label>
                                <label class="text-xs font-medium text-slate-600">Reference Prefix
                                    <input type="text" x-model="templateForm.referencePrefix" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                </label>
                                <fieldset class="text-xs font-medium text-slate-600">
                                    <legend>Amount Type</legend>
                                    <div class="mt-2 flex items-center gap-4 text-sm">
                                        <label class="inline-flex items-center gap-1.5">
                                            <input type="radio" name="amountType" value="Fixed Amount" x-model="templateForm.amountType" class="text-blue-600 focus:ring-blue-500">
                                            Fixed Amount
                                        </label>
                                        <label class="inline-flex items-center gap-1.5">
                                            <input type="radio" name="amountType" value="Variable Amount" x-model="templateForm.amountType" class="text-blue-600 focus:ring-blue-500">
                                            Variable Amount
                                        </label>
                                    </div>
                                </fieldset>
                            </div>
                            <div x-show="templateAccessRestricted" class="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700">
                                You are not authorized to create templates for this account.
                                <span class="ml-2 inline-flex rounded-full border border-orange-300 bg-white px-2 py-0.5 text-[11px] font-bold">Access Restricted</span>
                            </div>
                        </section>

                        <section class="rounded-xl border border-slate-200 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Governance &amp; Rules Builder</h4>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <span>Requires Dual Authorization (Maker-Checker)</span>
                                    <input type="checkbox" x-model="templateForm.dualAuthorization" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <span>Requires Mandatory Cost Center Tag</span>
                                    <input type="checkbox" x-model="templateForm.costCenterRequired" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <span>Requires Mandatory Client / Loan ID</span>
                                    <input type="checkbox" x-model="templateForm.clientLoanRequired" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <span>Enforce Transaction Limit</span>
                                    <input type="checkbox" x-model="templateForm.limitEnforced" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="text-xs font-medium text-slate-600">Authorized Approver / Checker
                                    <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                        <option>Finance Director</option>
                                        <option>Head of Finance</option>
                                    </select>
                                </label>
                                <label class="text-xs font-medium text-slate-600">Maker Role
                                    <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                        <option>Accountant</option>
                                        <option>Branch Operations</option>
                                    </select>
                                </label>
                                <label class="text-xs font-medium text-slate-600 sm:col-span-2">Maximum amount input
                                    <input type="number" min="0" x-model.number="templateForm.maxAmount" class="mt-1 w-full rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-900 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500">
                                </label>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
                                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">Active</span>
                                <span class="rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-slate-700">History</span>
                                <span class="rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-orange-700">Access Restricted</span>
                                <span class="rounded-full border border-purple-200 bg-purple-50 px-2.5 py-1 text-purple-700">COA Rules Applied</span>
                            </div>
                        </section>
                    </div>

                    <footer class="flex items-center justify-between gap-2 border-t border-slate-200 p-4 sm:p-5">
                        <div class="inline-flex rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Active (COA Rules Applied)</div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="closeTemplateModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" :disabled="templateAccessRestricted" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300">Create Template</button>
                        </div>
                    </footer>
                </div>
            </section>
        </div>
    </x-loan.page>

    <script>
        function journalEntryCommandCenter() {
            return {
                showTemplateModal: false,
                entryDate: '2026-04-23',
                reference: '',
                description: '',
                accountOptions: [
                    { name: 'M-Pesa Utility', balance: 52000, floor: 5000 },
                    { name: 'Cash Account', balance: 9500, floor: 5000 },
                    { name: 'Equity Bank', balance: 120000, floor: 10000 },
                    { name: 'Director Reserve Account', balance: 250000, floor: 30000 },
                ],
                lines: [
                    { account: 'M-Pesa Utility', debit: 5000, credit: 0, notes: 'Transfer from M-Pesa', projectedBalance: 52000, belowFloor: false },
                    { account: 'Cash Account', debit: 0, credit: 5000, notes: 'Cash deposit', projectedBalance: 4500, belowFloor: true },
                    { account: 'Equity Bank', debit: 0, credit: 0, notes: '', projectedBalance: 120000, belowFloor: false },
                ],
                activities: [
                    { time: '10:45 AM', title: 'KSh 1.2M M-Pesa to Bank', amount: 'Ref: JE-000245', ref: 'Posted', status: 'Posted' },
                    { time: '10:20 AM', title: 'Petty Cash Replenishment', amount: 'Ref: JE-000244', ref: 'Posted', status: 'Posted' },
                    { time: '09:55 AM', title: 'Salary Payment Adjustment', amount: 'Ref: JE-000243', ref: 'Pending Approval', status: 'Pending Approval' },
                    { time: '09:30 AM', title: 'Cash Deposit to Bank', amount: 'Ref: JE-000242', ref: 'Posted', status: 'Posted' },
                    { time: '09:05 AM', title: 'Loan Recovery Reversal', amount: 'Ref: JE-000241', ref: 'Blocked Draft', status: 'Blocked Draft' },
                    { time: '08:40 AM', title: 'Bank Charge Allocation', amount: 'Ref: JE-000240', ref: 'Posted', status: 'Posted' },
                    { time: '08:15 AM', title: 'Suspense Account Clearance', amount: 'Ref: JE-000239', ref: 'Pending Approval', status: 'Pending Approval' },
                ],
                coaOptions: ['M-Pesa Utility', 'Cash Account', 'Equity Bank', 'Director Reserve Account'],
                templateForm: {
                    name: '',
                    description: '',
                    category: 'Personal',
                    debitAccount: 'M-Pesa Utility',
                    creditAccount: 'Cash Account',
                    referencePrefix: 'RENT-',
                    amountType: 'Variable Amount',
                    dualAuthorization: true,
                    costCenterRequired: true,
                    clientLoanRequired: false,
                    limitEnforced: true,
                    maxAmount: 100000,
                },
                templateAccessRestricted: false,
                openTemplateModal() {
                    this.showTemplateModal = true;
                    this.validateTemplateAccess();
                },
                closeTemplateModal() {
                    this.showTemplateModal = false;
                },
                formatKsh(value) {
                    return `KSh ${new Intl.NumberFormat().format(Number(value || 0))}`;
                },
                onLineChange(index) {
                    const line = this.lines[index];
                    const account = this.accountOptions.find((item) => item.name === line.account);
                    if (!account) return;
                    line.projectedBalance = Number(account.balance) + Number(line.debit || 0) - Number(line.credit || 0);
                    line.belowFloor = line.projectedBalance < Number(account.floor);
                },
                addLine() {
                    this.lines.push({ account: 'M-Pesa Utility', debit: 0, credit: 0, notes: '', projectedBalance: 52000, belowFloor: false });
                },
                removeLine(index) {
                    if (this.lines.length > 1) this.lines.splice(index, 1);
                },
                validateTemplateAccess() {
                    const restricted = this.templateForm.debitAccount === 'Director Reserve Account' || this.templateForm.creditAccount === 'Director Reserve Account';
                    this.templateAccessRestricted = restricted;
                },
                get totalDebit() {
                    return this.lines.reduce((sum, row) => sum + Number(row.debit || 0), 0);
                },
                get totalCredit() {
                    return this.lines.reduce((sum, row) => sum + Number(row.credit || 0), 0);
                },
                get hasFloorViolation() {
                    return this.lines.some((row) => row.belowFloor);
                },
                get requiresApproval() {
                    return this.lines.some((row) => row.account === 'Director Reserve Account') || this.totalDebit >= 500000;
                },
                get attachmentRequired() {
                    return this.requiresApproval || this.totalDebit >= 100000;
                },
                get guardrailMessage() {
                    const violating = this.lines.find((row) => row.belowFloor);
                    if (!violating) return '';
                    const account = this.accountOptions.find((item) => item.name === violating.account);
                    return `Projected Balance: ${this.formatKsh(violating.projectedBalance)} (Below COA Floor: ${this.formatKsh(account ? account.floor : 0)})`;
                },
                init() {
                    this.lines.forEach((_, index) => this.onLineChange(index));
                    this.validateTemplateAccess();
                },
            };
        }
    </script>
</x-loan-layout>
@php
    $fmt = fn (int|float $n) => number_format((float) $n, 2);
    $reports = [
        ['title' => 'Income Statement (P&L)', 'description' => 'Analyze performance with realized revenue streams (interest vs. processing fees).', 'last' => '5 mins ago'],
        ['title' => 'Balance Sheet', 'description' => 'Real-time statement of financial position (focus: net loan portfolio and equity).', 'last' => '10 mins ago'],
        ['title' => 'Trial Balance', 'description' => 'Auditor view of debit/credit integrity across all cash-basis accounts.', 'last' => '15 mins ago'],
        ['title' => 'Cash Flow Statement', 'description' => 'Map cash movement between M-Pesa utility and main bank accounts.', 'last' => '30 mins ago'],
        ['title' => 'Tax / KRA Ledger', 'description' => 'Summarize PAYE, VAT, NSSF, NHIF, and SHIF obligations for the period.', 'last' => '40 mins ago'],
        ['title' => 'Management Report', 'description' => 'Consolidated financial and operational metrics for leadership review.', 'last' => '1 hour ago'],
    ];
@endphp

<x-loan-layout>
    <x-loan.page title="Books of Account" subtitle="Financial intelligence command context for cash-basis executive oversight.">
        <div class="space-y-6" x-data="financialIntelligencePage()" @keydown.escape.window="closeAllPanels()">
            <section class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Real-Time Financial Intelligence (Cash-Basis: Fortress Lenders)</h1>
                        <p class="mt-1 text-sm text-slate-600">Real-time visibility into liquidity, performance, and compliance.</p>
                    </div>
                    <div class="space-y-3 xl:text-right">
                        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                                    <path d="M8 3v4M16 3v4M3 10h18"></path>
                                </svg>
                                {{ now()->format('l, F j, Y') }}
                            </span>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row xl:justify-end">
                            <button
                                type="button"
                                @click="openDateModal()"
                                class="inline-flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:border-teal-200 hover:bg-teal-50/40"
                            >
                                <span>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-teal-700">Time Traveler</span>
                                    <span class="block font-semibold text-slate-900" x-text="dateRange"></span>
                                    <span class="block text-xs text-slate-500">Real-time data synced to this range</span>
                                </span>
                                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"></path></svg>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">Quick Access &amp; Templates</button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Create template">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">TIER 1 (Top): The "Real-Time Profitability" Dashboard</h2>
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                        <input type="checkbox" x-model="compareMode" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Compare (Prev. Month/Year)
                    </label>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10M10 20V4M16 20v-6M22 20v-9"></path></svg>
                                </span>
                                <h3 class="text-sm font-semibold text-slate-900">Real-Time Income Statement</h3>
                            </div>
                            <button type="button" @click="openAuditTrail('Real-Time Income Statement')" class="text-slate-400 transition hover:text-slate-700" aria-label="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Net Cash Surplus</p>
                        <button type="button" @click="openPeekPanel('Net Cash Surplus')" class="mt-1 text-left text-4xl font-semibold leading-tight text-emerald-700">KSh {{ $fmt(3250300.20) }}</button>
                        <p class="text-sm text-slate-500">(Revenue - OPEX)</p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2">
                                <p class="font-semibold text-emerald-700">Total Cash Collected</p>
                                <button type="button" @click="openPeekPanel('Total Cash Collected')" class="mt-1 text-sm font-semibold text-slate-900">KSh {{ $fmt(1650000) }}</button>
                                <p class="mt-1 text-slate-600">Interest: KSh 1.2M<br>Processing fees: KSh 450k</p>
                            </div>
                            <div class="rounded-lg bg-orange-50 px-3 py-2">
                                <p class="font-semibold text-orange-700">Total Cash Spent</p>
                                <button type="button" @click="openPeekPanel('Total Cash Spent')" class="mt-1 text-sm font-semibold text-slate-900">KSh {{ $fmt(1349699.80) }}</button>
                                <p class="mt-1 text-slate-600">OPEX: KSh 300k<br>Salaries &amp; welfare: KSh 1.1M</p>
                            </div>
                        </div>
                        <svg class="mt-4 h-20 w-full" viewBox="0 0 360 80" aria-hidden="true">
                            <rect x="40" y="20" width="100" height="46" rx="6" fill="#14b8a6"></rect>
                            <rect x="210" y="29" width="100" height="37" rx="6" fill="#f97316"></rect>
                            <text x="90" y="15" text-anchor="middle" fill="#0f172a" font-size="10">1.65M</text>
                            <text x="260" y="24" text-anchor="middle" fill="#0f172a" font-size="10">1.35M</text>
                            <text x="90" y="76" text-anchor="middle" fill="#475569" font-size="10">Cash Revenue</text>
                            <text x="260" y="76" text-anchor="middle" fill="#475569" font-size="10">Cash OPEX</text>
                        </svg>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Cash-Basis: Realized Income Only</span>
                            <p class="text-xs font-semibold text-emerald-700">12% higher than March 2026</p>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8.9 8 10 4.6-1.1 8-5 8-10V7l-8-4Z"></path></svg>
                                </span>
                                <h3 class="text-sm font-semibold text-slate-900">Liquidity &amp; Statutory Shield</h3>
                            </div>
                            <button type="button" @click="openAuditTrail('Liquidity & Statutory Shield')" class="text-slate-400 transition hover:text-slate-700" aria-label="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Aggregate Cash Position</p>
                        <button type="button" @click="openPeekPanel('Aggregate Cash Position')" class="mt-1 text-left text-4xl font-semibold leading-tight text-blue-700">KSh {{ $fmt(4120000) }}</button>
                        <p class="text-sm text-slate-500">(Combined in all accounts)</p>
                        <ul class="mt-4 space-y-1 text-sm text-slate-700">
                            <li class="flex justify-between"><span>Equity Bank (all accounts)</span><span class="font-semibold">KSh 2.90M</span></li>
                            <li class="flex justify-between"><span>M-Pesa utility (till &amp; float)</span><span class="font-semibold">KSh 1.20M</span></li>
                        </ul>
                        <div class="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800">
                            <p class="font-semibold">Estimated KRA liability: KSh 240,000</p>
                            <p class="text-xs">PAYE: KSh 80k · VAT: KSh 110k · Corporate tax (est.): KSh 50k</p>
                        </div>
                        <svg class="mt-4 h-20 w-full" viewBox="0 0 240 80" aria-hidden="true">
                            <path d="M30 60 A50 50 0 0 1 210 60" fill="none" stroke="#cbd5e1" stroke-width="14"></path>
                            <path d="M30 60 A50 50 0 0 1 125 18" fill="none" stroke="#22c55e" stroke-width="14"></path>
                            <path d="M125 18 A50 50 0 0 1 175 32" fill="none" stroke="#facc15" stroke-width="14"></path>
                            <path d="M175 32 A50 50 0 0 1 210 60" fill="none" stroke="#ef4444" stroke-width="14"></path>
                            <text x="120" y="76" text-anchor="middle" fill="#334155" font-size="10">Safety Gauge vs min. cash floors</text>
                        </svg>
                        <p class="mt-2 text-xs font-semibold text-orange-700">Approaching statutory deadline &amp; minimum cash floors</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 17V7M10 17V9M16 17V5M22 17v-3"></path></svg>
                                </span>
                                <h3 class="text-sm font-semibold text-slate-900">Management Efficiency</h3>
                            </div>
                            <button type="button" @click="openAuditTrail('Management Efficiency')" class="text-slate-400 transition hover:text-slate-700" aria-label="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Operating Margin</p>
                        <button type="button" @click="openPeekPanel('Operating Margin')" class="mt-1 text-left text-4xl font-semibold leading-tight text-orange-700">34.5%</button>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Cost per client</p>
                                <button type="button" @click="openPeekPanel('Cost per Client')" class="font-semibold text-slate-900">KSh 1,200</button>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Collection efficiency</p>
                                <button type="button" @click="openPeekPanel('Collection Efficiency')" class="font-semibold text-slate-900">98.2%</button>
                            </div>
                        </div>
                        <svg class="mt-4 h-20 w-full" viewBox="0 0 320 80" aria-hidden="true">
                            <polyline points="20,55 80,50 140,47 200,40 260,36 300,34" fill="none" stroke="#f97316" stroke-width="3"></polyline>
                            <polyline x-show="compareMode" points="20,60 80,57 140,54 200,50 260,46 300,43" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="4 4"></polyline>
                            <circle cx="300" cy="34" r="4" fill="#f97316"></circle>
                            <text x="300" y="25" text-anchor="end" fill="#ea580c" font-size="10">34.5%</text>
                        </svg>
                        <p class="mt-2 text-xs text-slate-600">Highlights inefficiency risks even when net cash surplus remains strong.</p>
                    </article>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-base font-semibold text-slate-900">TIER 2: The Statement "Vault" (2x3 Grid of Report Tiles)</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $report)
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h8l4 4v14H7z"></path><path d="M15 3v4h4"></path></svg>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">{{ $report['title'] }}</h3>
                                        <p class="mt-1 text-xs text-slate-600">{{ $report['description'] }}</p>
                                        @if ($report['title'] === 'Tax / KRA Ledger')
                                            <p class="mt-2 inline-flex items-center rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-800">Next KRA due in 10 days</p>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" @click="openAuditTrail('{{ $report['title'] }}')" class="text-slate-400 transition hover:text-slate-700" aria-label="Audit history">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                </button>
                            </div>
                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
                                <button type="button" @click="exportPdf('{{ $report['title'] }}')" class="font-semibold text-blue-700 transition hover:text-blue-800">PDF Export</button>
                                <span class="inline-flex items-center gap-1 text-slate-500">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2" stroke-linecap="round"></path></svg>
                                    Last generated: {{ $report['last'] }}
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <div x-show="peekOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/30" @click="peekOpen = false"></div>
            <aside x-show="peekOpen" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" class="fixed inset-x-4 top-20 z-50 mx-auto w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-xl sm:inset-x-0">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Drill-down Peek Panel</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900" x-text="peekTitle"></h3>
                        <p class="mt-1 text-sm text-slate-600">Underlying journal entries and automation triggers.</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="peekOpen = false" aria-label="Close panel">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                    </button>
                </div>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="font-medium text-slate-800">JR-2404-118 · Interest collection batch</p>
                        <p class="text-xs text-slate-500">Debit: M-Pesa Utility · Credit: Interest Income · KSh 420,000</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="font-medium text-slate-800">JR-2404-123 · Salary disbursement trigger</p>
                        <p class="text-xs text-slate-500">Debit: Salary Expense · Credit: Equity Bank Operating · KSh 310,000</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="font-medium text-slate-800">JR-2404-131 · VAT accrual settlement</p>
                        <p class="text-xs text-slate-500">Debit: VAT Ledger · Credit: Equity Bank Main · KSh 110,000</p>
                    </div>
                </div>
            </aside>

            <div x-show="modalOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/45" @click="modalOpen = false"></div>
            <section x-show="modalOpen" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100" class="fixed inset-x-4 top-[12%] z-50 mx-auto w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:inset-x-0">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Quick Access &amp; Templates</p>
                        <h3 class="mt-1 text-xl font-semibold text-slate-900" x-text="modalMode === 'date' ? 'Time Traveler Control' : 'Template Creation System'"></h3>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="modalOpen = false" aria-label="Close modal">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                    </button>
                </div>

                <template x-if="modalMode === 'date'">
                    <div class="space-y-4 text-sm">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Time Traveler</span>
                            <input type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" x-model="dateRange">
                        </label>
                        <p class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">Real-time data synced to this range and applied to all intelligence cards and statement tiles.</p>
                        <div class="flex justify-end">
                            <button type="button" @click="modalOpen = false" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-800">Apply Range</button>
                        </div>
                    </div>
                </template>

                <template x-if="modalMode === 'template'">
                    <div class="space-y-4 text-sm">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">COA Mapping (Debit/Credit)</span>
                                <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                    <option>Cash Collection -> Dr M-Pesa Utility / Cr Interest Income</option>
                                    <option>Operating Expense -> Dr OPEX / Cr Equity Bank</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Governance Rules (Maker-Checker)</span>
                                <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                    <option>Mandatory Checker Approval</option>
                                    <option>Auto-approve below KSh 50,000</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Amount Logic</span>
                                <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                    <option>Fixed amount trigger</option>
                                    <option>Variable (% of batch amount)</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Approval Controls</span>
                                <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                    <option>Director + CPA dual sign-off</option>
                                    <option>CPA only</option>
                                </select>
                            </label>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modalOpen = false" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                            <button type="button" class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white transition hover:bg-blue-800">Create Template</button>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </x-loan.page>

    <script>
        function financialIntelligencePage() {
            return {
                compareMode: false,
                modalOpen: false,
                modalMode: 'template',
                peekOpen: false,
                peekTitle: 'Net Cash Surplus',
                dateRange: 'April 1 - April 24, 2026',
                openTemplateModal() {
                    this.modalMode = 'template';
                    this.modalOpen = true;
                },
                openDateModal() {
                    this.modalMode = 'date';
                    this.modalOpen = true;
                },
                openPeekPanel(title) {
                    this.peekTitle = title;
                    this.peekOpen = true;
                },
                openAuditTrail(title) {
                    this.peekTitle = `${title} Audit Trail`;
                    this.peekOpen = true;
                },
                exportPdf(title) {
                    const safeTitle = String(title || 'report')
                        .replace(/[^a-z0-9]+/gi, '-')
                        .replace(/^-+|-+$/g, '')
                        .toLowerCase();
                    const fileName = `${safeTitle || 'report'}-as-of-${this.dateRange.replace(/\s+/g, '-')}.pdf`;
                    const escapePdf = (value) => String(value || '').replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
                    const lines = [
                        `Report: ${title}`,
                        `Date Range: ${this.dateRange}`,
                        `Generated At: ${new Date().toLocaleString()}`,
                        '',
                        'This export is generated from the Financial Intelligence dashboard.',
                    ];
                    const textOps = lines.map((line, idx) => `1 0 0 1 50 ${760 - (idx * 18)} Tm (${escapePdf(line)}) Tj`).join('\n');
                    const stream = `BT\n/F1 12 Tf\n${textOps}\nET`;
                    const objects = [
                        '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj',
                        '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj',
                        '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj',
                        `4 0 obj\n<< /Length ${stream.length} >>\nstream\n${stream}\nendstream\nendobj`,
                        '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj',
                    ];
                    let pdf = '%PDF-1.4\n';
                    const offsets = [0];
                    objects.forEach((obj) => {
                        offsets.push(pdf.length);
                        pdf += `${obj}\n`;
                    });
                    const xrefOffset = pdf.length;
                    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
                    offsets.slice(1).forEach((offset) => {
                        pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
                    });
                    pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;
                    const blob = new Blob([pdf], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = fileName;
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                },
                closeAllPanels() {
                    this.modalOpen = false;
                    this.peekOpen = false;
                },
            };
        }
    </script>
</x-loan-layout>
<x-loan-layout>
    <x-loan.page title="Journal Entry Command Center" subtitle="Manage manual journal entries, adjustments, and non-routine cash movements.">
        <div class="min-h-full bg-slate-50/70 p-3 sm:p-5 lg:p-6" x-data="journalEntryCommandCenter()" @keydown.escape.window="closeTemplateModal()">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Journal Entry Command Center</h1>
                        <p class="mt-1 text-sm text-slate-600">Manage manual journal entries, adjustments, and non-routine cash movements.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 lg:items-end">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <p class="font-medium text-slate-600">Thursday, April 23, 2026</p>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="openTemplateModal()" class="inline-flex items-center gap-2 rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 5h16v5H4zM4 14h10v5H4zM17 14h3v5h-3z"/>
                                </svg>
                                Quick Access &amp; Templates
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <button type="button" @click="openTemplateModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Create quick access template">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-12">
                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <h2 class="text-base font-semibold text-slate-900">Templates &amp; Quick Access</h2>
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Personal Favorites</p>
                                <div class="mt-2 space-y-2">
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-sm text-blue-700 transition hover:bg-blue-100">
                                        <span class="truncate">M-Pesa to Bank Transfer</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-sm text-blue-700 transition hover:bg-blue-100">
                                        <span class="truncate">Petty Cash Replenishment</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-purple-100 bg-purple-50/70 px-3 py-2 text-sm text-purple-700 transition hover:bg-purple-100">
                                        <span class="truncate">Director Equity Contribution</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-purple-100 bg-purple-50/70 px-3 py-2 text-sm text-purple-700 transition hover:bg-purple-100">
                                        <span class="truncate">Salary Advance Issuance</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">System Defined</p>
                                <div class="mt-2 space-y-2">
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-blue-700 transition hover:bg-slate-50">
                                        <span class="truncate">Inter-Account Transfer</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-blue-700 transition hover:bg-slate-50">
                                        <span class="truncate">Bank Charges Allocation</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                    <a href="#" class="flex items-center justify-between rounded-lg border border-orange-100 bg-orange-50/70 px-3 py-2 text-sm text-orange-700 transition hover:bg-orange-100">
                                        <span class="truncate">Reversal of Previous Journal</span>
                                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </a>
                                </div>
                            </div>
                            <button type="button" class="mt-1 w-full rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">View Full Posted History</button>
                        </div>
                    </article>
                </aside>

                <main class="space-y-4 xl:col-span-6">
                    <section class="grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Blocked Drafts (The Liquidity Queue)</h3>
                                <svg class="h-4 w-4 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18.2a1.5 1.5 0 0 0 1.3 2.3h17.8a1.5 1.5 0 0 0 1.3-2.3L13.7 3.9a1.5 1.5 0 0 0-3.4 0Z" stroke-linejoin="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">7 Entries</p>
                            <p class="mt-1 text-xs text-orange-700">Violations of Min. Balance Floor rules.</p>
                        </article>
                        <article class="rounded-xl border border-purple-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Approval Queue</h3>
                                <svg class="h-4 w-4 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 5 3.2 8.2 7 10 3.8-1.8 7-5 7-10V6l-7-3Z"/><path d="m9.5 12 2 2 3.5-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">3 Entries</p>
                            <p class="mt-1 text-xs text-purple-700">Awaiting Director authorization</p>
                        </article>
                        <article class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">Drafts &amp; Unposted</h3>
                                <svg class="h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">5 Entries</p>
                            <p class="mt-1 text-xs text-blue-700">Not yet posted to the general ledger</p>
                        </article>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Smart Journal Entry Form</h2>
                                <p class="mt-0.5 text-sm text-slate-600">New Journal Entry</p>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold" :class="requiresApproval ? 'border-purple-200 bg-purple-50 text-purple-700' : 'border-teal-200 bg-teal-50 text-teal-700'">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 5 3.2 8.2 7 10 3.8-1.8 7-5 7-10V6l-7-3Z"/></svg>
                                <span x-text="requiresApproval ? 'DNA Check: Director Approval Required' : 'DNA Check'"></span>
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <label class="text-xs font-medium text-slate-600">Date
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="date" x-model="entryDate" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                            <label class="text-xs font-medium text-slate-600">Reference
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="text" placeholder="Reference" x-model="reference" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                            <label class="text-xs font-medium text-slate-600">Description
                                <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
                                    <input type="text" placeholder="Enter description of the transaction" x-model="description" class="w-full border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0">
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                </div>
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">if applicable based on account rules</p>

                        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-[860px] w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Journal Lines</th>
                                        <th class="px-3 py-2 text-right">Debit (KSh)</th>
                                        <th class="px-3 py-2 text-right">Credit (KSh)</th>
                                        <th class="px-3 py-2">Notes</th>
                                        <th class="px-3 py-2 text-right">Projected Balance (KSh)</th>
                                        <th class="px-3 py-2 text-center">Audit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <template x-for="(line, index) in lines" :key="index">
                                        <tr>
                                            <td class="px-3 py-2">
                                                <select x-model="line.account" @change="onLineChange(index)" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                                    <template x-for="account in accountOptions" :key="account.name">
                                                        <option :value="account.name" x-text="account.name"></option>
                                                    </template>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" min="0" step="100" x-model.number="line.debit" @input="onLineChange(index)" class="w-full rounded-lg border px-2 py-1.5 text-right text-sm focus:outline-none focus:ring-1" :class="line.belowFloor ? 'border-orange-300 bg-orange-50 focus:border-orange-500 focus:ring-orange-500' : 'border-slate-300 focus:border-teal-700 focus:ring-teal-700'">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" min="0" step="100" x-model.number="line.credit" @input="onLineChange(index)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="text" x-model="line.notes" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-700 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                            </td>
                                            <td class="px-3 py-2 text-right font-semibold" :class="line.belowFloor ? 'text-orange-600' : 'text-emerald-700'" x-text="formatKsh(line.projectedBalance)"></td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center justify-center gap-2 text-slate-500">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                                    <button type="button" @click="removeLine(index)" class="rounded p-1 hover:bg-slate-100" title="Remove line">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 5 14 14M19 5 5 19" stroke-linecap="round"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-slate-50 text-sm font-semibold text-slate-700">
                                    <tr>
                                        <td class="px-3 py-2">
                                            <button type="button" @click="addLine()" class="inline-flex items-center gap-2 rounded-md border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                                                Add Journal Line
                                            </button>
                                        </td>
                                        <td class="px-3 py-2 text-right" x-text="formatKsh(totalDebit)"></td>
                                        <td class="px-3 py-2 text-right" x-text="formatKsh(totalCredit)"></td>
                                        <td class="px-3 py-2"></td>
                                        <td class="px-3 py-2 text-right"></td>
                                        <td class="px-3 py-2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <span x-show="attachmentRequired" class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 7v10a4 4 0 0 1-8 0V6a3 3 0 0 1 6 0v10a2 2 0 0 1-4 0V8" stroke-linecap="round"/></svg>
                                Attachment Required
                            </span>
                            <span x-show="hasFloorViolation" class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700" x-text="guardrailMessage"></span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" x-show="!hasFloorViolation && !requiresApproval" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Post Transaction</button>
                            <button type="button" x-show="!hasFloorViolation && requiresApproval" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700">Submit for Approval</button>
                            <button type="button" x-show="hasFloorViolation" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">Save as Blocked Draft</button>
                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Save as Draft</button>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">When a high-value account is selected, or the amount pushes the projected balance below the COA floor, the amount field highlights in orange.</p>
                    </section>
                </main>

                <aside class="xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold text-slate-900">Last 15 Journal Activities</h2>
                            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                        </div>
                        <div class="mt-3 max-h-[42rem] space-y-2 overflow-y-auto pr-1">
                            <template x-for="(activity, index) in activities" :key="index">
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-xs text-slate-500" x-text="activity.time"></p>
                                            <p class="mt-1 text-sm font-semibold text-slate-800" x-text="activity.title"></p>
                                            <p class="text-xs text-slate-600" x-text="activity.ref"></p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" x-show="activity.status === 'Posted'" class="rounded p-1 text-orange-500 hover:bg-orange-50" title="Reverse">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 5v6h6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                            <button type="button" x-show="activity.status === 'Blocked Draft'" class="rounded p-1 text-blue-600 hover:bg-blue-50" title="Retry / Top-up">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12a8 8 0 0 1 14.6-4.4"/><path d="M20 12a8 8 0 0 1-14.6 4.4"/><path d="M19 4v5h-5M5 20v-5h5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-700" x-text="activity.amount"></span>
                                        <span class="rounded-full border px-2 py-0.5 text-xs font-semibold"
                                            :class="activity.status === 'Posted' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : (activity.status === 'Pending Approval' ? 'border-purple-200 bg-purple-50 text-purple-700' : 'border-orange-200 bg-orange-50 text-orange-700')"
                                            x-text="activity.status">
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </article>
                </aside>
            </section>

            <div x-show="showTemplateModal" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50" @click="closeTemplateModal()"></div>
            <section x-show="showTemplateModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-xl" @click.stop role="dialog" aria-modal="true">
                    <header class="flex items-start justify-between border-b border-slate-200 p-4 sm:p-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Config Popup</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Create New Quick Access Template</h3>
                        </div>
                        <button type="button" @click="closeTemplateModal()" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Close modal">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
                        </button>
                    </header>

                    <div class="max-h-[72vh] space-y-4 overflow-y-auto p-4 sm:p-5">
                        <section class="rounded-xl border border-slate-200 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Template Details</h4>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600">Template Name
                                    <input type="text" x-model="templateForm.name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                </label>
                                <label class="text-xs font-medium text-slate-600">Category
                                    <select x-model="templateForm.category" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                        <option>Personal</option>
                                        <option>System</option>
                                    </select>
                                </label>
                                <label class="sm:col-span-2 text-xs font-medium text-slate-600">Description
                                    <input type="text" x-model="templateForm.description" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                </label>
                            </div>
                            <button type="button" class="mt-3 text-xs font-semibold text-blue-600 hover:text-blue-700">View / Manage All</button>
                        </section>

                        <section class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-900">Accounting DNA Mappings (CPA-level fields)</h4>
                                <span class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700">DNA-checked</span>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600">Debit Account (COA)
                                    <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 px-2 py-1.5">
                                        <select x-model="templateForm.debitAccount" @change="validateTemplateAccess()" class="w-full border-0 bg-transparent text-sm text-slate-800 focus:ring-0">
                                            <template x-for="account in coaOptions" :key="'d-'+account">
                                                <option :value="account" x-text="account"></option>
                                            </template>
                                        </select>
                                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </div>
                                </label>
                                <label class="text-xs font-medium text-slate-600">Credit Account (COA)
                                    <div class="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 px-2 py-1.5">
                                        <select x-model="templateForm.creditAccount" @change="validateTemplateAccess()" class="w-full border-0 bg-transparent text-sm text-slate-800 focus:ring-0">
                                            <template x-for="account in coaOptions" :key="'c-'+account">
                                                <option :value="account" x-text="account"></option>
                                            </template>
                                        </select>
                                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </div>
                                </label>
                                <label class="text-xs font-medium text-slate-600">Reference Prefix
                                    <input type="text" x-model="templateForm.referencePrefix" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                </label>
                                <fieldset class="text-xs font-medium text-slate-600">
                                    <legend>Amount Type</legend>
                                    <div class="mt-2 flex items-center gap-4 text-sm">
                                        <label class="inline-flex items-center gap-1.5">
                                            <input type="radio" name="amountType" value="Fixed Amount" x-model="templateForm.amountType" class="text-blue-600 focus:ring-blue-500">
                                            Fixed Amount
                                        </label>
                                        <label class="inline-flex items-center gap-1.5">
                                            <input type="radio" name="amountType" value="Variable Amount" x-model="templateForm.amountType" class="text-blue-600 focus:ring-blue-500">
                                            Variable Amount
                                        </label>
                                    </div>
                                </fieldset>
                            </div>
                            <div x-show="templateAccessRestricted" class="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700">
                                You are not authorized to create templates for this account.
                                <span class="ml-2 inline-flex rounded-full border border-orange-300 bg-white px-2 py-0.5 text-[11px] font-bold">Access Restricted</span>
                            </div>
                        </section>

                        <section class="rounded-xl border border-slate-200 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Governance &amp; Rules Builder</h4>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <span>Requires Dual Authorization (Maker-Checker)</span>
                                    <input type="checkbox" x-model="templateForm.dualAuthorization" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <span>Requires Mandatory Cost Center Tag</span>
                                    <input type="checkbox" x-model="templateForm.costCenterRequired" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <span>Requires Mandatory Client / Loan ID</span>
                                    <input type="checkbox" x-model="templateForm.clientLoanRequired" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <span>Enforce Transaction Limit</span>
                                    <input type="checkbox" x-model="templateForm.limitEnforced" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="text-xs font-medium text-slate-600">Authorized Approver / Checker
                                    <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                        <option>Finance Director</option>
                                        <option>Head of Finance</option>
                                    </select>
                                </label>
                                <label class="text-xs font-medium text-slate-600">Maker Role
                                    <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-700 focus:outline-none focus:ring-1 focus:ring-teal-700">
                                        <option>Accountant</option>
                                        <option>Branch Operations</option>
                                    </select>
                                </label>
                                <label class="text-xs font-medium text-slate-600 sm:col-span-2">Maximum amount input
                                    <input type="number" min="0" x-model.number="templateForm.maxAmount" class="mt-1 w-full rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-900 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500">
                                </label>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
                                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">Active</span>
                                <span class="rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-slate-700">History</span>
                                <span class="rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-orange-700">Access Restricted</span>
                                <span class="rounded-full border border-purple-200 bg-purple-50 px-2.5 py-1 text-purple-700">COA Rules Applied</span>
                            </div>
                        </section>
                    </div>

                    <footer class="flex items-center justify-between gap-2 border-t border-slate-200 p-4 sm:p-5">
                        <div class="inline-flex rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Active (COA Rules Applied)</div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="closeTemplateModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" :disabled="templateAccessRestricted" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300">Create Template</button>
                        </div>
                    </footer>
                </div>
            </section>
        </div>
    </x-loan.page>

    <script>
        function journalEntryCommandCenter() {
            return {
                showTemplateModal: false,
                entryDate: '2026-04-23',
                reference: '',
                description: '',
                accountOptions: [
                    { name: 'M-Pesa Utility', balance: 52000, floor: 5000, restricted: false, approval: false },
                    { name: 'Cash Account', balance: 9500, floor: 5000, restricted: false, approval: false },
                    { name: 'Equity Bank', balance: 120000, floor: 10000, restricted: false, approval: false },
                    { name: 'Director Reserve Account', balance: 250000, floor: 30000, restricted: true, approval: true },
                ],
                lines: [
                    { account: 'M-Pesa Utility', debit: 5000, credit: 0, notes: 'Transfer from M-Pesa', projectedBalance: 52000, belowFloor: false },
                    { account: 'Cash Account', debit: 0, credit: 5000, notes: 'Cash deposit', projectedBalance: 4500, belowFloor: true },
                    { account: 'Equity Bank', debit: 0, credit: 0, notes: '', projectedBalance: 120000, belowFloor: false },
                ],
                activities: [
                    { time: '10:45 AM', title: 'KSh 1.2M M-Pesa to Bank', amount: 'Ref: JE-000245', ref: 'Posted', status: 'Posted' },
                    { time: '10:20 AM', title: 'Petty Cash Replenishment', amount: 'Ref: JE-000244', ref: 'Posted', status: 'Posted' },
                    { time: '09:55 AM', title: 'Salary Payment Adjustment', amount: 'Ref: JE-000243', ref: 'Pending Approval', status: 'Pending Approval' },
                    { time: '09:30 AM', title: 'Cash Deposit to Bank', amount: 'Ref: JE-000242', ref: 'Posted', status: 'Posted' },
                    { time: '09:05 AM', title: 'Loan Recovery Reversal', amount: 'Ref: JE-000241', ref: 'Blocked Draft', status: 'Blocked Draft' },
                    { time: '08:40 AM', title: 'Bank Charge Allocation', amount: 'Ref: JE-000240', ref: 'Posted', status: 'Posted' },
                    { time: '08:15 AM', title: 'Suspense Account Clearance', amount: 'Ref: JE-000239', ref: 'Pending Approval', status: 'Pending Approval' },
                ],
                coaOptions: ['M-Pesa Utility', 'Cash Account', 'Equity Bank', 'Director Reserve Account'],
                templateForm: {
                    name: '',
                    description: '',
                    category: 'Personal',
                    debitAccount: 'M-Pesa Utility',
                    creditAccount: 'Cash Account',
                    referencePrefix: 'RENT-',
                    amountType: 'Variable Amount',
                    dualAuthorization: true,
                    costCenterRequired: true,
                    clientLoanRequired: false,
                    limitEnforced: true,
                    maxAmount: 100000,
                },
                templateAccessRestricted: false,
                openTemplateModal() {
                    this.showTemplateModal = true;
                    this.validateTemplateAccess();
                },
                closeTemplateModal() {
                    this.showTemplateModal = false;
                },
                formatKsh(value) {
                    return `KSh ${new Intl.NumberFormat().format(Number(value || 0))}`;
                },
                onLineChange(index) {
                    const line = this.lines[index];
                    const account = this.accountOptions.find((item) => item.name === line.account);
                    if (!account) return;
                    line.projectedBalance = Number(account.balance) + Number(line.debit || 0) - Number(line.credit || 0);
                    line.belowFloor = line.projectedBalance < Number(account.floor);
                },
                addLine() {
                    this.lines.push({ account: 'M-Pesa Utility', debit: 0, credit: 0, notes: '', projectedBalance: 52000, belowFloor: false });
                },
                removeLine(index) {
                    if (this.lines.length > 1) this.lines.splice(index, 1);
                },
                validateTemplateAccess() {
                    const restricted = this.templateForm.debitAccount === 'Director Reserve Account' || this.templateForm.creditAccount === 'Director Reserve Account';
                    this.templateAccessRestricted = restricted;
                },
                get totalDebit() {
                    return this.lines.reduce((sum, row) => sum + Number(row.debit || 0), 0);
                },
                get totalCredit() {
                    return this.lines.reduce((sum, row) => sum + Number(row.credit || 0), 0);
                },
                get hasFloorViolation() {
                    return this.lines.some((row) => row.belowFloor);
                },
                get requiresApproval() {
                    return this.lines.some((row) => row.account === 'Director Reserve Account') || this.totalDebit >= 500000;
                },
                get attachmentRequired() {
                    return this.requiresApproval || this.totalDebit >= 100000;
                },
                get guardrailMessage() {
                    const violating = this.lines.find((row) => row.belowFloor);
                    if (!violating) return '';
                    const account = this.accountOptions.find((item) => item.name === violating.account);
                    return `Projected Balance: ${this.formatKsh(violating.projectedBalance)} (Below COA Floor: ${this.formatKsh(account ? account.floor : 0)})`;
                },
                init() {
                    this.lines.forEach((_, index) => this.onLineChange(index));
                    this.validateTemplateAccess();
                },
            };
        }
    </script>
</x-loan-layout>
@php
    $fmtN = fn (int|float $n) => number_format((float) $n, 0);
    $chartBase = rtrim(route('loan.accounting.chart.index'), '/');
    $accountsCollection = collect($accounts ?? []);
    $coaRows = $accountsCollection->take(12);
    if ($coaRows->isEmpty()) {
        $coaRows = collect([
            (object) ['id' => 1, 'code' => '4021', 'name' => 'Account Manage', 'account_type' => 'asset'],
            (object) ['id' => 2, 'code' => '4099', 'name' => 'M-Pesa Utility', 'account_type' => 'asset'],
            (object) ['id' => 3, 'code' => '61001', 'name' => 'Equity Bank Operating', 'account_type' => 'asset'],
            (object) ['id' => 4, 'code' => '4119', 'name' => 'Equity Bank', 'account_type' => 'liability'],
            (object) ['id' => 5, 'code' => '4132', 'name' => 'Equity Bank Opera', 'account_type' => 'liability'],
        ]);
    }
    $assetRows = $coaRows->filter(fn ($r) => strtolower((string) data_get($r, 'account_type')) === 'asset')->values();
    $liabilityRows = $coaRows->filter(fn ($r) => strtolower((string) data_get($r, 'account_type')) === 'liability')->values();
    $otherRows = $coaRows->reject(fn ($r) => in_array(strtolower((string) data_get($r, 'account_type')), ['asset', 'liability'], true))->values();
    $mappingRows = collect($postingRules ?? [])->take(4);
    if ($mappingRows->isEmpty()) {
        $mappingRows = collect([
            (object) ['label' => 'New Loan Disbursed', 'debitAccount' => (object) ['name' => 'Loan Portfolio (Principal)'], 'creditAccount' => (object) ['name' => 'M-Pesa Bulk Utility']],
            (object) ['label' => 'Processing Fee Recovery', 'debitAccount' => (object) ['name' => 'M-Pesa Bulk Utility'], 'creditAccount' => (object) ['name' => 'Fee Income']],
            (object) ['label' => 'Staff Salary Payment', 'debitAccount' => (object) ['name' => 'Salary Expense'], 'creditAccount' => (object) ['name' => 'Bank Account']],
            (object) ['label' => 'M-Pesa Reversal', 'debitAccount' => (object) ['name' => 'Client Ledger'], 'creditAccount' => (object) ['name' => 'M-Pesa Bulk Utility']],
        ]);
    }
    $statusCycle = ['Checker Assigned', 'Awaiting Approval', 'Active', 'Draft'];
    $accountsPayload = $coaRows->map(fn ($row) => [
        'id' => (int) data_get($row, 'id'),
        'code' => (string) data_get($row, 'code', ''),
        'name' => (string) data_get($row, 'name', ''),
        'account_type' => (string) data_get($row, 'account_type', 'asset'),
        'is_active' => (bool) data_get($row, 'is_active', true),
        'is_cash_account' => (bool) data_get($row, 'is_cash_account', false),
    ])->values();
@endphp

<x-loan-layout>
    <x-loan.page title="Chart of Accounts &amp; Rules" subtitle="Define and manage the company account structure, hierarchies, and regulatory compliance rules.">
        @include('loan.accounting.partials.flash')

        <div class="space-y-6" x-data="chartAccountsPage({ accounts: @js($accountsPayload), chartBase: @js($chartBase) })">
            <section class="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Chart of Accounts &amp; Rules</h1>
                        <p class="mt-1 text-sm text-slate-600">Central control hub for the general ledger, operational registers, payroll, budgets, and financial statements.</p>
                    </div>
                    <div class="space-y-3 lg:text-right">
                        <p class="text-sm font-medium text-slate-600">{{ now()->format('l, F j, Y') }}</p>
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: April 2026 (Open)</span>
                        <div class="ml-auto w-full max-w-md rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-left">
                            <p class="text-sm font-semibold text-slate-900">Global Cash-Basis Settings</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                                    <span>System Accounting Mode: Cash-Basis Only</span>
                                    <input type="checkbox" checked class="h-4 w-8 cursor-pointer rounded-full border border-slate-300 bg-blue-600 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                                    <span>Enforce Liquidity Guardrails</span>
                                    <input type="checkbox" checked class="h-4 w-8 cursor-pointer rounded-full border border-slate-300 bg-blue-600 text-blue-600 focus:ring-blue-500">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article class="rounded-xl border border-orange-200 bg-white p-4 shadow-sm transition hover:-translate-y-px hover:shadow-md">
                    <h2 class="text-sm font-semibold text-slate-900">Audit Status</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between rounded-lg bg-orange-50 px-3 py-2 text-orange-700">
                            <span>Pending Account Approvals</span>
                            <span class="font-semibold">7</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-orange-50 px-3 py-2 text-orange-700">
                            <span>Accounts Missing Rules</span>
                            <span class="font-semibold">14</span>
                        </div>
                    </div>
                </article>
                <article class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm transition hover:-translate-y-px hover:shadow-md">
                    <h2 class="text-sm font-semibold text-slate-900">Financial Pulse</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-emerald-700">
                            <span>Active G/L Accounts</span>
                            <span class="font-semibold">450</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-emerald-700">
                            <span>New Accounts (30 Days)</span>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold">+18</span>
                        </div>
                    </div>
                </article>
                <article class="rounded-xl border border-teal-200 bg-white p-4 shadow-sm transition hover:-translate-y-px hover:shadow-md">
                    <h2 class="text-sm font-semibold text-slate-900">Balanced Books Meter</h2>
                    <p class="mt-3 text-sm text-slate-600">Total Debits Balance with Total Credits</p>
                    <div class="mt-3 flex items-center justify-between">
                        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 12A8 8 0 1 1 4 12" stroke-linecap="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Balanced
                        </span>
                    </div>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-10">
                <div class="space-y-6 xl:col-span-7">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Manage Chart of Accounts (Cash Flow View)</h2>
                            <button type="button" @click="newAccount()" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">Create New Account</button>
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
                                        <th class="px-3 py-2">Min. Balance (Floor)</th>
                                        <th class="px-3 py-2">Active State</th>
                                        <th class="px-3 py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr class="bg-emerald-50/60 text-xs font-semibold uppercase tracking-wide text-emerald-800">
                                        <td class="px-3 py-2" colspan="8">Assets</td>
                                    </tr>
                                    @foreach ($assetRows as $row)
                                        @php
                                            $bal = (int) data_get($row, 'current_balance', 50000 - ($loop->index * 12000));
                                            $floor = (int) data_get($row, 'min_balance_floor', 10000);
                                            $isNear = $bal <= ($floor * 1.2);
                                            $isLow = $bal < $floor;
                                        @endphp
                                        <tr class="group cursor-pointer bg-white transition hover:bg-teal-50/60" @click="selectAccount({{ (int) data_get($row, 'id', 0) }})" :class="selectedId === {{ (int) data_get($row, 'id', 0) }} ? 'ring-1 ring-teal-500 ring-inset' : ''">
                                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-700">{{ data_get($row, 'code', '0000') }}</td>
                                            <td class="px-3 py-2 font-medium text-slate-800">{{ data_get($row, 'name', 'Account') }}</td>
                                            <td class="px-3 py-2 capitalize text-slate-600">{{ data_get($row, 'account_type', 'asset') }}</td>
                                            <td class="px-3 py-2 text-slate-600">{{ data_get($row, 'parent_group', 'Assets') }}</td>
                                            <td class="px-3 py-2 font-semibold {{ $isLow ? 'text-red-600' : ($isNear ? 'text-orange-600' : 'text-emerald-700') }}">KSh {{ $fmtN($bal) }}</td>
                                            <td class="px-3 py-2 text-slate-600">KSh {{ $fmtN($floor) }}</td>
                                            <td class="px-3 py-2">
                                                <input type="checkbox" @checked((bool) data_get($row, 'is_active', true)) disabled class="h-4 w-8 rounded-full border border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-1 opacity-70 transition group-hover:opacity-100">
                                                    <a href="{{ route('loan.accounting.chart.edit', data_get($row, 'id', 0)) }}" class="rounded p-1 text-slate-500 hover:bg-blue-50 hover:text-blue-700" title="Edit" @click.stop>
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4-1 10-10a2 2 0 0 0-3-3L5 16l-1 4Z"/><path d="m13 6 3 3"/></svg>
                                                    </a>
                                                    <button type="button" @click.stop="duplicateFrom({{ (int) data_get($row, 'id', 0) }})" class="rounded p-1 text-slate-500 hover:bg-blue-50 hover:text-blue-700" title="Duplicate">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="8" y="8" width="10" height="10" rx="2"/><rect x="4" y="4" width="10" height="10" rx="2"/></svg>
                                                    </button>
                                                    <a href="{{ route('loan.accounting.chart.edit', data_get($row, 'id', 0)) }}" class="rounded p-1 text-slate-500 hover:bg-blue-50 hover:text-blue-700" title="View" @click.stop>
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                                    </a>
                                                    <form method="post" action="{{ route('loan.accounting.chart.destroy', data_get($row, 'id', 0)) }}" @click.stop>
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="rounded p-1 text-slate-500 hover:bg-red-50 hover:text-red-700" title="Delete" data-swal-confirm="Remove this account? It must have no journal lines.">
                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16"/><path d="M9 7V5h6v2"/><path d="m7 7 1 12h8l1-12"/></svg>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-purple-50 hover:text-purple-700" title="Audit History">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach

                                    <tr class="bg-orange-50/60 text-xs font-semibold uppercase tracking-wide text-orange-800">
                                        <td class="px-3 py-2" colspan="8">Liabilities</td>
                                    </tr>
                                    @foreach ($liabilityRows as $row)
                                        @php
                                            $bal = (int) data_get($row, 'current_balance', 120000 - ($loop->index * 18000));
                                            $floor = (int) data_get($row, 'min_balance_floor', 10000);
                                        @endphp
                                        <tr class="group cursor-pointer bg-white transition hover:bg-teal-50/60" @click="selectAccount({{ (int) data_get($row, 'id', 0) }})" :class="selectedId === {{ (int) data_get($row, 'id', 0) }} ? 'ring-1 ring-teal-500 ring-inset' : ''">
                                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-700">{{ data_get($row, 'code', '0000') }}</td>
                                            <td class="px-3 py-2 font-medium text-slate-800">{{ data_get($row, 'name', 'Liability Account') }}</td>
                                            <td class="px-3 py-2 capitalize text-slate-600">{{ data_get($row, 'account_type', 'liability') }}</td>
                                            <td class="px-3 py-2 text-slate-600">{{ data_get($row, 'parent_group', 'Group') }}</td>
                                            <td class="px-3 py-2 font-semibold text-orange-700">KSh {{ $fmtN($bal) }}</td>
                                            <td class="px-3 py-2 text-slate-600">KSh {{ $fmtN($floor) }}</td>
                                            <td class="px-3 py-2"><input type="checkbox" @checked((bool) data_get($row, 'is_active', true)) disabled class="h-4 w-8 rounded-full border border-slate-300 text-emerald-600 focus:ring-emerald-500"></td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-1 opacity-70 transition group-hover:opacity-100">
                                                    <a href="{{ route('loan.accounting.chart.edit', data_get($row, 'id', 0)) }}" class="rounded p-1 text-slate-500 hover:bg-blue-50 hover:text-blue-700" title="Edit" @click.stop><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4-1 10-10a2 2 0 0 0-3-3L5 16l-1 4Z"/><path d="m13 6 3 3"/></svg></a>
                                                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-purple-50 hover:text-purple-700" title="Audit History"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg></button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @if ($otherRows->isNotEmpty())
                                        <tr class="bg-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                            <td class="px-3 py-2" colspan="8">Other Groups</td>
                                        </tr>
                                        @foreach ($otherRows as $row)
                                            <tr class="group cursor-pointer bg-white transition hover:bg-teal-50/60">
                                                <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ data_get($row, 'code', '0000') }}</td>
                                                <td class="px-3 py-2 font-medium text-slate-800">{{ data_get($row, 'name', 'General Account') }}</td>
                                                <td class="px-3 py-2 capitalize text-slate-600">{{ data_get($row, 'account_type', 'other') }}</td>
                                                <td class="px-3 py-2 text-slate-600">{{ data_get($row, 'parent_group', 'General') }}</td>
                                                <td class="px-3 py-2 font-semibold text-emerald-700">KSh {{ $fmtN((int) data_get($row, 'current_balance', 5000)) }}</td>
                                                <td class="px-3 py-2 text-slate-600">KSh {{ $fmtN((int) data_get($row, 'min_balance_floor', 10000)) }}</td>
                                                <td class="px-3 py-2"><input type="checkbox" @checked((bool) data_get($row, 'is_active', true)) disabled class="h-4 w-8 rounded-full border border-slate-300 text-emerald-600 focus:ring-emerald-500"></td>
                                                <td class="px-3 py-2 text-slate-500">-</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-900">Automated Cash Mappings (Maker-Checker)</h2>
                            <span class="rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Governance Layer</span>
                        </div>
                        @if (session('status') === 'Accounting rule updated.')
                            <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                                Mapping rule saved successfully.
                            </div>
                        @endif
                        @if ($errors->has('debit_account_id') || $errors->has('credit_account_id'))
                            <div class="mb-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-medium text-orange-800">
                                {{ $errors->first('debit_account_id') ?: $errors->first('credit_account_id') }}
                            </div>
                        @endif
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
                                    @foreach ($mappingRows as $map)
                                        @php
                                            $isLiveRule = (bool) data_get($map, 'id');
                                            $isMapped = data_get($map, 'debit_account_id') && data_get($map, 'credit_account_id');
                                            $state = $isLiveRule ? ($isMapped ? 'Active' : 'Awaiting Approval') : $statusCycle[$loop->index % count($statusCycle)];
                                            $stateClasses = match ($state) {
                                                'Active' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                                'Awaiting Approval' => 'border-orange-200 bg-orange-50 text-orange-700',
                                                'Checker Assigned' => 'border-purple-200 bg-purple-50 text-purple-700',
                                                default => 'border-slate-200 bg-slate-100 text-slate-700',
                                            };
                                        @endphp
                                        @if ($isLiveRule && data_get($map, 'is_editable', false))
                                            <tr class="group transition hover:bg-teal-50/60">
                                                <td class="px-3 py-2 font-medium text-slate-800">{{ data_get($map, 'label', 'Trigger') }}</td>
                                                <td class="px-3 py-2 text-slate-700">
                                                    <form method="post" action="{{ route('loan.accounting.chart.posting_rules.update', data_get($map, 'id')) }}" @submit.prevent="saveMapping($event, {{ (int) data_get($map, 'id') }})" class="grid gap-2 md:grid-cols-[1fr_1fr_auto] md:items-center">
                                                        @csrf
                                                        @method('patch')
                                                        <select name="debit_account_id" class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600">
                                                            <option value="">Select debit account</option>
                                                            @foreach ($selectAccounts as $acc)
                                                                <option value="{{ $acc->id }}" @selected((int) data_get($map, 'debit_account_id') === (int) $acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <select name="credit_account_id" class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600">
                                                            <option value="">Select credit account</option>
                                                            @foreach ($selectAccounts as $acc)
                                                                <option value="{{ $acc->id }}" @selected((int) data_get($map, 'credit_account_id') === (int) $acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" :disabled="Boolean(mappingBusy[{{ (int) data_get($map, 'id') }}])" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60" x-text="mappingBusy[{{ (int) data_get($map, 'id') }}] ? 'Saving...' : 'Save'">Save</button>
                                                    </form>
                                                    <template x-if="mappingFeedback[{{ (int) data_get($map, 'id') }}]">
                                                        <p class="mt-1 text-xs"
                                                           :class="mappingFeedback[{{ (int) data_get($map, 'id') }}].type === 'success' ? 'text-emerald-700' : 'text-orange-700'"
                                                           x-text="mappingFeedback[{{ (int) data_get($map, 'id') }}].message"></p>
                                                    </template>
                                                </td>
                                                <td class="hidden px-3 py-2 text-slate-700 md:table-cell">{{ data_get($map, 'creditAccount.name', '—') }}</td>
                                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $stateClasses }}">{{ $state }}</span></td>
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-1 opacity-70 transition group-hover:opacity-100">
                                                        <button type="button" class="rounded p-1 text-slate-500 hover:bg-purple-50 hover:text-purple-700" title="Mapping History"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @else
                                            <tr class="group cursor-pointer transition hover:bg-teal-50/60">
                                                <td class="px-3 py-2 font-medium text-slate-800">{{ data_get($map, 'label', 'Trigger') }}</td>
                                                <td class="px-3 py-2 text-slate-700">{{ data_get($map, 'debitAccount.name', data_get($map, 'debit_account', '—')) }}</td>
                                                <td class="px-3 py-2 text-slate-700">{{ data_get($map, 'creditAccount.name', data_get($map, 'credit_account', '—')) }}</td>
                                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $stateClasses }}">{{ $state }}</span></td>
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-1 opacity-70 transition group-hover:opacity-100">
                                                        <button type="button" class="rounded p-1 text-slate-500 hover:bg-blue-50 hover:text-blue-700" title="Edit Mapping"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4-1 10-10a2 2 0 0 0-3-3L5 16l-1 4Z"/><path d="m13 6 3 3"/></svg></button>
                                                        <button type="button" class="rounded p-1 text-slate-500 hover:bg-purple-50 hover:text-purple-700" title="Mapping History"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>

                <aside class="xl:col-span-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Create / Edit Account Details</h2>
                        <form method="post" :action="formAction" class="mt-4 space-y-5">
                            @csrf
                            <template x-if="isEditMode">
                                <input type="hidden" name="_method" value="PATCH">
                            </template>
                            <section>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-slate-800">General Information</h3>
                                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-purple-50 hover:text-purple-700" title="Field Audit History">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </button>
                                </div>
                                <div class="space-y-3">
                                    <input type="text" name="name" x-model="form.name" placeholder="Account Name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600">
                                    <input type="text" name="code" x-model="form.code" placeholder="Account Code (mask-able)" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <select name="account_type" x-model="form.account_type" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600">
                                            <option value="asset">Asset</option>
                                            <option value="liability">Liability</option>
                                            <option value="equity">Equity</option>
                                            <option value="income">Income</option>
                                            <option value="expense">Expense</option>
                                        </select>
                                        <select class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600">
                                            <option>Parent Group</option>
                                            <option>Assets</option>
                                            <option>Liabilities</option>
                                            <option>Equity</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <input type="text" placeholder="Opening Balance (KSh)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600">
                                        <input type="text" placeholder="Min. Required Balance (Floor)" class="rounded-lg border border-orange-300 bg-orange-50/50 px-3 py-2 text-sm text-slate-800 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500">
                                    </div>
                                    <p class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-700">WARNING: Blocks transaction if balance goes below floor.</p>
                                </div>
                            </section>

                            <section class="border-t border-slate-200 pt-4">
                                <h3 class="text-sm font-semibold text-slate-800">Controls</h3>
                                <label class="mt-2 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <span>Account Active</span>
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="h-4 w-8 rounded-full border border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                </label>
                                <label class="mt-2 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <span>Cash Account</span>
                                    <input type="hidden" name="is_cash_account" value="0">
                                    <input type="checkbox" name="is_cash_account" value="1" x-model="form.is_cash_account" class="h-4 w-8 rounded-full border border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                </label>
                            </section>

                            <section class="border-t border-slate-200 pt-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-slate-800">Rules &amp; Governance Layer</h3>
                                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-purple-50 hover:text-purple-700" title="Rule Audit History">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                        <span>Requires Dual Authorization (Maker-Checker)</span>
                                        <input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </label>
                                    <div class="grid grid-cols-1 gap-2">
                                        <select class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800">
                                            <option>Maker (e.g., Loan Officer)</option>
                                        </select>
                                        <select class="rounded-lg border border-slate-300 bg-purple-50 px-3 py-2 text-sm text-slate-800">
                                            <option>Checker required (Approver)</option>
                                        </select>
                                    </div>

                                    <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                        <span>Enforce Transaction Limit</span>
                                        <input type="checkbox" checked class="h-4 w-8 rounded-full border border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </label>
                                    <input type="text" value="KSh 100k" class="w-full rounded-lg border border-orange-300 bg-orange-50/60 px-3 py-2 text-sm text-orange-900 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500">

                                    <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                        <span>Requires Mandatory Cost Center Tag</span>
                                        <input type="checkbox" class="h-4 w-8 rounded-full border border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </label>
                                </div>
                            </section>

                            <section class="border-t border-slate-200 pt-4">
                                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700" x-text="isEditMode ? 'Update Account' : 'Save Account'">Save Account</button>
                                <p class="mt-3 inline-flex rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700">Account Status: Proposed (Awaiting Director Approval)</p>
                            </section>
                        </form>
                    </div>
                </aside>
            </section>
        </div>
    </x-loan.page>
    <script>
        function chartAccountsPage(config) {
            return {
                accounts: Array.isArray(config.accounts) ? config.accounts : [],
                chartBase: config.chartBase || '',
                selectedId: null,
                mappingBusy: {},
                mappingFeedback: {},
                form: {
                    code: '',
                    name: '',
                    account_type: 'asset',
                    is_active: true,
                    is_cash_account: false,
                },
                get isEditMode() {
                    return Number.isInteger(this.selectedId) && this.selectedId > 0;
                },
                get formAction() {
                    return this.isEditMode ? `${this.chartBase}/${this.selectedId}` : '{{ route('loan.accounting.chart.store') }}';
                },
                selectAccount(id) {
                    const row = this.accounts.find((item) => Number(item.id) === Number(id));
                    if (!row) return;
                    this.selectedId = Number(row.id);
                    this.form.code = row.code || '';
                    this.form.name = row.name || '';
                    this.form.account_type = row.account_type || 'asset';
                    this.form.is_active = Boolean(row.is_active);
                    this.form.is_cash_account = Boolean(row.is_cash_account);
                },
                duplicateFrom(id) {
                    const row = this.accounts.find((item) => Number(item.id) === Number(id));
                    if (!row) return;
                    this.selectedId = null;
                    this.form.code = row.code ? `${row.code}-COPY` : '';
                    this.form.name = row.name ? `${row.name} Copy` : '';
                    this.form.account_type = row.account_type || 'asset';
                    this.form.is_active = true;
                    this.form.is_cash_account = Boolean(row.is_cash_account);
                },
                newAccount() {
                    this.selectedId = null;
                    this.form = {
                        code: '',
                        name: '',
                        account_type: 'asset',
                        is_active: true,
                        is_cash_account: false,
                    };
                },
                async saveMapping(event, ruleId) {
                    const form = event.target;
                    if (!form || this.mappingBusy[ruleId]) return;

                    this.mappingBusy[ruleId] = true;
                    this.mappingFeedback[ruleId] = { type: 'info', message: 'Saving...' };

                    const formData = new FormData(form);
                    const payload = new URLSearchParams();
                    for (const [key, value] of formData.entries()) {
                        payload.append(key, value == null ? '' : String(value));
                    }

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': String(formData.get('_token') || ''),
                            },
                            body: payload.toString(),
                        });

                        if (response.status === 422) {
                            const data = await response.json();
                            const msg = data?.errors?.debit_account_id?.[0]
                                || data?.errors?.credit_account_id?.[0]
                                || data?.message
                                || 'Unable to save mapping.';
                            this.mappingFeedback[ruleId] = { type: 'error', message: msg };
                            return;
                        }

                        if (!response.ok) {
                            throw new Error('Request failed');
                        }

                        this.mappingFeedback[ruleId] = { type: 'success', message: 'Mapping saved.' };
                        setTimeout(() => {
                            if (this.mappingFeedback[ruleId]?.type === 'success') {
                                delete this.mappingFeedback[ruleId];
                            }
                        }, 2500);
                    } catch (error) {
                        this.mappingFeedback[ruleId] = { type: 'error', message: 'Save failed. Please retry.' };
                    } finally {
                        this.mappingBusy[ruleId] = false;
                    }
                },
            };
        }
    </script>
</x-loan-layout>
