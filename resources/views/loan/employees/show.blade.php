@php
    $na = fn ($value) => filled($value) ? $value : 'Not Set';
    $statusLabel = $na($employee->employment_status);
    $designationLabel = $na($employee->job_title);
    $initials = strtoupper(substr((string) ($employee->first_name ?? ''), 0, 1).substr((string) ($employee->last_name ?? ''), 0, 1));
    $accountNumber = $employee->bank_account_number;
    $maskedAccount = filled($accountNumber)
        ? str_repeat('*', max(0, strlen((string) $accountNumber) - 4)).substr((string) $accountNumber, -4)
        : 'Not Set';
    $activeLoans = (int) $employee->staffPortfolios->sum('active_loans');
    $managedPortfolioKes = number_format((float) $employee->staffPortfolios->sum('outstanding_amount'), 0);
    $activeClients = (int) $employee->staffLoans->count();
@endphp

<style>
    .staff360-shell { background: #f4f7f6; }
    .staff360-card { background: #fff; border: 1px solid #dbe3e4; border-radius: 12px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .staff360-label { font-size: 10pt; font-weight: 700; color: #334155; }
    .staff360-value { font-size: 11pt; color: #0f172a; }
    .staff360-tab { border: 1px solid #d1d5db; background: #fff; color: #334155; border-radius: 9px; padding: .5rem .9rem; font-size: 10pt; font-weight: 600; }
    .staff360-tab-active { background: #0f172a; color: #fff; border-color: #0f172a; }
    .staff360-action { border: 1px solid #cfd8dc; background: #fff; color: #1f2937; border-radius: 9px; padding: .45rem .8rem; font-size: .83rem; font-weight: 600; }
    .staff360-action:hover { background: #f8fafc; }
    .staff360-muted { color: #64748b; font-size: 10pt; }
    @media (max-width: 1024px) {
        .staff360-stack { grid-template-columns: 1fr !important; }
    }
</style>

<x-loan-layout>
    <x-loan.page
        :title="'Employee Profile: '.$employee->full_name"
        subtitle="Comprehensive Staff 360 Dashboard"
    >
        <x-slot name="actions">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('loan.employees.index') }}" class="staff360-action inline-flex items-center justify-center">
                    &lt; Back to List
                </a>
                <a href="{{ route('loan.employees.edit', $employee) }}" class="staff360-action inline-flex items-center justify-center !border-indigo-300 !bg-indigo-50 !text-indigo-700 hover:!bg-indigo-100">
                    Edit Profile
                </a>
                <button type="button" onclick="window.print()" class="staff360-action inline-flex items-center justify-center">
                    Print Staff File
                </button>
                <a href="#security-logs-tab" class="staff360-action inline-flex items-center justify-center !border-amber-300 !bg-amber-50 !text-amber-800 hover:!bg-amber-100">
                    Manage Access
                </a>
            </div>
        </x-slot>

        <div class="staff360-shell min-h-full rounded-2xl border border-slate-200 p-4 sm:p-6">
            <div class="staff360-card mb-4 p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <h2 class="text-xl font-semibold text-slate-900">{{ $employee->full_name }}</h2>
                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $designationLabel }}</span>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $statusLabel }}</span>
                </div>
                <p class="mt-1 staff360-muted">Employee ID: {{ $na($employee->employee_number) }} | Department: {{ $na($employee->department) }}</p>
            </div>

            <div class="staff360-card mb-4 p-3">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="staff-tab-btn staff360-tab staff360-tab-active" data-tab-target="overview-tab">Overview</button>
                    <button type="button" class="staff-tab-btn staff360-tab" data-tab-target="work-portfolio-tab">Work &amp; Portfolio</button>
                    <button type="button" class="staff-tab-btn staff360-tab" data-tab-target="compliance-payroll-tab">Compliance &amp; Payroll</button>
                    <button type="button" id="security-logs-tab-trigger" class="staff-tab-btn staff360-tab" data-tab-target="security-logs-tab">Security &amp; Logs</button>
                </div>
            </div>

            <section id="overview-tab" class="staff-tab-panel space-y-4">
                <div class="staff360-stack grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <div class="space-y-4 lg:col-span-9">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="staff360-card p-4 sm:p-5">
                        <p class="text-[10pt] font-bold uppercase tracking-wide text-slate-600">Card A: Essential Personal Data</p>
                        <div class="mt-4 flex items-center gap-3">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-lg font-semibold text-slate-700">{{ $initials !== '' ? $initials : 'NA' }}</div>
                            <div>
                                <p class="text-[10pt] font-bold text-slate-700">Avatar</p>
                                <p class="text-[11pt] text-slate-700">Monogram</p>
                            </div>
                        </div>
                        <div class="mt-4 space-y-2">
                            <p class="text-[10pt] font-bold text-slate-700">Employee ID</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->employee_number) }}</p>
                            <p class="text-[10pt] font-bold text-slate-700">Gender</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->gender) }}</p>
                            <p class="text-[10pt] font-bold text-slate-700">National ID</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->national_id) }}</p>
                            <p class="text-[10pt] font-bold text-slate-700">Phone</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->phone) }}</p>
                            <p class="text-[10pt] font-bold text-slate-700">Personal Email</p>
                            <p class="break-all text-[11pt] text-slate-900">{{ $na($employee->personal_email ?: $employee->email) }}</p>
                            <p class="pt-2 text-[10pt] font-bold text-slate-700">Emergency Contact (Next of Kin)</p>
                            <p class="text-[11pt] text-slate-900">Name: {{ $na($employee->next_of_kin_name) }}</p>
                            <p class="text-[11pt] text-slate-900">Phone: {{ $na($employee->next_of_kin_phone) }}</p>
                        </div>
                    </div>

                    <div class="staff360-card p-4 sm:p-5">
                        <p class="text-[10pt] font-bold uppercase tracking-wide text-slate-600">Card B: Employment &amp; Hierarchy</p>
                        <div class="mt-4 space-y-2">
                            <p class="text-[10pt] font-bold text-slate-700">Department</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->department) }}</p>
                            <p class="text-[10pt] font-bold text-slate-700">Branch</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->branch) }}</p>
                            <p class="text-[10pt] font-bold text-slate-700">Hire Date</p>
                            <p class="text-[11pt] text-slate-900">{{ optional($employee->hire_date)->format('Y-m-d') ?: 'Not Set' }}</p>
                            <p class="pt-2 text-[10pt] font-bold text-slate-700">Reporting Line</p>
                            @if ($supervisor)
                                <a href="{{ route('loan.employees.show', $supervisor) }}" class="text-[11pt] text-indigo-700 hover:text-indigo-900">Immediate Supervisor: {{ $supervisor->full_name }}</a>
                            @else
                                <p class="text-[11pt] text-slate-900">Immediate Supervisor: Not Set</p>
                            @endif
                            <div class="text-[11pt] text-slate-900">
                                Direct Reports:
                                @if ($directReports->isNotEmpty())
                                    @foreach ($directReports as $report)
                                        <a href="{{ route('loan.employees.show', $report) }}" class="ml-1 text-indigo-700 hover:text-indigo-900">{{ $report->full_name }}</a>@if (! $loop->last), @endif
                                    @endforeach
                                @else
                                    Not Set
                                @endif
                            </div>
                            <p class="pt-2 text-[10pt] font-bold text-slate-700">Work Type</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->work_type) }}</p>
                        </div>
                    </div>

                    <div class="staff360-card p-4 sm:p-5">
                        <p class="text-[10pt] font-bold uppercase tracking-wide text-slate-600">Card C: Company Assets &amp; Payroll</p>
                        <div class="mt-4 space-y-2">
                            <p class="text-[10pt] font-bold text-slate-700">Assigned Tools</p>
                            @if (filled($employee->assigned_tools))
                                <p class="text-[11pt] text-slate-900">{{ $employee->assigned_tools }}</p>
                            @else
                                <p class="text-[11pt] text-slate-900">Not Set</p>
                            @endif
                            <p class="pt-2 text-[10pt] font-bold text-slate-700">KRA PIN</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->kra_pin) }}</p>
                            <p class="text-[10pt] font-bold text-slate-700">Bank Name</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->bank_name) }}</p>
                            <p class="text-[10pt] font-bold text-slate-700">Account Number</p>
                            <p class="text-[11pt] text-slate-900">{{ $maskedAccount }}</p>
                        </div>
                    </div>
                        </div>
                    </div>

                    <aside class="space-y-4 lg:col-span-3">
                        <div class="staff360-card p-4">
                            <p class="staff360-label uppercase tracking-wide">Loan Portfolio Stats</p>
                            <div class="mt-3 space-y-2">
                                <p class="staff360-value">Active Loans: {{ $activeLoans > 0 ? number_format($activeLoans) : 'Not Set' }}</p>
                                <p class="staff360-value">Managed Portfolio (KES): {{ $activeLoans > 0 ? $managedPortfolioKes : 'Not Set' }}</p>
                                <p class="staff360-value">Active Clients: {{ $activeClients > 0 ? number_format($activeClients) : 'Not Set' }}</p>
                            </div>
                        </div>
                        <div class="staff360-card p-4">
                            <p class="staff360-label uppercase tracking-wide">Linked Login Account</p>
                            <p class="mt-2 staff360-value">{{ $linkedUser?->email ?: 'Not Set' }}</p>
                            <a href="{{ $linkedUser ? route('superadmin.users.edit', $linkedUser) : '#' }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-[#0f5d63] px-3 py-2 text-xs font-semibold text-white hover:bg-[#0b4a4f] {{ $linkedUser ? '' : 'pointer-events-none opacity-60' }}">
                                Grant Login Access
                            </a>
                        </div>
                        <div class="staff360-card p-4">
                            <p class="staff360-label uppercase tracking-wide">Audit Trail</p>
                            <p class="mt-2 staff360-value">Last login: {{ optional($recentActivity->first()?->created_at)->diffForHumans() ?: 'Not Set' }}</p>
                        </div>
                    </aside>
                </div>
            </section>

            <section id="work-portfolio-tab" class="staff-tab-panel hidden space-y-4">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 lg:col-span-2">
                        <p class="text-[10pt] font-bold uppercase tracking-wide text-slate-600">Department Work Portfolio</p>
                        @if ($isCreditDepartment)
                            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <p class="text-[10pt] font-bold text-slate-700">Loan Portfolios</p>
                                    <p class="text-[11pt] text-slate-900">{{ $employee->staffPortfolios->count() ?: 'Not Set' }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <p class="text-[10pt] font-bold text-slate-700">Loan Applications</p>
                                    <p class="text-[11pt] text-slate-900">{{ $employee->staffLoanApplications->count() ?: 'Not Set' }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <p class="text-[10pt] font-bold text-slate-700">Managed Loans</p>
                                    <p class="text-[11pt] text-slate-900">{{ $employee->staffLoans->count() ?: 'Not Set' }}</p>
                                </div>
                            </div>
                        @elseif ($isAccountingDepartment)
                            <div class="mt-4 space-y-2">
                                @forelse ($journalEntries as $entry)
                                    <div class="rounded-lg border border-slate-200 p-3">
                                        <p class="text-[11pt] text-slate-900">Journal {{ $entry->reference ?: 'Not Set' }} - {{ strtoupper((string) ($entry->status ?: 'posted')) }}</p>
                                        <p class="text-[10pt] text-slate-600">{{ optional($entry->entry_date)->format('Y-m-d') ?: 'Not Set' }}</p>
                                    </div>
                                @empty
                                    <p class="text-[11pt] text-slate-900">Processed journals: Not Set</p>
                                @endforelse
                            </div>
                        @elseif ($isHrDepartment)
                            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <p class="text-[10pt] font-bold text-slate-700">Recruitment Metrics</p>
                                    <p class="text-[11pt] text-slate-900">Not Set</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <p class="text-[10pt] font-bold text-slate-700">Open Positions Managed</p>
                                    <p class="text-[11pt] text-slate-900">Not Set</p>
                                </div>
                            </div>
                        @else
                            <p class="mt-4 text-[11pt] text-slate-900">Department-specific portfolio data is not configured for this staff category yet.</p>
                        @endif
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p class="text-[10pt] font-bold uppercase tracking-wide text-slate-600">{{ $kpi['title'] }}</p>
                        <div class="mt-4 space-y-3">
                            <div class="rounded-lg border border-slate-200 p-3">
                                <p class="text-[10pt] font-bold text-slate-700">{{ $kpi['metric_a_label'] }}</p>
                                <p class="text-[11pt] text-slate-900">{{ $kpi['metric_a_value'] }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 p-3">
                                <p class="text-[10pt] font-bold text-slate-700">{{ $kpi['metric_b_label'] }}</p>
                                <p class="text-[11pt] text-slate-900">{{ $kpi['metric_b_value'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="compliance-payroll-tab" class="staff-tab-panel hidden">
                <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <p class="text-[10pt] font-bold uppercase tracking-wide text-slate-600">Compliance &amp; Payroll Records</p>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">KRA PIN</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->kra_pin) }}</p>
                        </div>
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">Bank Name</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->bank_name) }}</p>
                        </div>
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">Bank Account</p>
                            <p class="text-[11pt] text-slate-900">{{ $maskedAccount }}</p>
                        </div>
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">NHIF</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->nhif_number) }}</p>
                        </div>
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">NSSF</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->nssf_number) }}</p>
                        </div>
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">Employment Contract Scan</p>
                            <p class="text-[11pt] text-slate-900">{{ $na($employee->employment_contract_scan) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="security-logs-tab" class="staff-tab-panel hidden space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <p class="text-[10pt] font-bold uppercase tracking-wide text-slate-600">System Permissions &amp; Access</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <a href="{{ $linkedUser ? route('superadmin.users.edit', $linkedUser) : '#' }}" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 {{ $linkedUser ? '' : 'pointer-events-none opacity-60' }}">
                            Assign System Credentials
                        </a>
                        @if ($linkedUser)
                            <form method="post" action="{{ route('loan.employees.resend_login', $employee) }}" data-swal-confirm="Resend login credentials to this employee email?">
                                @csrf
                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Resend credentials</button>
                            </form>
                        @endif
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">Linked User</p>
                            <p class="text-[11pt] text-slate-900">{{ $linkedUser?->email ?: 'Not Set' }}</p>
                        </div>
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">Role</p>
                            <p class="text-[11pt] text-slate-900">{{ $linkedUser ? (optional($linkedUser->activeLoanAccessRole())->name ?: ($linkedUser->effectiveLoanRole() ?: $linkedUser->loan_role ?: 'user')) : 'Not Set' }}</p>
                        </div>
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">Module Access</p>
                            <p class="text-[11pt] text-slate-900">{{ $linkedUser ? implode(', ', $linkedUser->approvedModules()) : 'Not Set' }}</p>
                        </div>
                        <div>
                            <p class="text-[10pt] font-bold text-slate-700">Last Login Activity</p>
                            <p class="text-[11pt] text-slate-900">{{ optional($recentActivity->first()?->created_at)->format('Y-m-d H:i') ?: 'Not Set' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <p class="text-[10pt] font-bold uppercase tracking-wide text-slate-600">Recent Activity (Last 5)</p>
                    <div class="mt-3 space-y-2">
                        @forelse ($recentActivity as $log)
                            <div class="rounded-lg border border-slate-200 p-3">
                                <p class="text-[11pt] text-slate-900">{{ $log->activity ?: Str::headline((string) $log->route_name ?: (string) $log->path) }}</p>
                                <p class="text-[10pt] text-slate-600">{{ optional($log->created_at)->format('Y-m-d H:i') ?: 'Not Set' }}</p>
                            </div>
                        @empty
                            <p class="text-[11pt] text-slate-900">No recent activity found.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </x-loan.page>
</x-loan-layout>

<script>
    (function () {
        const buttons = document.querySelectorAll('.staff-tab-btn');
        const panels = document.querySelectorAll('.staff-tab-panel');
        if (!buttons.length || !panels.length) return;

        function activate(tabId) {
            panels.forEach((panel) => panel.classList.toggle('hidden', panel.id !== tabId));
            buttons.forEach((button) => {
                const active = button.dataset.tabTarget === tabId;
                button.classList.toggle('staff360-tab-active', active);
            });
        }

        buttons.forEach((button) => {
            button.addEventListener('click', () => activate(button.dataset.tabTarget));
        });

        if (window.location.hash === '#security-logs-tab') {
            activate('security-logs-tab');
        } else {
            activate('overview-tab');
        }
    })();
</script>
