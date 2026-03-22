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
    </x-loan.page>
</x-loan-layout>
