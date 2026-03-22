<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.branches.loan_summary') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Loan summary</a>
            <a href="{{ route('loan.regions.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Create region</a>
        </x-slot>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">All regions</h2>
                <p class="text-xs text-slate-500">{{ $regions->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Code</th>
                            <th class="px-5 py-3">Branches</th>
                            <th class="px-5 py-3">Active</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($regions as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $row->name }}</td>
                                <td class="px-5 py-3 text-slate-600 font-mono text-xs">{{ $row->code ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $row->branches_count }}</td>
                                <td class="px-5 py-3">
                                    @if ($row->is_active)
                                        <span class="text-xs font-semibold text-emerald-700">Yes</span>
                                    @else
                                        <span class="text-xs text-slate-400">No</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.regions.edit', $row) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.regions.destroy', $row) }}" class="inline" onsubmit="return confirm('Delete this region?');">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                    No regions yet. <a href="{{ route('loan.regions.create') }}" class="text-indigo-600 font-medium hover:underline">Create one</a> to attach branches.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($regions->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $regions->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
