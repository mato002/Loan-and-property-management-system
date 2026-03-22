<x-loan-layout>
    <x-loan.page
        :title="$staff_group->name"
        subtitle="Add or remove employees in this group."
    >
        <x-slot name="actions">
            <form method="post" action="{{ route('loan.employees.groups.destroy', $staff_group) }}" class="inline" data-swal-confirm="Delete this group and remove all memberships?">
                @csrf
                @method('delete')
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 transition-colors">
                    Delete group
                </button>
            </form>
            <a href="{{ route('loan.employees.groups') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                All groups
            </a>
        </x-slot>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Members</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($staff_group->employees as $employee)
                        <li class="px-5 py-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $employee->full_name }}</p>
                                <p class="text-xs text-slate-500">{{ $employee->employee_number }} @if($employee->department) · {{ $employee->department }} @endif</p>
                            </div>
                            <form method="post" action="{{ route('loan.employees.groups.members.destroy', [$staff_group, $employee]) }}" data-swal-confirm="Remove from group?">
                                @csrf
                                @method('delete')
                                <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Remove</button>
                            </form>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-slate-500">No members yet. Add someone from the right.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-800 mb-4">Add member</h2>
                @if ($availableEmployees->isEmpty())
                    <p class="text-sm text-slate-500">Everyone is already in this group, or you have no employees. <a href="{{ route('loan.employees.create') }}" class="text-indigo-600 font-medium hover:underline">Add an employee</a>.</p>
                @else
                    <form method="post" action="{{ route('loan.employees.groups.members.store', $staff_group) }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="employee_id" value="Employee" />
                            <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">Choose…</option>
                                @foreach ($availableEmployees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_number }})</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
                        </div>
                        <x-primary-button>{{ __('Add to group') }}</x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
