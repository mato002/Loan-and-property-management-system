<x-loan-layout>
    <x-loan.page
        title="{{ $default_client_group->name }}"
        subtitle="Manage grouped clients with officer and performance filters."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.default_groups.edit', $default_client_group) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Edit group
            </a>
            <a href="{{ route('loan.clients.default_groups') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                All groups
            </a>
            <a href="{{ route('loan.clients.default_groups.show', array_merge(['default_client_group' => $default_client_group->id], request()->query(), ['export' => 'csv'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
            <a href="{{ route('loan.clients.default_groups.show', array_merge(['default_client_group' => $default_client_group->id], request()->query(), ['export' => 'xls'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
            <a href="{{ route('loan.clients.default_groups.show', array_merge(['default_client_group' => $default_client_group->id], request()->query(), ['export' => 'pdf'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
        </x-slot>

        @if ($default_client_group->description)
            <p class="text-sm text-slate-600 max-w-3xl">{{ $default_client_group->description }}</p>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5 mb-4">
            <form method="get" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Client groups</label>
                    <select class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm" onchange="if (this.value) window.location.href=this.value;">
                        @foreach (($groups ?? collect()) as $group)
                            <option value="{{ route('loan.clients.default_groups.show', $group) }}" @selected((int) $group->id === (int) $default_client_group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Loan officer</label>
                    <select name="employee_id" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">Corporate</option>
                        @foreach (($employees ?? collect()) as $employee)
                            <option value="{{ $employee->id }}" @selected((int) ($employeeId ?? 0) === (int) $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ml-auto">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Search" class="h-10 w-52 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm" />
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
            </form>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Group members</h2>
                    <span class="text-xs text-slate-500">{{ $members->total() }} client(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3">Name</th>
                                <th class="px-5 py-3">Contact</th>
                                <th class="px-5 py-3">Id Number</th>
                                <th class="px-5 py-3">Loan Officer</th>
                                <th class="px-5 py-3 text-right">Balance</th>
                                <th class="px-5 py-3 text-right">Days</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($members as $client)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-5 py-3 font-medium text-slate-900">
                                        <a href="{{ route('loan.clients.show', $client) }}" class="text-indigo-600 hover:text-indigo-500">{{ $client->full_name }}</a>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $client->phone ?: '—' }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $client->id_number ?: '—' }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $client->assignedEmployee?->full_name ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) ($client->total_balance ?? 0), 0) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ (int) ($client->max_dpd ?? 0) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="post" action="{{ route('loan.clients.default_groups.members.destroy', [$default_client_group, $client]) }}" class="inline" data-swal-confirm="Remove from group?">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-slate-500">No members match this filter yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($members->hasPages())
                    <div class="px-5 py-4 border-t border-slate-100">
                        {{ $members->links() }}
                    </div>
                @endif
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 sm:p-6 h-fit">
                <h2 class="text-sm font-semibold text-slate-700 mb-4">Add client</h2>
                @if ($availableClients->isEmpty())
                    <p class="text-sm text-slate-500">All active clients are already in this group, or no clients exist yet.</p>
                @else
                    <form method="post" action="{{ route('loan.clients.default_groups.members.store', $default_client_group) }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="loan_client_id" value="Client" />
                            <select id="loan_client_id" name="loan_client_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Select —</option>
                                @foreach ($availableClients as $c)
                                    <option value="{{ $c->id }}">{{ $c->client_number }} — {{ $c->full_name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('loan_client_id')" />
                        </div>
                        <x-primary-button type="submit">{{ __('Add to group') }}</x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
