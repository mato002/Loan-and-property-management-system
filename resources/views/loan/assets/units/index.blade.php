<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.assets.units.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add unit
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Units</h2>
                <p class="text-xs text-slate-500">{{ $units->total() }} total</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Abbreviation</th>
                            <th class="px-5 py-3">Description</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($units as $unit)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $unit->name }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $unit->abbreviation }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-xs truncate">{{ $unit->description ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.assets.units.edit', $unit) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.assets.units.destroy', $unit) }}" class="inline" data-swal-confirm="Delete this measurement unit? It cannot be in use by any stock line.">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-slate-500">No measurement units yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($units->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $units->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
