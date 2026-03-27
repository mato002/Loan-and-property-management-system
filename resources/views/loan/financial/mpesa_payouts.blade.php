<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.mpesa_platform') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                M-Pesa platform
            </a>
            <a href="{{ route('loan.financial.mpesa_payouts.create') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Manual batch (legacy)
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Live payouts (B2C)</h2>
                <p class="text-xs text-slate-500">{{ $payouts->total() }} payout(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Transaction</th>
                            <th class="px-5 py-3">Conversation</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Result</th>
                            <th class="px-5 py-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payouts as $p)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-800">
                                    {{ $p->transaction_id ?? $p->reference }}
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">
                                    {{ $p->conversation_id ?? '—' }}
                                    @if($p->originator_conversation_id)
                                        <span class="block text-[11px] text-slate-500">{{ $p->originator_conversation_id }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-800 border border-slate-200 capitalize">{{ $p->status }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-600 text-xs">
                                    @if($p->result_code !== null)
                                        <span class="font-semibold">Code:</span> {{ $p->result_code }}
                                    @else
                                        —
                                    @endif
                                    @if($p->result_desc)
                                        <span class="block text-[11px] text-slate-500 max-w-md truncate">{{ $p->result_desc }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500 tabular-nums text-xs">{{ $p->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                    No live payouts yet.
                                    <div class="mt-2 text-xs text-slate-500">
                                        To see real ongoing payouts, set your Daraja <span class="font-mono">ResultURL</span> to
                                        <span class="font-mono">{{ url('/webhooks/mpesa/b2c-result') }}</span>.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payouts->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $payouts->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
