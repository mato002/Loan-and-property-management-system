<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.assets.items.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add asset / stock
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Register</h2>
                <p class="text-xs text-slate-500">{{ $items->total() }} line(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Code</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Category</th>
                            <th class="px-5 py-3 text-right">Qty</th>
                            <th class="px-5 py-3">Unit</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $row->asset_code }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $row->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->category->name }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ rtrim(rtrim(number_format((float) $row->quantity, 4, '.', ''), '0'), '.') }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $row->measurementUnit->abbreviation }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $row->status) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.assets.items.edit', $row) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.assets.items.destroy', $row) }}" class="inline" data-swal-confirm="Remove this asset / stock line from the register?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">No assets or stock yet. Add categories and units first if needed.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($items->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $items->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
