@php($title = 'Mobile Navigation — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Mobile navigation</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">Choose which workspaces and sections appear in agent phone navigation. These settings apply to all agent workspaces and do not change desktop menus or user permissions.</p>
        </div>
        <a href="{{ route('superadmin.agent_workspaces') }}" class="text-sm font-semibold text-emerald-700 hover:underline">Agent workspaces</a>
    </div>

    <form method="post" action="{{ route('superadmin.mobile_navigation.update') }}" class="space-y-4">
        @csrf
        @foreach ($workspaces as $workspace)
            @php
                $workspaceKey = (string) ($workspace['key'] ?? '');
                $tabs = $tabsByWorkspace[$workspaceKey] ?? [];
                $hiddenTabs = $settings['hidden_tabs'][$workspaceKey] ?? [];
            @endphp
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                            <i class="fa-solid {{ $workspace['icon'] ?? 'fa-circle' }}" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="font-bold text-slate-900">{{ $workspace['label'] ?? $workspaceKey }}</h2>
                            <p class="text-xs text-slate-500">{{ $workspace['sublabel'] ?? '' }}</p>
                        </div>
                    </div>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="hidden_workspaces[]" value="{{ $workspaceKey }}" @checked(in_array($workspaceKey, $settings['hidden_workspaces'], true)) class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        Hide workspace on phones
                    </label>
                </div>

                @if ($tabs !== [])
                    <details class="border-t border-slate-100">
                        <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">Choose sections to hide <span class="font-normal text-slate-400">({{ count($tabs) }} sections)</span></summary>
                        <div class="grid grid-cols-1 gap-2 px-5 pb-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($tabs as $tab)
                                @php($tabKey = (string) ($tab['key'] ?? ''))
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                    <input type="checkbox" name="hidden_tabs[{{ $workspaceKey }}][]" value="{{ $tabKey }}" @checked(in_array($tabKey, $hiddenTabs, true)) class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    Hide {{ $tab['label'] ?? $tabKey }}
                                </label>
                            @endforeach
                        </div>
                    </details>
                @else
                    <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">This workspace has no section tabs to configure.</p>
                @endif
            </section>
        @endforeach

        <div class="sticky bottom-0 flex flex-col-reverse gap-3 border-t border-slate-200 bg-[#e8ecf1]/95 py-4 backdrop-blur sm:flex-row sm:justify-end">
            <a href="{{ route('superadmin.mobile_navigation') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">Discard changes</a>
            <button type="submit" class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Save mobile navigation</button>
        </div>
    </form>
@endsection
