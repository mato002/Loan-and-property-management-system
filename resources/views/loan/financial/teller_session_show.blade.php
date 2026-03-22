<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.teller_operations') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                All teller ops
            </a>
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Opening float</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums mt-1">{{ number_format((float) $session->opening_float, 2) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Cash in (session)</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums mt-1">{{ number_format((float) $session->cashInTotal(), 2) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Cash out (session)</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums mt-1">{{ number_format((float) $session->cashOutTotal(), 2) }}</p>
            </div>
        </div>

        @if ($session->isOpen())
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Record movement</h2>
                </div>
                <form method="post" action="{{ route('loan.financial.teller_sessions.movements.store', $session) }}" class="px-5 py-5 grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label for="kind" class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                        <select id="kind" name="kind" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="cash_in" @selected(old('kind') === 'cash_in')>Cash in</option>
                            <option value="cash_out" @selected(old('kind') === 'cash_out')>Cash out</option>
                        </select>
                        @error('kind')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="note" class="block text-xs font-semibold text-slate-600 mb-1">Note</label>
                        <input id="note" name="note" value="{{ old('note') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('note')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-4">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                            Post movement
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-amber-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-amber-100 bg-amber-50/50">
                    <h2 class="text-sm font-semibold text-slate-800">Close till</h2>
                    <p class="text-xs text-slate-600 mt-0.5">Enter the physical count to close this session.</p>
                </div>
                <form method="post" action="{{ route('loan.financial.teller_sessions.close', $session) }}" class="px-5 py-5 flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label for="closing_float" class="block text-xs font-semibold text-slate-600 mb-1">Closing float</label>
                        <input id="closing_float" name="closing_float" type="number" step="0.01" min="0" value="{{ old('closing_float') }}" required class="w-full min-w-[200px] rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('closing_float')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-900 transition-colors">
                        Close till
                    </button>
                </form>
            </div>
        @else
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                This session is closed{{ $session->closing_float !== null ? ' · counted float '.number_format((float) $session->closing_float, 2) : '' }}.
            </div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Movements</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Note</th>
                            <th class="px-5 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($session->movements as $m)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3">
                                    @if ($m->kind === 'cash_in')
                                        <span class="text-emerald-700 font-medium">Cash in</span>
                                    @else
                                        <span class="text-red-700 font-medium">Cash out</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-slate-900">{{ number_format((float) $m->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $m->note ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-500 text-xs tabular-nums">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-slate-500">No movements posted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
