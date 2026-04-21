<x-loan-layout>
    <x-loan.page
        title="Default groups"
        subtitle="Segment clients for campaigns, PAR reviews, and portfolio reporting."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.default_groups.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                New group
            </a>
        </x-slot>

        <form method="get" action="{{ route('loan.clients.default_groups') }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-3 sm:p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div class="flex-1 min-w-[220px]">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search groups</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Group name or description..." class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.clients.default_groups') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">All groups</h2>
                <p class="text-xs text-slate-500">{{ $groups->total() }} group(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Members</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($groups as $group)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3">
                                    <a href="{{ route('loan.clients.default_groups.show', $group) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $group->name }}</a>
                                    @if ($group->description)
                                        <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ $group->description }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $group->loan_clients_count }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.clients.default_groups.show', $group) }}" class="text-slate-600 hover:text-slate-800 font-medium text-sm mr-3">Members</a>
                                    <a href="{{ route('loan.clients.default_groups.edit', $group) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.clients.default_groups.destroy', $group) }}" class="inline" data-swal-confirm="Delete this group? Members will be unlinked.">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-12 text-center text-slate-500">
                                    No default groups yet. Create one to start segmenting clients.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($groups->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $groups->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
