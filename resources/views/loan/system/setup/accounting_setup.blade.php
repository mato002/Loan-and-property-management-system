<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle" :showQuickLinks="false" class="max-w-[1320px] ml-0 mr-auto lg:ml-8 xl:ml-10">
        <x-slot name="actions">
            <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                System Setup
            </a>
            <a href="{{ $booksUrl }}" class="inline-flex items-center gap-2 rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5A2.5 2.5 0 016.5 17H20" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5V4.5A2.5 2.5 0 016.5 2z" />
                </svg>
                Open Chart of Accounts
            </a>
        </x-slot>

        @include('loan.accounting.partials.flash')

        @php
            $approvalEnabledOld = old('coa_approval_required', ($approvalEnabled ?? false) ? '1' : '0');
            $approvalRowsOld = old('approvers', $coaApproverWorkflow ?? []);
        @endphp

        <div
            class="space-y-6 bg-slate-50/70 p-2 sm:p-3 rounded-xl"
            x-data="{
                requireApproval: {{ $approvalEnabledOld === '1' ? 'true' : 'false' }},
                approverRows: {{ \Illuminate\Support\Js::from(array_values($approvalRowsOld)) }},
                addRow() {
                    if (this.approverRows.length >= 8) return;
                    this.approverRows.push({ user_id: '' });
                },
                removeRow(index) {
                    this.approverRows.splice(index, 1);
                }
            }"
        >
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Accounting Readiness</p>
                        <div class="flex items-end gap-3">
                            <p class="text-3xl font-bold text-teal-900">74%</p>
                            <span class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700">Partially configured</span>
                        </div>
                        <div class="h-2.5 w-full max-w-xl overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full rounded-full bg-teal-700" style="width: 74%"></div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="inline-flex items-center rounded-lg border border-blue-600 bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Complete Accounting Setup</button>
                        <button type="button" class="inline-flex items-center rounded-lg border border-orange-300 bg-orange-50 px-3.5 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-100">Review Critical Gaps</button>
                        <a href="{{ $booksUrl }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open Chart of Accounts</a>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-green-200 bg-green-50 p-3">
                        <p class="text-xs text-slate-600">Cash-basis mode</p>
                        <p class="mt-1 text-sm font-semibold text-green-700">Active</p>
                    </div>
                    <div class="rounded-lg border border-orange-200 bg-orange-50 p-3">
                        <p class="text-xs text-slate-600">Maker-checker</p>
                        <p class="mt-1 text-sm font-semibold text-orange-700">Partially configured</p>
                    </div>
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3">
                        <p class="text-xs text-slate-600">Liquidity floors</p>
                        <p class="mt-1 text-sm font-semibold text-red-700">3 accounts missing</p>
                    </div>
                    <div class="rounded-lg border border-orange-200 bg-orange-50 p-3">
                        <p class="text-xs text-slate-600">Tax rules</p>
                        <p class="mt-1 text-sm font-semibold text-orange-700">Needs review</p>
                    </div>
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 sm:col-span-2">
                        <p class="text-xs text-slate-600">Period closing</p>
                        <p class="mt-1 text-sm font-semibold text-red-700">Not configured</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <form method="post" action="{{ $formActionUrl }}" class="space-y-4">
                    @csrf
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-teal-900">Chart of Accounts Approval Control</h2>
                            <p class="mt-1 text-sm text-slate-600">Choose whether new chart of account records become active immediately or pass through sequenced approval.</p>
                        </div>
                        <button type="submit" class="inline-flex rounded-lg border border-blue-600 bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Control</button>
                    </div>

                    <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        <span>Require approval before newly created accounts become active</span>
                        <span>
                            <input type="hidden" name="coa_approval_required" :value="requireApproval ? '1' : '0'" />
                            <input type="checkbox" x-model="requireApproval" class="h-4 w-4 rounded border-slate-300 text-teal-700" />
                        </span>
                    </label>

                    <div x-cloak x-show="requireApproval" class="space-y-3 rounded-xl border border-orange-200 bg-orange-50/60 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-orange-900">Approver Sequence (max 8)</p>
                            <button type="button" @click="addRow()" class="inline-flex rounded-lg border border-orange-300 bg-white px-3 py-1.5 text-xs font-semibold text-orange-700 hover:bg-orange-100">Add Approver Row</button>
                        </div>

                        <template x-for="(row, index) in approverRows" :key="index">
                            <div class="grid grid-cols-1 gap-2 rounded-lg border border-orange-200 bg-white p-3 sm:grid-cols-[120px_1fr_auto] sm:items-center">
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="'Approver ' + (index + 1)"></div>
                                <select :name="'approvers[' + index + '][user_id]'" x-model="row.user_id" class="rounded-lg border-slate-200 text-sm">
                                    <option value="">Select user...</option>
                                    @foreach (($availableApprovers ?? collect()) as $approver)
                                        <option value="{{ $approver->id }}">{{ $approver->name }}{{ $approver->email ? ' ('.$approver->email.')' : '' }}</option>
                                    @endforeach
                                </select>
                                <button type="button" @click="removeRow(index)" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">Remove</button>
                            </div>
                        </template>

                        <p class="text-xs text-orange-800">Approvals follow sequence: Approver 1, then Approver 2, up to Approver 8.</p>
                    </div>
                </form>
            </section>

            <div class="space-y-6">
            <details class="rounded-xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-teal-900">Governance DNA</h2>
                    <p class="mt-1 text-sm text-slate-600">These rules define how every business event flows into the General Ledger.</p>
                </summary>
                <div class="grid grid-cols-1 gap-4 p-5 xl:grid-cols-2">
                    <article class="rounded-xl border border-green-200 bg-green-50/50 p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900">Cash-Basis Revenue Recognition</h3>
                            <span class="rounded-full border border-green-200 bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Active</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">Only recognize income after successful M-Pesa, bank, cash, or wallet confirmation.</p>
                        <p class="mt-1 text-xs text-slate-600">Prevents accrued interest from hitting the P&amp;L before cash is received.</p>
                        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                Enforce Cash-Basis Only
                                <input type="checkbox" checked class="h-4 w-4 rounded border-slate-300 text-teal-700" />
                            </label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                Block Accrued Income Posting
                                <input type="checkbox" checked class="h-4 w-4 rounded border-slate-300 text-teal-700" />
                            </label>
                        </div>
                        <button type="button" class="mt-3 inline-flex rounded-lg border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Configure Revenue Rules</button>
                    </article>

                    <article class="rounded-xl border border-purple-200 bg-purple-50/50 p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900">Maker-Checker Hierarchy</h3>
                            <span class="rounded-full border border-orange-200 bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700">Partially configured</span>
                        </div>
                        <ul class="mt-2 list-disc space-y-1 pl-4 text-sm text-slate-700">
                            <li>Equity Bank or Director Equity journals require Director Approval.</li>
                            <li>Operational expenses above KSh 2,000 require approval.</li>
                        </ul>
                        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-500">
                                Approval Threshold
                                <input type="text" value="KSh 2,000" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                            </label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                Director Approval Required
                                <input type="checkbox" checked class="h-4 w-4 rounded border-slate-300 text-purple-700" />
                            </label>
                        </div>
                        <button type="button" class="mt-3 inline-flex rounded-lg border border-purple-600 bg-purple-600 px-3 py-2 text-sm font-semibold text-white hover:bg-purple-700">Configure Approval Matrix</button>
                    </article>

                    <article class="rounded-xl border border-orange-200 bg-orange-50/50 p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900">Automated Event Mapping</h3>
                            <span class="rounded-full border border-orange-200 bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700">Needs setup</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">Loan Disbursed -&gt; Debit Loan Portfolio (Principal), Credit M-Pesa Bulk Utility.</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">View event mappings</button>
                            <button type="button" class="inline-flex rounded-lg border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add mapping</button>
                            <button type="button" class="inline-flex rounded-lg border border-orange-300 bg-orange-100 px-3 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-200">Validate mapping coverage</button>
                        </div>
                    </article>

                    <article class="rounded-xl border border-red-200 bg-red-50/50 p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900">Period Control</h3>
                            <span class="rounded-full border border-red-200 bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Not configured</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">Soft-close each month and block posting to closed periods unless Director override is granted.</p>
                        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-500">
                                Monthly soft-close day
                                <input type="number" value="28" class="mt-1 w-full rounded-lg border-slate-200 text-sm" />
                            </label>
                            <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                Director override token
                                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-purple-700" />
                            </label>
                        </div>
                        <button type="button" class="mt-3 inline-flex rounded-lg border border-red-600 bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Configure Closing Engine</button>
                    </article>
                </div>
            </details>

            <details class="rounded-xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-teal-900">Chart of Accounts Lockdown</h2>
                </summary>
                <div class="space-y-4 p-5">
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-[980px] w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-2">Account Group</th>
                                    <th class="px-3 py-2">Protection Level</th>
                                    <th class="px-3 py-2">Transaction Posted?</th>
                                    <th class="px-3 py-2">Can Edit?</th>
                                    <th class="px-3 py-2">Can Delete?</th>
                                    <th class="px-3 py-2">Inheritance</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                                @foreach ([
                                    ['name' => 'Assets', 'level' => 'Parent Locked', 'posted' => 'Yes', 'edit' => 'Limited', 'delete' => 'No', 'inherit' => 'Parent to child', 'status' => 'Protected'],
                                    ['name' => 'Loan Portfolio', 'level' => 'Critical', 'posted' => 'Yes', 'edit' => 'No', 'delete' => 'No', 'inherit' => 'Inherited', 'status' => 'Protected'],
                                    ['name' => 'M-Pesa Utility', 'level' => 'Critical', 'posted' => 'Yes', 'edit' => 'No', 'delete' => 'No', 'inherit' => 'Inherited', 'status' => 'Protected'],
                                    ['name' => 'Equity Bank', 'level' => 'Critical', 'posted' => 'Yes', 'edit' => 'No', 'delete' => 'No', 'inherit' => 'Inherited', 'status' => 'Protected'],
                                    ['name' => 'Director Equity', 'level' => 'Director Lock', 'posted' => 'Yes', 'edit' => 'No', 'delete' => 'No', 'inherit' => 'Direct lock', 'status' => 'Director Guard'],
                                    ['name' => 'Fee Income', 'level' => 'Controlled', 'posted' => 'Yes', 'edit' => 'Limited', 'delete' => 'No', 'inherit' => 'Parent to child', 'status' => 'Protected'],
                                    ['name' => 'Operating Expenses', 'level' => 'Controlled', 'posted' => 'Yes', 'edit' => 'Limited', 'delete' => 'No', 'inherit' => 'Parent to child', 'status' => 'Review'],
                                ] as $row)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-3 py-2 font-medium text-slate-900">{{ $row['name'] }}</td>
                                        <td class="px-3 py-2">{{ $row['level'] }}</td>
                                        <td class="px-3 py-2">{{ $row['posted'] }}</td>
                                        <td class="px-3 py-2">{{ $row['edit'] }}</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c1.657 0 3-1.343 3-3V6a3 3 0 10-6 0v2c0 1.657 1.343 3 3 3z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14v9H5z" />
                                                </svg>
                                                {{ $row['delete'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2"><span class="rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-xs font-semibold text-purple-700">{{ $row['inherit'] }}</span></td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $row['status'] === 'Review' ? 'border-orange-200 bg-orange-50 text-orange-700' : 'border-green-200 bg-green-50 text-green-700' }}">{{ $row['status'] }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Review</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $booksUrl }}" class="inline-flex rounded-lg border border-blue-600 bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open COA Manager</a>
                        <button type="button" class="inline-flex rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Review Protected Accounts</button>
                        <button type="button" class="inline-flex rounded-lg border border-purple-300 bg-purple-50 px-3.5 py-2 text-sm font-semibold text-purple-700 hover:bg-purple-100">Add Controlled Account</button>
                    </div>
                </div>
            </details>

            <details class="rounded-xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-teal-900">Liquidity Guardrails</h2>
                    <p class="mt-1 text-sm text-slate-600">Prevent failed disbursements, overdrawing, and technical insolvency.</p>
                </summary>
                <div class="space-y-4 p-5">
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-[920px] w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-2">Account</th>
                                    <th class="px-3 py-2">Current Balance</th>
                                    <th class="px-3 py-2">Minimum Floor</th>
                                    <th class="px-3 py-2">Available Above Floor</th>
                                    <th class="px-3 py-2">Guardrail Action</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-900">M-Pesa Bulk Utility</td>
                                    <td class="px-3 py-2 text-slate-700">KSh 42,000</td>
                                    <td class="px-3 py-2 text-slate-700">KSh 10,000</td>
                                    <td class="px-3 py-2 text-green-700 font-semibold">KSh 32,000</td>
                                    <td class="px-3 py-2 text-slate-700">Disable auto-disbursement below floor</td>
                                    <td class="px-3 py-2"><span class="rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">Safe</span></td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-900">Equity Bank Operating</td>
                                    <td class="px-3 py-2 text-slate-700">KSh 1,250,000</td>
                                    <td class="px-3 py-2 text-slate-700">KSh 50,000</td>
                                    <td class="px-3 py-2 text-green-700 font-semibold">KSh 1,200,000</td>
                                    <td class="px-3 py-2 text-slate-700">Warn before reaching floor</td>
                                    <td class="px-3 py-2"><span class="rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">Safe</span></td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-900">Petty Cash Float</td>
                                    <td class="px-3 py-2 text-slate-700">KSh 6,400</td>
                                    <td class="px-3 py-2 text-slate-700">KSh 8,000</td>
                                    <td class="px-3 py-2 text-red-700 font-semibold">-KSh 1,600</td>
                                    <td class="px-3 py-2 text-slate-700">Block outgoing reimbursement</td>
                                    <td class="px-3 py-2"><span class="rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Below floor</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">Enforce Liquidity Floors <input type="checkbox" checked class="h-4 w-4 rounded border-slate-300 text-teal-700" /></label>
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">Block Auto-Disbursement Below Floor <input type="checkbox" checked class="h-4 w-4 rounded border-slate-300 text-teal-700" /></label>
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">Warn Before Reaching Floor <input type="checkbox" checked class="h-4 w-4 rounded border-slate-300 text-teal-700" /></label>
                        <button type="button" class="inline-flex justify-center rounded-lg border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Configure Floor Rules</button>
                    </div>
                </div>
            </details>

            <details class="rounded-xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-teal-900">Statutory &amp; Tax Configuration</h2>
                    <p class="mt-1 text-sm text-slate-600">Maintain a shadow tax ledger so management always sees upcoming statutory obligations.</p>
                </summary>
                <div class="space-y-4 p-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ([
                            ['name' => 'Excise Duty on fees', 'rate' => '15%', 'date' => '2026-01-01', 'ledger' => 'Excise Duty Payable', 'due' => '2026-04-30', 'status' => 'Configured'],
                            ['name' => 'PAYE', 'rate' => 'Graduated bands', 'date' => '2026-01-01', 'ledger' => 'PAYE Control', 'due' => '2026-05-09', 'status' => 'Configured'],
                            ['name' => 'VAT', 'rate' => '16%', 'date' => '2026-01-01', 'ledger' => 'VAT Output', 'due' => '2026-05-20', 'status' => 'Needs review'],
                            ['name' => 'Corporation Tax', 'rate' => '30%', 'date' => '2026-01-01', 'ledger' => 'Corporate Tax Provision', 'due' => '2026-06-30', 'status' => 'Draft'],
                            ['name' => 'NSSF / NHIF / SHIF', 'rate' => 'Statutory tables', 'date' => '2026-01-01', 'ledger' => 'Payroll Statutory Control', 'due' => '2026-05-09', 'status' => 'Configured'],
                        ] as $tax)
                            <article class="rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $tax['name'] }}</h3>
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $tax['status'] === 'Configured' ? 'border-green-200 bg-green-50 text-green-700' : ($tax['status'] === 'Needs review' ? 'border-orange-200 bg-orange-50 text-orange-700' : 'border-purple-200 bg-purple-50 text-purple-700') }}">{{ $tax['status'] }}</span>
                                </div>
                                <dl class="mt-3 space-y-1 text-xs text-slate-600">
                                    <div class="flex justify-between gap-3"><dt>Rate / Rule</dt><dd class="font-medium text-slate-800">{{ $tax['rate'] }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt>Effective date</dt><dd class="font-medium text-slate-800">{{ $tax['date'] }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt>Ledger account</dt><dd class="font-medium text-slate-800">{{ $tax['ledger'] }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt>Next due date</dt><dd class="font-medium text-orange-700">{{ $tax['due'] }}</dd></div>
                                </dl>
                                <button type="button" class="mt-3 inline-flex rounded-lg border border-blue-600 bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Configure</button>
                            </article>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs text-slate-600">Estimated upcoming tax liability</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">KSh 364,200</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600">Next statutory deadline</p>
                            <p class="mt-1 text-sm font-semibold text-orange-700">PAYE filing - 2026-05-09</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600">Missing tax mappings</p>
                            <p class="mt-1 text-sm font-semibold text-purple-700">2 mappings pending validation</p>
                        </div>
                    </div>
                </div>
            </details>

            <details class="rounded-xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-teal-900">Automated Business Event -&gt; Ledger Mapping</h2>
                </summary>
                <div class="space-y-4 p-5">
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-[1120px] w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-2">Business Event</th>
                                    <th class="px-3 py-2">Debit Account</th>
                                    <th class="px-3 py-2">Credit Account</th>
                                    <th class="px-3 py-2">Trigger Source</th>
                                    <th class="px-3 py-2">Approval Rule</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ([
                                    ['event' => 'Loan Disbursed', 'debit' => 'Loan Portfolio (Principal)', 'credit' => 'M-Pesa Bulk Utility', 'source' => 'Disbursement engine', 'approval' => 'Checker required', 'status' => 'Active'],
                                    ['event' => 'Processing Fee Received', 'debit' => 'M-Pesa Utility', 'credit' => 'Fee Income', 'source' => 'Receipt postback', 'approval' => 'Auto-post', 'status' => 'Active'],
                                    ['event' => 'Interest Received', 'debit' => 'M-Pesa Utility', 'credit' => 'Interest Income', 'source' => 'Repayment settlement', 'approval' => 'Auto-post', 'status' => 'Active'],
                                    ['event' => 'Penalty Received', 'debit' => 'M-Pesa Utility', 'credit' => 'Penalty Income', 'source' => 'Penalty scheduler', 'approval' => 'Auto-post', 'status' => 'Draft'],
                                    ['event' => 'Salary Paid', 'debit' => 'Salaries Expense', 'credit' => 'Bank Account', 'source' => 'Payroll run', 'approval' => 'Director required', 'status' => 'Needs Approval'],
                                    ['event' => 'M-Pesa Reversal', 'debit' => 'Suspense Reversal', 'credit' => 'M-Pesa Utility', 'source' => 'Reversal callback', 'approval' => 'Dual authorization', 'status' => 'Missing Account'],
                                ] as $row)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-3 py-2 font-medium text-slate-900">{{ $row['event'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $row['debit'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $row['credit'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $row['source'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $row['approval'] }}</td>
                                        <td class="px-3 py-2">
                                            @php
                                                $statusClass = match ($row['status']) {
                                                    'Active' => 'border-green-200 bg-green-50 text-green-700',
                                                    'Draft' => 'border-slate-200 bg-slate-50 text-slate-700',
                                                    'Needs Approval' => 'border-purple-200 bg-purple-50 text-purple-700',
                                                    default => 'border-red-200 bg-red-50 text-red-700',
                                                };
                                            @endphp
                                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $row['status'] }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <button type="button" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</button>
                                                <button type="button" class="rounded-lg border border-slate-300 bg-white p-1.5 text-slate-600 hover:bg-slate-50" title="Audit history">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1018 0 9 9 0 00-18 0z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="inline-flex rounded-lg border border-orange-300 bg-orange-50 px-3.5 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-100">Validate All Mappings</button>
                        <button type="button" class="inline-flex rounded-lg border border-blue-600 bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add New Event Mapping</button>
                    </div>
                </div>
            </details>

            <details class="rounded-xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-teal-900">Maker-Checker Approval Matrix</h2>
                </summary>
                <div class="p-5">
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-[980px] w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-2">Transaction Type</th>
                                    <th class="px-3 py-2">Amount Threshold</th>
                                    <th class="px-3 py-2">Maker Role</th>
                                    <th class="px-3 py-2">Checker Role</th>
                                    <th class="px-3 py-2">Director Required?</th>
                                    <th class="px-3 py-2">Auto-post Allowed?</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ([
                                    ['type' => 'Operational Expense', 'threshold' => '&lt; KSh 2,000', 'maker' => 'Accountant', 'checker' => 'System Rule', 'director' => 'No', 'auto' => 'Yes', 'status' => 'Active'],
                                    ['type' => 'Operational Expense', 'threshold' => '&gt; KSh 2,000', 'maker' => 'Accountant', 'checker' => 'Finance Manager', 'director' => 'Yes', 'auto' => 'No', 'status' => 'Active'],
                                    ['type' => 'Director Equity Journal', 'threshold' => 'Any amount', 'maker' => 'Senior Accountant', 'checker' => 'Director', 'director' => 'Yes', 'auto' => 'No', 'status' => 'Active'],
                                    ['type' => 'Bank to M-Pesa transfer', 'threshold' => 'Any amount', 'maker' => 'Treasury Officer', 'checker' => 'Finance Manager', 'director' => 'No', 'auto' => 'No', 'status' => 'Needs Review'],
                                    ['type' => 'Reversal entry', 'threshold' => 'Any amount', 'maker' => 'Accountant', 'checker' => 'Finance Manager', 'director' => 'Yes', 'auto' => 'No', 'status' => 'Active'],
                                ] as $row)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-3 py-2 font-medium text-slate-900">{{ $row['type'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{!! $row['threshold'] !!}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $row['maker'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $row['checker'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $row['director'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $row['auto'] }}</td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $row['status'] === 'Active' ? 'border-green-200 bg-green-50 text-green-700' : 'border-orange-200 bg-orange-50 text-orange-700' }}">{{ $row['status'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-teal-900">Accounting Health Check</h2>
                        <p class="mt-1 text-sm text-slate-600">Detected setup gaps requiring immediate closure.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="inline-flex rounded-lg border border-blue-600 bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Run Accounting Validation</button>
                        <button type="button" class="inline-flex rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Download Setup Audit Report</button>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    @foreach ([
                        ['gap' => '3 accounts missing minimum balance floors', 'module' => 'Liquidity Guardrails', 'severity' => 'Critical'],
                        ['gap' => '2 business events missing ledger mapping', 'module' => 'Event Mapping', 'severity' => 'High'],
                        ['gap' => 'Tax ledger not fully configured', 'module' => 'Statutory Tax', 'severity' => 'Medium'],
                        ['gap' => 'Period close rule not active', 'module' => 'Governance DNA', 'severity' => 'Critical'],
                        ['gap' => '1 approval path missing checker role', 'module' => 'Approval Matrix', 'severity' => 'High'],
                    ] as $item)
                        <article class="rounded-lg border border-slate-200 bg-slate-50 p-3 hover:bg-slate-100/70">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $item['gap'] }}</p>
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $item['severity'] === 'Critical' ? 'border-red-200 bg-red-50 text-red-700' : ($item['severity'] === 'High' ? 'border-orange-200 bg-orange-50 text-orange-700' : 'border-purple-200 bg-purple-50 text-purple-700') }}">{{ $item['severity'] }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <p class="text-xs text-slate-600">Affected module: {{ $item['module'] }}</p>
                                <button type="button" class="rounded-lg border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100">Fix</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
