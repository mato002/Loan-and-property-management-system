<x-loan-layout>
    <x-loan.page title="Utility payments" subtitle="Electricity, water, internet, and other recurring bills.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.utilities.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Record payment</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Provider</th>
                            <th class="px-5 py-3">Paid on</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 text-slate-800 capitalize">{{ str_replace('_', ' ', $r->utility_type) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $r->provider ?? '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $r->paid_on->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium">{{ $r->currency }} {{ number_format((float) $r->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $r->payment_method }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.accounting.utilities.edit', $r) }}" class="text-indigo-600 font-medium text-sm mr-2">Edit</a>
                                    <form method="post" action="{{ route('loan.accounting.utilities.destroy', $r) }}" class="inline" data-swal-confirm="Remove this record?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No utility payments recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $rows->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
