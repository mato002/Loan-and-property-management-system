<x-loan-layout>
    <div class="mx-auto w-full max-w-[1400px] px-3 pb-6 pt-2 sm:px-4 lg:px-6">
        @include('loan.accounting.partials.flash')

        @php
            $roleCards = [
                ['id' => 'director', 'name' => 'Director', 'level' => 'Level 10', 'description' => 'Full system access. Can view, create, edit, delete, approve and configure all modules.'],
                ['id' => 'finance_manager', 'name' => 'Finance Manager', 'level' => 'Level 9', 'description' => 'Finance control with accounting and treasury approvals.'],
                ['id' => 'branch_manager', 'name' => 'Branch Manager', 'level' => 'Level 8', 'description' => 'Branch-level supervision, approvals, and performance oversight.'],
                ['id' => 'senior_loan_officer', 'name' => 'Senior Loan Officer', 'level' => 'Level 6', 'description' => 'Loan supervision and senior underwriting support.'],
                ['id' => 'loan_officer', 'name' => 'Loan Officer', 'level' => 'Level 5', 'description' => 'Client onboarding, application prep and portfolio servicing.'],
                ['id' => 'collection_officer', 'name' => 'Collection Officer', 'level' => 'Level 4', 'description' => 'Collections execution and arrears follow-up.'],
                ['id' => 'cashier', 'name' => 'Cashier', 'level' => 'Level 3', 'description' => 'Cash movement recording and teller controls.'],
                ['id' => 'auditor', 'name' => 'Auditor', 'level' => 'Level 2', 'description' => 'Read-only audit evidence and compliance checks.'],
                ['id' => 'system_admin', 'name' => 'System Admin', 'level' => 'Level 10', 'description' => 'Platform configuration and system administration.'],
            ];

            $permissionRows = [
                ['module' => 'Clients', 'icon' => 'users', 'states' => ['allow', 'allow', 'allow', 'allow', 'allow', 'allow', 'deny', 'not_set']],
                ['module' => 'Loan Applications', 'icon' => 'file', 'states' => ['allow', 'allow', 'allow', 'allow', 'deny', 'allow', 'deny', 'not_set']],
                ['module' => 'Loans', 'icon' => 'briefcase', 'states' => ['allow', 'allow', 'allow', 'deny', 'allow', 'allow', 'deny', 'not_set']],
                ['module' => 'Disbursements', 'icon' => 'send', 'states' => ['allow', 'allow', 'allow', 'deny', 'allow', 'allow', 'deny', 'not_set']],
                ['module' => 'Collections', 'icon' => 'collection', 'states' => ['allow', 'allow', 'allow', 'deny', 'allow', 'allow', 'deny', 'not_set']],
                ['module' => 'Payments', 'icon' => 'wallet', 'states' => ['allow', 'allow', 'allow', 'deny', 'allow', 'allow', 'deny', 'not_set']],
                ['module' => 'Wallets', 'icon' => 'wallet', 'states' => ['allow', 'allow', 'allow', 'deny', 'allow', 'allow', 'deny', 'not_set']],
                ['module' => 'Accounting', 'icon' => 'calculator', 'states' => ['allow', 'not_set', 'not_set', 'not_set', 'allow', 'allow', 'deny', 'allow']],
                ['module' => 'Journals', 'icon' => 'journal', 'states' => ['allow', 'not_set', 'not_set', 'not_set', 'allow', 'allow', 'deny', 'allow']],
                ['module' => 'Chart of Accounts', 'icon' => 'chart', 'states' => ['allow', 'deny', 'deny', 'deny', 'deny', 'deny', 'deny', 'allow']],
                ['module' => 'Automated Cash Mappings', 'icon' => 'mapping', 'states' => ['allow', 'deny', 'deny', 'deny', 'deny', 'deny', 'deny', 'allow']],
                ['module' => 'Reports', 'icon' => 'report', 'states' => ['allow', 'allow', 'allow', 'allow', 'allow', 'allow', 'allow', 'not_set']],
                ['module' => 'System Setup', 'icon' => 'settings', 'states' => ['allow', 'deny', 'deny', 'deny', 'deny', 'deny', 'deny', 'allow']],
                ['module' => 'Audit Logs', 'icon' => 'shield', 'states' => ['allow', 'not_set', 'not_set', 'not_set', 'allow', 'allow', 'not_set', 'not_set']],
            ];
        @endphp

        <div
            class="rounded-xl border border-slate-200 bg-[#f7f9fb] p-4 sm:p-5 lg:p-6"
            x-data="{
                activeTab: 'roles',
                selectedRole: 'director',
                createRoleOpen: @js(old('form_context') === 'create_role'),
                matrixRows: @js($permissionRows),
                activeStates: ['allow', 'deny', 'not_set'],
                bulkAction: '',
                roleDetails: {
                    director: {
                        name: 'Director',
                        level: 'Level 10',
                        description: 'Full system access with board-level governance accountability.',
                        dataScope: 'All Branches (Global)',
                        approvalLimit: 'Unlimited',
                        canApprove: 'All Transactions',
                        maker: 'Yes',
                        checker: 'Yes',
                        ipRestriction: 'No Restriction',
                        active: true
                    },
                    finance_manager: {
                        name: 'Finance Manager',
                        level: 'Level 9',
                        description: 'Accounting and treasury manager with high-risk approval coverage.',
                        dataScope: 'Finance Departments + Branch Finance',
                        approvalLimit: 'KES 10,000,000',
                        canApprove: 'Accounting & Treasury',
                        maker: 'Yes',
                        checker: 'Yes',
                        ipRestriction: 'Restricted',
                        active: true
                    },
                    branch_manager: {
                        name: 'Branch Manager',
                        level: 'Level 8',
                        description: 'Controls branch operations, collections, and supervised approvals.',
                        dataScope: 'Assigned Branch',
                        approvalLimit: 'KES 2,000,000',
                        canApprove: 'Branch Transactions',
                        maker: 'Yes',
                        checker: 'Yes',
                        ipRestriction: 'Restricted',
                        active: true
                    },
                    senior_loan_officer: {
                        name: 'Senior Loan Officer',
                        level: 'Level 6',
                        description: 'Senior loan processing and quality assurance.',
                        dataScope: 'Assigned Portfolio + Branch',
                        approvalLimit: 'KES 500,000',
                        canApprove: 'Escalated Credit Actions',
                        maker: 'Yes',
                        checker: 'No',
                        ipRestriction: 'Restricted',
                        active: true
                    },
                    loan_officer: {
                        name: 'Loan Officer',
                        level: 'Level 5',
                        description: 'Creates and services loans; cannot approve own applications.',
                        dataScope: 'Assigned Clients',
                        approvalLimit: 'KES 0 (Maker Only)',
                        canApprove: 'None (Checker Required)',
                        maker: 'Yes',
                        checker: 'No',
                        ipRestriction: 'Restricted',
                        active: true
                    },
                    collection_officer: {
                        name: 'Collection Officer',
                        level: 'Level 4',
                        description: 'Collections follow-up with limited posting rights.',
                        dataScope: 'Assigned Delinquency Queues',
                        approvalLimit: 'KES 0',
                        canApprove: 'None',
                        maker: 'Yes',
                        checker: 'No',
                        ipRestriction: 'Restricted',
                        active: true
                    },
                    cashier: {
                        name: 'Cashier',
                        level: 'Level 3',
                        description: 'Records payments; cannot reverse own payments.',
                        dataScope: 'Teller Station + Assigned Branch',
                        approvalLimit: 'KES 0',
                        canApprove: 'None',
                        maker: 'Yes',
                        checker: 'No',
                        ipRestriction: 'Restricted',
                        active: true
                    },
                    auditor: {
                        name: 'Auditor',
                        level: 'Level 2',
                        description: 'Read-only governance visibility for audit and compliance.',
                        dataScope: 'All Branches (Read-only)',
                        approvalLimit: 'N/A',
                        canApprove: 'No',
                        maker: 'No',
                        checker: 'No',
                        ipRestriction: 'Restricted',
                        active: true
                    },
                    system_admin: {
                        name: 'System Admin',
                        level: 'Level 10',
                        description: 'Platform setup and policy configuration without financial approvals by default.',
                        dataScope: 'Global',
                        approvalLimit: 'No Financial Approval by Default',
                        canApprove: 'System Configuration Changes',
                        maker: 'Yes',
                        checker: 'Yes',
                        ipRestriction: 'Restricted',
                        active: true
                    }
                },
                roleStateClass(state) {
                    if (state === 'allow') return 'text-emerald-600';
                    if (state === 'deny') return 'text-rose-600';
                    return 'text-slate-400';
                },
                cycleState(rowIndex, colIndex) {
                    const current = this.matrixRows[rowIndex].states[colIndex];
                    const nextIndex = (this.activeStates.indexOf(current) + 1) % this.activeStates.length;
                    this.matrixRows[rowIndex].states[colIndex] = this.activeStates[nextIndex];
                },
                applyBulk() {
                    if (this.bulkAction === '') return;
                    const allowed = ['allow', 'deny', 'not_set'];
                    if (!allowed.includes(this.bulkAction)) return;
                    this.matrixRows = this.matrixRows.map((row) => ({
                        ...row,
                        states: row.states.map(() => this.bulkAction),
                    }));
                },
                showComingSoon(message = 'Coming soon') {
                    this.toastMessage = message;
                    this.toastOpen = true;
                    setTimeout(() => { this.toastOpen = false; }, 1800);
                },
                iconMarkup(state) {
                    if (state === 'allow') {
                        return '<svg class=\'h-4 w-4\' viewBox=\'0 0 20 20\' fill=\'none\'><circle cx=\'10\' cy=\'10\' r=\'8\' fill=\'currentColor\' fill-opacity=\'.12\'/><path d=\'M6 10.2l2.4 2.5L14 7.6\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/></svg>';
                    }
                    if (state === 'deny') {
                        return '<svg class=\'h-4 w-4\' viewBox=\'0 0 20 20\' fill=\'none\'><circle cx=\'10\' cy=\'10\' r=\'8\' fill=\'currentColor\' fill-opacity=\'.12\'/><path d=\'M6.5 6.5l7 7m0-7l-7 7\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\'/></svg>';
                    }
                    return '<svg class=\'h-4 w-4\' viewBox=\'0 0 20 20\' fill=\'none\'><path d=\'M6 10h8\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\'/></svg>';
                },
                toastOpen: false,
                toastMessage: ''
            }"
        >
            @if (!($rbacReady ?? false))
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Roles tables are not ready. Run <span class="font-semibold">php artisan migrate</span> to enable role persistence.
                </div>
            @endif

            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">User Roles &amp; Access Control</h1>
                        <p class="mt-1 text-sm text-slate-600">Manage roles, permissions, data scope and approval authority</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                        <select class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-700">
                            <option>Fortress Lenders Ltd</option>
                            <option>Branch Operations Unit</option>
                        </select>
                        <button class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50" type="button" aria-label="Notifications" @click="showComingSoon('Notifications center coming soon')">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none">
                                <path d="M10 3a4 4 0 00-4 4v1.8c0 .7-.2 1.4-.6 1.9L4 13h12l-1.4-2.3a3.7 3.7 0 01-.6-1.9V7a4 4 0 00-4-4z" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M8.2 14.5a1.8 1.8 0 003.6 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span class="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">5</span>
                        </button>
                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-600">{{ now()->format('D, M d, Y h:i A') }}</span>
                    </div>
                </div>

                <div class="mt-4 border-b border-slate-200">
                    <nav class="-mb-px flex flex-wrap gap-2 text-sm">
                        <button type="button" @click="activeTab = 'roles'" :class="activeTab === 'roles' ? 'border-b-2 border-teal-700 text-teal-700' : 'text-slate-500'" class="px-2 pb-2 font-medium">Roles</button>
                        <button type="button" @click="activeTab = 'permission_matrix'" :class="activeTab === 'permission_matrix' ? 'border-b-2 border-teal-700 text-teal-700' : 'text-slate-500'" class="px-2 pb-2 font-medium">Permission Matrix</button>
                        <button type="button" @click="activeTab = 'data_scope'" :class="activeTab === 'data_scope' ? 'border-b-2 border-teal-700 text-teal-700' : 'text-slate-500'" class="px-2 pb-2 font-medium">Data Scope</button>
                        <button type="button" @click="activeTab = 'maker_checker'" :class="activeTab === 'maker_checker' ? 'border-b-2 border-teal-700 text-teal-700' : 'text-slate-500'" class="px-2 pb-2 font-medium">Maker-Checker Rules</button>
                        <button type="button" @click="activeTab = 'access_policies'" :class="activeTab === 'access_policies' ? 'border-b-2 border-teal-700 text-teal-700' : 'text-slate-500'" class="px-2 pb-2 font-medium">Access Policies</button>
                        <button type="button" @click="activeTab = 'audit_preview'" :class="activeTab === 'audit_preview' ? 'border-b-2 border-teal-700 text-teal-700' : 'text-slate-500'" class="px-2 pb-2 font-medium">Audit Preview</button>
                    </nav>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-4 xl:flex-row xl:items-start" x-show="activeTab === 'roles'">
                <div class="min-w-0 flex-1 space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Select Role to Edit</h2>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="post" action="{{ route('loan.system.setup.access_roles.clone', ($roles->first() ?? 0)) }}" class="flex items-center gap-2" x-data="{ cloneRoleId: '{{ (string) ($roles->first()->id ?? 0) }}' }" :action="'{{ url('/loan/system-help/setup/access-roles') }}/' + cloneRoleId + '/clone'">
                                    @csrf
                                    <select x-model="cloneRoleId" class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs text-slate-700">
                                        @foreach (($roles ?? collect()) as $cloneRole)
                                            <option value="{{ $cloneRole->id }}">{{ $cloneRole->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @disabled(($roles ?? collect())->isEmpty())>
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path d="M4 10h12M10 4v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        Clone Role
                                    </button>
                                </form>
                                <button type="button" @click="createRoleOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-800 disabled:opacity-60" @disabled(!($rbacReady ?? false))>
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none"><path d="M4 10h12M10 4v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    New Role
                                </button>
                                <form method="post" action="{{ route('loan.system.setup.access_roles.sync') }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100" @disabled(!($rbacReady ?? false))>Sync roles</button>
                                </form>
                            </div>
                        </div>

                        <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                            Permissions are role-based and follow the principle of least privilege. Changes are logged for audit and require authorized approval.
                        </div>

                        <div class="flex gap-3 overflow-x-auto pb-2">
                            @foreach ($roleCards as $card)
                                <button
                                    type="button"
                                    @click="selectedRole = '{{ $card['id'] }}'"
                                    :class="selectedRole === '{{ $card['id'] }}' ? 'border-emerald-500 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-white text-slate-700'"
                                    class="min-w-[155px] shrink-0 rounded-xl border px-3 py-3 text-left transition"
                                >
                                    <div class="text-xs font-semibold">{{ $card['name'] }}</div>
                                    <div class="mt-1 text-[11px] text-slate-500">{{ $card['level'] }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">Permission Matrix</h3>
                                <p class="text-xs text-slate-500">Toggle permissions for the selected role across all modules.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-emerald-700">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none"><path d="M6 10.2l2.4 2.5L14 7.6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Allow
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-rose-700">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none"><path d="M6.5 6.5l7 7m0-7l-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Deny
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-slate-500">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none"><path d="M6 10h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Not Set
                                </span>
                                <select x-model="bulkAction" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700">
                                    <option value="">Bulk Actions</option>
                                    <option value="allow">Set all Allow</option>
                                    <option value="deny">Set all Deny</option>
                                    <option value="not_set">Set all Not Set</option>
                                </select>
                                <button type="button" @click="applyBulk()" class="rounded-md border border-slate-300 px-2 py-1 font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-[900px] w-full text-xs">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="sticky left-0 z-10 min-w-[190px] bg-slate-50 px-3 py-2 text-left font-semibold">Modules</th>
                                        <th class="px-3 py-2 font-semibold">View</th>
                                        <th class="px-3 py-2 font-semibold">Create</th>
                                        <th class="px-3 py-2 font-semibold">Update</th>
                                        <th class="px-3 py-2 font-semibold">Delete</th>
                                        <th class="px-3 py-2 font-semibold">Approve</th>
                                        <th class="px-3 py-2 font-semibold">Export</th>
                                        <th class="px-3 py-2 font-semibold">Reverse</th>
                                        <th class="px-3 py-2 font-semibold">Configure</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(row, rowIndex) in matrixRows" :key="row.module">
                                        <tr class="hover:bg-slate-50/70">
                                            <td class="sticky left-0 bg-white px-3 py-2 text-slate-700" x-text="row.module"></td>
                                            <template x-for="(state, colIndex) in row.states" :key="colIndex">
                                                <td class="px-3 py-2 text-center">
                                                    <button type="button" @click="cycleState(rowIndex, colIndex)" :class="roleStateClass(state)" class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-transparent hover:border-slate-200" x-html="iconMarkup(state)"></button>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-xs text-purple-900">
                            Rule controls: System Admin can configure system setup but should not auto-approve financial transactions. Loan Officer cannot approve own applications. Cashier cannot reverse own payments. Director/CPA may approve sensitive accounting actions. Maker cannot be checker for the same transaction.
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Access Policies Summary</h3>
                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-xs font-semibold text-slate-800">Session Timeout</p>
                                <p class="mt-1 text-xs text-slate-500">10 minutes inactivity</p>
                                <p class="text-xs text-slate-500">Auto logout enabled</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-xs font-semibold text-slate-800">High-Risk Pages Timeout</p>
                                <p class="mt-1 text-xs text-slate-500">Cash Mapping, M-Pesa Settings, Journals, COA Rules, User Roles</p>
                                <p class="text-xs text-slate-500">Timeout in 10 minutes</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-xs font-semibold text-slate-800">IP Restriction</p>
                                <p class="mt-1 text-xs text-slate-500">Enforced for sensitive modules</p>
                                <p class="text-xs text-slate-500">Multiple IP ranges allowed</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-xs font-semibold text-slate-800">Login Time Window</p>
                                <p class="mt-1 text-xs text-slate-500">Mon-Fri: 06:00 AM-08:00 PM</p>
                                <p class="text-xs text-slate-500">Sat: 06:00 AM-02:00 PM</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-xs font-semibold text-slate-800">Zero Shared Credentials</p>
                                <p class="mt-1 text-xs text-slate-500">Every human must have unique credentials</p>
                            </div>
                        </div>
                        <form method="post" action="{{ route('loan.system.setup.access_roles.security_policies.update') }}" class="mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                            @csrf
                            <p class="text-xs font-semibold text-slate-700">Policy toggles & JSON config</p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input type="hidden" name="device_governance_enabled" value="0">
                                    <input type="checkbox" name="device_governance_enabled" value="1" class="rounded border-slate-300" @checked(($securityPolicySettings['device_governance_enabled'] ?? '0') === '1')>
                                    Device governance ON
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input type="hidden" name="role_login_windows_enabled" value="0">
                                    <input type="checkbox" name="role_login_windows_enabled" value="1" class="rounded border-slate-300" @checked(($securityPolicySettings['role_login_windows_enabled'] ?? '0') === '1')>
                                    Role login windows ON
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input type="hidden" name="ip_restrictions_enabled" value="0">
                                    <input type="checkbox" name="ip_restrictions_enabled" value="1" class="rounded border-slate-300" @checked(($securityPolicySettings['ip_restrictions_enabled'] ?? '0') === '1')>
                                    IP restrictions ON
                                </label>
                            </div>
                            <div class="grid grid-cols-1 gap-2 lg:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-slate-600">Global IP allowlist JSON</label>
                                    <textarea name="ip_allowlist_json" rows="4" class="w-full rounded-lg border-slate-200 text-xs">{{ $securityPolicySettings['ip_allowlist_json'] ?? '[]' }}</textarea>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-slate-600">Role IP overrides JSON</label>
                                    <textarea name="role_ip_overrides_json" rows="4" class="w-full rounded-lg border-slate-200 text-xs">{{ $securityPolicySettings['role_ip_overrides_json'] ?? '{}' }}</textarea>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-slate-600">Role login windows JSON</label>
                                    <textarea name="role_login_windows_json" rows="4" class="w-full rounded-lg border-slate-200 text-xs">{{ $securityPolicySettings['role_login_windows_json'] ?? '{}' }}</textarea>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Save policies</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="w-full space-y-4 xl:w-[360px] 2xl:w-[390px] xl:shrink-0">
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-800">Reporting Hierarchy</h3>
                            <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-1 text-[11px]">
                                <button type="button" class="rounded px-2 py-1 bg-white text-slate-700 shadow-sm">Tree View</button>
                                <button type="button" class="rounded px-2 py-1 text-slate-500" @click="showComingSoon('Hierarchy list view coming soon')">List View</button>
                            </div>
                        </div>
                        <div class="space-y-2 text-xs text-slate-700">
                            <div class="font-semibold text-slate-800">Board of Directors</div>
                            <div class="ml-3 border-l border-slate-200 pl-3">
                                <div class="font-medium">Director</div>
                                <div class="ml-3 mt-1 border-l border-slate-200 pl-3">
                                    <div>Finance Manager / CPA</div>
                                    <div>Branch Manager</div>
                                    <div class="ml-3 mt-1 border-l border-slate-200 pl-3 space-y-1">
                                        <div>Senior Loan Officer</div>
                                        <div>Loan Officer</div>
                                        <div>Collection Officer</div>
                                        <div>Cashier</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-purple-800">Auditor (Read Only)</div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-slate-800">Role Details</h3>
                        <dl class="mt-3 space-y-2 text-xs">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Role Name</dt><dd class="font-semibold text-slate-800" x-text="roleDetails[selectedRole].name"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Access Level</dt><dd class="font-semibold text-amber-700" x-text="roleDetails[selectedRole].level"></dd></div>
                            <div class="space-y-1"><dt class="text-slate-500">Description</dt><dd class="text-slate-700" x-text="roleDetails[selectedRole].description"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Data Scope</dt><dd class="text-slate-700" x-text="roleDetails[selectedRole].dataScope"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Approval Limit</dt><dd class="text-slate-700" x-text="roleDetails[selectedRole].approvalLimit"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Can Approve</dt><dd class="text-emerald-700" x-text="roleDetails[selectedRole].canApprove"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Maker Permissions</dt><dd class="text-slate-700" x-text="roleDetails[selectedRole].maker"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Checker Permissions</dt><dd class="text-slate-700" x-text="roleDetails[selectedRole].checker"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">IP Restriction</dt><dd class="text-slate-700" x-text="roleDetails[selectedRole].ipRestriction"></dd></div>
                            <div class="flex items-center justify-between gap-3 pt-1">
                                <dt class="text-slate-500">Active</dt>
                                <button type="button" class="relative h-6 w-11 rounded-full bg-emerald-500" @click="showComingSoon('Role active toggle integration coming soon')">
                                    <span class="absolute right-0.5 top-0.5 h-5 w-5 rounded-full bg-white"></span>
                                </button>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-800">Temporary Access Requests</h3>
                            <span class="text-xs text-slate-500">Role-threshold approval</span>
                        </div>
                        <div class="space-y-2 text-xs max-h-64 overflow-y-auto">
                            @forelse(($temporaryAccessRequests ?? collect()) as $req)
                                @php
                                    $tone = $req->status === 'approved' ? 'emerald' : ($req->status === 'rejected' ? 'rose' : 'amber');
                                @endphp
                                <div class="rounded-lg border border-slate-200 p-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-semibold text-slate-800">{{ $req->requester?->name ?? 'User' }}</p>
                                        <span class="rounded-full bg-{{ $tone }}-100 px-2 py-0.5 text-[10px] font-semibold text-{{ $tone }}-800">{{ ucfirst((string) $req->status) }}</span>
                                    </div>
                                    <p class="text-slate-500">{{ $req->permission_key }} @if($req->amount_limit) · limit {{ number_format((float) $req->amount_limit, 2) }} @endif</p>
                                    <p class="text-[10px] text-slate-400">{{ optional($req->created_at)->diffForHumans() }}</p>
                                    @if($req->status === 'pending')
                                        <form method="post" action="{{ route('loan.system.setup.access_roles.temporary_access.decision', $req) }}" class="mt-2 flex items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button class="rounded border border-emerald-300 bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">Approve</button>
                                        </form>
                                        <form method="post" action="{{ route('loan.system.setup.access_roles.temporary_access.decision', $req) }}" class="mt-1 flex items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject">
                                            <button class="rounded border border-rose-300 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700">Reject</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-slate-300 p-3 text-slate-500">No temporary access requests yet.</div>
                            @endforelse
                        </div>
                        <form method="post" action="{{ route('loan.system.setup.access_roles.temporary_access.store') }}" class="mt-3 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            @csrf
                            <label class="block text-[11px] font-semibold text-slate-700">Request temporary elevation</label>
                            <select name="permission_key" class="w-full rounded-lg border-slate-200 text-xs">
                                @foreach ($permissionCatalog as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                            <input name="amount_limit" type="number" step="0.01" min="0" placeholder="Amount limit (optional)" class="w-full rounded-lg border-slate-200 text-xs">
                            <textarea name="reason" rows="2" class="w-full rounded-lg border-slate-200 text-xs" placeholder="Reason"></textarea>
                            <button class="w-full rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Submit temporary request</button>
                        </form>
                        <form method="post" class="mt-2 space-y-2 rounded-lg border border-purple-200 bg-purple-50 p-3" x-data="{ targetUserId: '{{ (string) auth()->id() }}' }" :action="'{{ url('/loan/system-help/setup/access-roles/devices') }}/' + targetUserId + '/unbind'">
                            @csrf
                            <label class="block text-[11px] font-semibold text-purple-800">Master Key: Unbind trusted device</label>
                            <select x-model="targetUserId" class="w-full rounded-lg border-purple-200 text-xs">
                                @foreach (($users ?? collect()) as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            <button class="w-full rounded-lg border border-purple-300 bg-white px-3 py-2 text-xs font-semibold text-purple-700 hover:bg-purple-100">Unbind selected user device(s)</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600" x-show="activeTab !== 'roles'">
                <p class="font-semibold text-slate-800" x-text="activeTab.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())"></p>
                <p class="mt-2">Coming soon. This section is queued for backend integration and will be functional in the next increment.</p>
                <button type="button" class="mt-3 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100" @click="activeTab = 'roles'">Back to Roles</button>
            </div>

            <div class="fixed bottom-4 right-4 z-50" x-show="toastOpen" x-cloak>
                <div class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-lg" x-text="toastMessage"></div>
            </div>

            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-4"
                x-show="createRoleOpen"
                x-cloak
                @keydown.escape.window="createRoleOpen = false"
            >
                <div
                    class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-2xl"
                    x-data="{
                        defaults: @js($defaultPermissionsByBaseRole ?? []),
                        createBaseRole: @js(old('base_role', 'admin')),
                        createPerms: @js(old('permissions', [])),
                        hasOldPerms: @js(is_array(old('permissions')) && count(old('permissions')) > 0),
                        applyCreateDefaults() { this.createPerms = [...(this.defaults[this.createBaseRole] || [])]; }
                    }"
                    x-init="if (!hasOldPerms) applyCreateDefaults()"
                    @click.away="createRoleOpen = false"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-700">Create role</h2>
                        <button type="button" class="rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700" @click="createRoleOpen = false">Close</button>
                    </div>
                    <form method="post" action="{{ route('loan.system.setup.access_roles.store') }}" class="space-y-4 p-5">
                        @csrf
                        <input type="hidden" name="form_context" value="create_role">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Role name</label>
                            <input name="name" value="{{ old('name') }}" class="w-full rounded-lg border-slate-200 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Base role</label>
                            <select name="base_role" x-model="createBaseRole" @change="applyCreateDefaults()" class="w-full rounded-lg border-slate-200 text-sm" required>
                                @foreach (['admin', 'manager', 'accountant', 'officer', 'applicant', 'user'] as $role)
                                    <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <label class="block text-xs font-semibold text-slate-600">Permissions</label>
                                <button type="button" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700" @click="applyCreateDefaults()">Use role defaults</button>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach ($permissionCatalog as $key => $label)
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 rounded border border-slate-200 px-2 py-1.5">
                                        <input type="checkbox" name="permissions[]" :value="'{{ $key }}'" x-model="createPerms" class="rounded border-slate-300">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                            <textarea name="description" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('description') }}</textarea>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', '1') === '1')>
                            Active
                        </label>
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="createRoleOpen = false">Cancel</button>
                            <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-60" @disabled(!($rbacReady ?? false))>Save role</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-loan-layout>
