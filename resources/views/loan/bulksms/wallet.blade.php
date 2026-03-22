<x-loan-layout>
    <x-loan.page
        title="SMS wallet"
        subtitle="Top up credits used when messages are sent."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.bulksms.compose') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Send SMS
            </a>
        </x-slot>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-800">Current balance</h2>
                <p class="text-3xl font-bold text-slate-900 tabular-nums mt-2">{{ number_format((float) $balance, 2) }} <span class="text-lg font-semibold text-slate-500">{{ $currency }}</span></p>
                <p class="text-xs text-slate-500 mt-2">{{ number_format($costPerSms, 2) }} {{ $currency }} per SMS at send time.</p>

                <form method="post" action="{{ route('loan.bulksms.wallet.topup') }}" class="mt-6 space-y-4 border-t border-slate-100 pt-6">
                    @csrf
                    <div>
                        <label for="amount" class="block text-sm font-medium text-slate-700">Top-up amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="amount" value="{{ old('amount') }}" required
                            class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]" />
                        @error('amount')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="reference" class="block text-sm font-medium text-slate-700">Payment reference (optional)</label>
                        <input type="text" name="reference" id="reference" value="{{ old('reference') }}" maxlength="120"
                            class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]" />
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-slate-700">Notes (optional)</label>
                        <textarea name="notes" id="notes" rows="2" maxlength="500"
                            class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#2f4f4f] focus:ring-[#2f4f4f]">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                        Record top-up
                    </button>
                </form>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Recent top-ups</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3">Amount</th>
                                <th class="px-5 py-3">Reference</th>
                                <th class="px-5 py-3">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($topups as $row)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-5 py-3 text-slate-600 tabular-nums whitespace-nowrap">{{ $row->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-5 py-3 font-medium text-slate-900 tabular-nums">{{ number_format((float) $row->amount, 2) }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $row->reference ?? '—' }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $row->user?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-10 text-center text-slate-500">No top-ups recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($topups->hasPages())
                    <div class="px-5 py-4 border-t border-slate-100">
                        {{ $topups->links() }}
                    </div>
                @endif
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
