@php($title = 'Dashboard — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Platform control</h1>
        <p class="mt-1 text-sm text-slate-600 max-w-3xl">
            Use this console for <span class="font-semibold text-slate-800">access, users, subscriptions, and governance</span>.
            Client money, loan balances, and payment ledgers live in each tenant’s workspace (agent / loan / property staff)—not here by default.
        </p>
    </div>

    {{-- Top Level KPI Cards --}}
    <h2 class="text-sm font-bold tracking-widest text-slate-500 uppercase mb-4">Scale &amp; footprint</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="rounded-2xl border-l-[6px] border-emerald-500 bg-white p-6 shadow-sm flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="bg-emerald-50 p-4 rounded-xl text-emerald-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500">Accounts (total / agents)</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($stats['users']) }} <span class="text-sm font-bold text-slate-400">({{ number_format($stats['agents']) }} agents)</span></p>
            </div>
        </div>

        <div class="rounded-2xl border-l-[6px] border-indigo-500 bg-white p-6 shadow-sm flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="bg-indigo-50 p-4 rounded-xl text-indigo-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500">Properties &amp; units</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($stats['properties']) }} <span class="text-sm font-bold text-slate-400">/ {{ number_format($stats['units']) }} units</span></p>
            </div>
        </div>

        <div class="rounded-2xl border-l-[6px] border-cyan-500 bg-white p-6 shadow-sm flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="bg-cyan-50 p-4 rounded-xl text-cyan-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500">Tenant records (property)</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($stats['tenants']) }}</p>
            </div>
        </div>
    </div>

    {{-- Loan module: counts only unless opted in for money aggregates --}}
    <h2 class="text-sm font-bold tracking-widest text-slate-500 uppercase mb-2 mt-2">Loan module health</h2>
    <p class="text-xs text-slate-500 mb-4 max-w-3xl">Non-money counts for platform monitoring. Open the <strong>loan</strong> product with a loan staff account to work balances, applications, and payments.</p>

    @if (! ($loanStats['tables_ready'] ?? false))
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 p-8 mb-8 text-center">
            <p class="text-sm font-semibold text-slate-600">Loan book tables are not installed or migrated yet.</p>
            <p class="mt-1 text-xs text-slate-500">After migrations, operational counts will appear here.</p>
        </div>
    @else
        @if (($loanStats['pending_loan_access'] ?? 0) > 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 mb-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-bold text-amber-900">
                    {{ number_format($loanStats['pending_loan_access']) }} pending loan module access request(s)
                </p>
                <a href="{{ route('superadmin.access_approvals', ['module' => 'loan']) }}" class="inline-flex rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-800">Review access</a>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
            @if ($loanStats['book_ready'] ?? false)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Active loans</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($loanStats['active_loans']) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format($loanStats['total_loans']) }} total in book</p>
                </div>
                @if ($showTenantFinancialAggregates ?? false)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Outstanding (system aggregate)</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">{{ 'Ksh '.number_format((float) ($loanStats['outstanding'] ?? 0), 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Active, restructured &amp; pending disbursement</p>
                    </div>
                @endif
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">NPL (31+ DPD)</p>
                    <p class="mt-2 text-2xl font-black text-rose-700">{{ number_format($loanStats['npl_count']) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Count of active loans</p>
                </div>
            @endif

            @if ($showTenantFinancialAggregates ?? false)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">MTD collections (aggregate)</p>
                    <p class="mt-2 text-2xl font-black text-emerald-700">{{ 'Ksh '.number_format((float) ($loanStats['mtd_collections'] ?? 0), 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Processed pay-ins, this month</p>
                </div>
            @elseif ($loanStats['book_ready'] ?? false)
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 shadow-sm sm:col-span-2 xl:col-span-1">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Money totals hidden</p>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                        Outstanding and collection <strong>amounts</strong> are off in Super Admin by default. Enable
                        <code class="rounded bg-white px-1 py-0.5 text-xs border border-slate-200">SUPERADMIN_SHOW_TENANT_FINANCIAL_AGGREGATES=true</code>
                        in <code class="rounded bg-white px-1 py-0.5 text-xs border border-slate-200">.env</code> only if you need system-wide figures here.
                    </p>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Clients &amp; leads</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($loanStats['clients']) }} <span class="text-lg font-bold text-slate-400">/ {{ number_format($loanStats['leads']) }}</span></p>
                <p class="mt-1 text-xs text-slate-500">Loan CRM records</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Applications pipeline</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($loanStats['pipeline']) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ number_format($loanStats['credit_review']) }} in credit review</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Unposted payments (queue)</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($loanStats['unposted_payments']) }}</p>
                <p class="mt-1 text-xs text-slate-500">Awaiting posting in loan workflows</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1 space-y-8">
            @if ($stats['pending_access'] > 0 || $stats['unmatched_payments'] > 0)
                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </span>
                        <h2 class="text-lg font-black text-slate-900">Needs attention</h2>
                    </div>
                    <ul class="space-y-3">
                        @if ($stats['pending_access'] > 0)
                            <li class="flex items-center justify-between bg-white px-4 py-3 rounded-xl border border-amber-100">
                                <span class="text-sm font-bold text-amber-950">Module access requests</span>
                                <a href="{{ route('superadmin.access_approvals') }}" class="inline-flex rounded-lg bg-amber-700 px-3 py-1 text-xs font-bold text-white hover:bg-amber-800">{{ $stats['pending_access'] }} pending</a>
                            </li>
                        @endif
                        @if ($stats['unmatched_payments'] > 0)
                            <li class="flex flex-col gap-2 bg-white px-4 py-3 rounded-xl border border-amber-100">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-bold text-amber-950">Property payment reconciliation</span>
                                    <span class="inline-flex rounded-lg bg-amber-200/80 px-3 py-1 text-xs font-bold text-amber-950">{{ $stats['unmatched_payments'] }} items</span>
                                </div>
                                <p class="text-xs text-amber-900/80">Unassigned receipts are cleared in each agent’s <strong>property</strong> workspace—not from this console.</p>
                                <a href="{{ route('superadmin.agent_workspaces') }}" class="text-xs font-bold text-amber-800 hover:underline">Agent workspaces →</a>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-900 mb-2">Shortcuts</h2>
                <p class="text-xs text-slate-500 mb-5">Tasks you control as platform operator.</p>
                <div class="grid grid-cols-2 gap-4">
                    <a href="{{ route('superadmin.users.index') }}" class="group flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-100 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-700 transition">
                        <svg class="h-8 w-8 text-slate-400 group-hover:text-emerald-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span class="text-sm font-bold text-center">Users</span>
                    </a>
                    <a href="{{ route('superadmin.console.subscriptions') }}" class="group flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-100 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition">
                        <svg class="h-8 w-8 text-slate-400 group-hover:text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        <span class="text-sm font-bold text-center">Subscriptions</span>
                    </a>
                    <a href="{{ route('superadmin.access_approvals') }}" class="col-span-2 group flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-300 transition">
                        <svg class="h-7 w-7 text-slate-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"></path></svg>
                        <span class="text-sm font-bold text-slate-700">Module access approvals</span>
                    </a>
                    <a href="{{ route('superadmin.roles_permissions') }}" class="col-span-2 text-center text-xs font-semibold text-slate-500 hover:text-slate-800 underline-offset-2 hover:underline">
                        Roles &amp; permissions (read-only map)
                    </a>
                </div>
                <a href="{{ route('dashboard') }}" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    Main app home
                </a>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm h-full">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Recent activity</h2>
                        <p class="text-sm text-slate-500">High-level audit trail preview (no client ledger detail).</p>
                    </div>
                    <a href="{{ route('superadmin.audit_trail') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">View all →</a>
                </div>

                @if(isset($recentActivities) && $recentActivities->isNotEmpty())
                    <div class="relative border-l-2 border-slate-100 ml-4 space-y-7 mt-8">
                        @foreach($recentActivities as $activity)
                            <div class="relative pl-6">
                                <span class="absolute -left-[9px] top-1 flex h-4 w-4 items-center justify-center rounded-full bg-slate-200 ring-4 ring-white"></span>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ $activity->user ? $activity->user->name : 'System' }}
                                            <span class="font-normal text-slate-500">·</span>
                                            <span class="font-mono text-xs bg-slate-100 text-slate-600 px-1 py-0.5 rounded">{{ $activity->action_key }}</span>
                                        </p>
                                        @if($activity->notes)
                                            <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ \Illuminate\Support\Str::limit((string) $activity->notes, 140) }}</p>
                                        @endif
                                    </div>
                                    <div class="flex-shrink-0 text-xs font-semibold text-slate-400 sm:mt-0 mt-1">
                                        {{ $activity->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 flex flex-col items-center justify-center border-t border-slate-100 mt-6">
                        <svg class="h-10 w-10 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        <p class="text-sm font-bold text-slate-400">No recent activity recorded.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
