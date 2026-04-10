@php($title = 'Dashboard — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Super Admin dashboard</h1>
        <p class="mt-1 text-sm text-slate-600">Global overview across users, workspaces and access.</p>
    </div>

    {{-- Top Level KPI Cards --}}
    <h2 class="text-sm font-bold tracking-widest text-slate-500 uppercase mb-4">Overall Snapshot</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="rounded-2xl border-l-[6px] border-emerald-500 bg-white p-6 shadow-sm flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="bg-emerald-50 p-4 rounded-xl text-emerald-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500">Total System Users</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($stats['users']) }} <span class="text-sm font-bold text-slate-400">({{ number_format($stats['agents']) }} agents)</span></p>
            </div>
        </div>

        <div class="rounded-2xl border-l-[6px] border-indigo-500 bg-white p-6 shadow-sm flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="bg-indigo-50 p-4 rounded-xl text-indigo-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500">Total Properties & Units</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($stats['properties']) }} <span class="text-sm font-bold text-slate-400">/ {{ number_format($stats['units']) }}</span></p>
            </div>
        </div>

        <div class="rounded-2xl border-l-[6px] border-cyan-500 bg-white p-6 shadow-sm flex items-center gap-4 transition-transform hover:-translate-y-1">
            <div class="bg-cyan-50 p-4 rounded-xl text-cyan-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-500">Total Tenants</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($stats['tenants']) }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left Column: Quick Actions & Alerts --}}
        <div class="lg:col-span-1 space-y-8">
            {{-- Needs Attention / Alerts --}}
            @if ($stats['pending_access'] > 0 || $stats['unmatched_payments'] > 0)
                <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </span>
                        <h2 class="text-lg font-black text-slate-900">Needs Attention</h2>
                    </div>
                    <ul class="space-y-3">
                        @if ($stats['pending_access'] > 0)
                            <li class="flex items-center justify-between bg-white px-4 py-3 rounded-xl border border-rose-100">
                                <span class="text-sm font-bold text-rose-900">Role Approvals</span>
                                <a href="{{ route('superadmin.access_approvals') }}" class="inline-flex rounded-lg bg-rose-600 px-3 py-1 text-xs font-bold text-white hover:bg-rose-700">{{ $stats['pending_access'] }} requests</a>
                            </li>
                        @endif
                        @if ($stats['unmatched_payments'] > 0)
                            <li class="flex items-center justify-between bg-white px-4 py-3 rounded-xl border border-rose-100">
                                <span class="text-sm font-bold text-rose-900">Unmatched Payments</span>
                                <span class="inline-flex rounded-lg bg-rose-200 px-3 py-1 text-xs font-bold text-rose-900">{{ $stats['unmatched_payments'] }} issues</span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            {{-- Quick Shortcuts --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-900 mb-5">Quick Actions</h2>
                <div class="grid grid-cols-2 gap-4">
                    <a href="{{ route('superadmin.users.index') }}" class="group flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-100 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-700 transition">
                        <svg class="h-8 w-8 text-slate-400 group-hover:text-emerald-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span class="text-sm font-bold text-center">Manage Users</span>
                    </a>
                    <a href="{{ route('superadmin.roles_permissions') }}" class="group flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-100 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition">
                        <svg class="h-8 w-8 text-slate-400 group-hover:text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <span class="text-sm font-bold text-center pl-2 pr-2">Roles & Access</span>
                    </a>
                    <a href="{{ route('dashboard') }}" class="col-span-2 group flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-100 bg-slate-50 hover:bg-slate-100 hover:border-slate-300 transition">
                        <svg class="h-7 w-7 text-slate-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        <span class="text-sm font-bold text-slate-600">Standard Dashboard</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Right Column: Audit Trail --}}
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm h-full">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Recent Activity Trail</h2>
                        <p class="text-sm text-slate-500">Latest actions performed in the system.</p>
                    </div>
                    <a href="{{ route('superadmin.audit_trail') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">View all &rarr;</a>
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
                                            <span class="font-normal text-slate-500">Performed</span> 
                                            <span class="font-mono text-xs bg-slate-100 text-slate-600 px-1 py-0.5 rounded">{{ $activity->action_key }}</span>
                                        </p>
                                        @if($activity->notes)
                                            <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $activity->notes }}</p>
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

