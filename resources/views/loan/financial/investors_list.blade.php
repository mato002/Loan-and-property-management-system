<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.investors.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add investor
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">All investors</h2>
                <p class="text-xs text-slate-500">{{ $investors->total() }} registered</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3">Package</th>
                            <th class="px-5 py-3 text-right">Committed</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($investors as $inv)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $inv->name }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    @if ($inv->email)
                                        <span class="block">{{ $inv->email }}</span>
                                    @endif
                                    @if ($inv->phone)
                                        <span class="block text-xs text-slate-500">{{ $inv->phone }}</span>
                                    @endif
                                    @if (! $inv->email && ! $inv->phone)
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $inv->investmentPackage?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">
                                    {{ $inv->committed_amount !== null ? number_format((float) $inv->committed_amount, 2) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('loan.financial.investors.edit', $inv) }}" class="text-xs font-semibold text-indigo-600 hover:underline">Edit</a>
                                        <form method="post" action="{{ route('loan.financial.investors.destroy', $inv) }}" class="inline" data-swal-confirm="Remove this investor?">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                    No investors yet. <a href="{{ route('loan.financial.investors.create') }}" class="text-indigo-600 font-medium hover:underline">Add investor</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($investors->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $investors->links() }}</div>
            @endif
        </div>

        <p class="text-xs text-slate-500">
            <a href="{{ route('loan.financial.investors_reports') }}" class="text-indigo-600 font-semibold hover:underline">Investors reports</a>
            · export CSV from there.
        </p>
    </x-loan.page>
</x-loan-layout>
