@php($title = 'Dashboard — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Super Admin dashboard</h1>
        <p class="mt-1 text-sm text-slate-600">Global overview across users, workspaces and access.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500 uppercase">Users</p><p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($stats['users']) }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500 uppercase">Agents</p><p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($stats['agents']) }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500 uppercase">Properties</p><p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($stats['properties']) }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500 uppercase">Units</p><p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($stats['units']) }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500 uppercase">Tenants</p><p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($stats['tenants']) }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500 uppercase">Pending Access</p><p class="mt-2 text-2xl font-black text-amber-700">{{ number_format($stats['pending_access']) }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500 uppercase">Unmatched Payments</p><p class="mt-2 text-2xl font-black text-rose-700">{{ number_format($stats['unmatched_payments']) }}</p></div>
    </div>
@endsection

