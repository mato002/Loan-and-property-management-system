<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.collection_agents.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Add agent</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Agents</h2>
                <p class="text-xs text-slate-500">{{ $agents->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Phone</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Linked employee</th>
                            <th class="px-5 py-3">Active</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($agents as $agent)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $agent->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $agent->phone ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $agent->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $agent->employee?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($agent->is_active)
                                        <span class="text-xs font-semibold text-emerald-700">Yes</span>
                                    @else
                                        <span class="text-xs text-slate-400">No</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.book.collection_agents.edit', $agent) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.book.collection_agents.destroy', $agent) }}" class="inline" data-swal-confirm="Remove this agent?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No collection agents yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($agents->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $agents->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
