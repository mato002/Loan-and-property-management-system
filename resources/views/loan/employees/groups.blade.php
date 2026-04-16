<x-loan-layout>
    <x-loan.page
        title="Staff groups"
        subtitle="Create teams and open a group to add or remove members."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.groups.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Create group
            </a>
        </x-slot>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Group name or description..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        @foreach ([12, 24, 36, 60, 120] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 24) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.employees.groups') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <a href="{{ route('loan.employees.groups', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.employees.groups', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.employees.groups', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse ($groups as $group)
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex flex-col gap-2">
                    <h2 class="text-base font-semibold text-slate-900">{{ $group->name }}</h2>
                    <p class="text-sm text-slate-500 flex-1">{{ $group->description ?: 'No description.' }}</p>
                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 mt-1">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $group->employees_count }} {{ $group->employees_count === 1 ? 'member' : 'members' }}</span>
                        <a href="{{ route('loan.employees.groups.show', $group) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">Manage</a>
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 xl:col-span-3 bg-white border border-slate-200 rounded-xl shadow-sm p-10 text-center text-slate-500 text-sm">
                    No groups yet. <a href="{{ route('loan.employees.groups.create') }}" class="text-indigo-600 font-semibold hover:underline">Create your first group</a>.
                </div>
            @endforelse
        </div>

        @if ($groups->hasPages())
            <div class="mt-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                {{ $groups->links() }}
            </div>
        @endif
    </x-loan.page>
</x-loan-layout>
