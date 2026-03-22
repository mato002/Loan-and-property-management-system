<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-slate-700">Open tills</h2>
                <p class="text-xs text-slate-500 mt-1">Select a session to post cash in/out or close.</p>
                <ul class="mt-4 space-y-2">
                    @forelse ($openSessions as $s)
                        <li>
                            <a href="{{ route('loan.financial.teller_sessions.show', $s) }}" class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm hover:border-indigo-200 hover:bg-white transition-colors">
                                <span class="font-medium text-slate-900">{{ $s->branch_label }}</span>
                                <span class="text-indigo-600 font-semibold text-xs">Manage →</span>
                            </a>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 py-4 text-center border border-dashed border-slate-200 rounded-lg">No open sessions. Open a till below.</li>
                    @endforelse
                </ul>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h2 class="text-sm font-semibold text-slate-700">Today's movements (all tills)</h2>
                <p class="text-xs text-slate-500 mt-1">Cash in vs cash out for sessions started today.</p>
                <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                    <div class="rounded-lg bg-slate-50 border border-slate-100 p-4">
                        <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Cash in</dt>
                        <dd class="text-lg font-bold text-slate-900 tabular-nums mt-1">{{ number_format((float) $cashInToday, 2) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 border border-slate-100 p-4">
                        <dt class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Cash out</dt>
                        <dd class="text-lg font-bold text-slate-900 tabular-nums mt-1">{{ number_format((float) $cashOutToday, 2) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Open new till</h2>
            </div>
            <form method="post" action="{{ route('loan.financial.teller_sessions.store') }}" class="px-5 py-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                @csrf
                <div>
                    <label for="branch_label" class="block text-xs font-semibold text-slate-600 mb-1">Branch / desk</label>
                    <input id="branch_label" name="branch_label" value="{{ old('branch_label') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('branch_label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="opened_by" class="block text-xs font-semibold text-slate-600 mb-1">Opened by</label>
                    <input id="opened_by" name="opened_by" value="{{ old('opened_by', auth()->user()?->name) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('opened_by')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="opening_float" class="block text-xs font-semibold text-slate-600 mb-1">Opening float</label>
                    <input id="opening_float" name="opening_float" type="number" step="0.01" min="0" value="{{ old('opening_float', '0') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('opening_float')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        Open till
                    </button>
                </div>
            </form>
        </div>

        @if ($recentSessions->isNotEmpty())
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Recently closed</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentSessions as $s)
                        <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="font-medium text-slate-900">{{ $s->branch_label }}</span>
                            <span class="text-slate-500 text-xs tabular-nums">Closed {{ $s->closed_at?->format('Y-m-d H:i') }}</span>
                            <a href="{{ route('loan.financial.teller_sessions.show', $s) }}" class="text-xs font-semibold text-indigo-600 hover:underline">View</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-loan.page>
</x-loan-layout>
