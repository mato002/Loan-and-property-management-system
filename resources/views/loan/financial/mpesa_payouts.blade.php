<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.mpesa_platform') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                M-Pesa platform
            </a>
            <a href="{{ route('loan.financial.mpesa_payouts.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                New payout batch
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Payout queue</h2>
                <p class="text-xs text-slate-500">{{ $batches->total() }} batch(es)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Recipients</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Created</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($batches as $batch)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">
                                    <a href="{{ route('loan.financial.mpesa_payouts.edit', $batch) }}" class="text-indigo-600 hover:underline">{{ $batch->reference }}</a>
                                </td>
                                <td class="px-5 py-3 tabular-nums text-slate-700">{{ $batch->recipient_count }}</td>
                                <td class="px-5 py-3 tabular-nums text-slate-700">{{ number_format((float) $batch->total_amount, 2) }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-800 border border-slate-200 capitalize">{{ $batch->status }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-500 tabular-nums text-xs">{{ $batch->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('loan.financial.mpesa_payouts.edit', $batch) }}" class="text-xs font-semibold text-indigo-600 hover:underline">Edit</a>
                                        <form method="post" action="{{ route('loan.financial.mpesa_payouts.destroy', $batch) }}" class="inline" data-swal-confirm="Delete this batch?">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                    No batches yet. <a href="{{ route('loan.financial.mpesa_payouts.create') }}" class="text-indigo-600 font-medium hover:underline">Create a payout batch</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($batches->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $batches->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
