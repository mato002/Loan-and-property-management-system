<x-loan-layout>
    <x-loan.page
        title="{{ $default_client_group->name }}"
        subtitle="Manage members linked to this default group."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.default_groups.edit', $default_client_group) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Edit group
            </a>
            <a href="{{ route('loan.clients.default_groups') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                All groups
            </a>
        </x-slot>

        @if ($default_client_group->description)
            <p class="text-sm text-slate-600 max-w-3xl">{{ $default_client_group->description }}</p>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Members</h2>
                    <span class="text-xs text-slate-500">{{ $default_client_group->loanClients->count() }} client(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3">Number</th>
                                <th class="px-5 py-3">Name</th>
                                <th class="px-5 py-3">Branch</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($default_client_group->loanClients as $client)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-5 py-3 font-mono text-xs">{{ $client->client_number }}</td>
                                    <td class="px-5 py-3 font-medium text-slate-900">
                                        <a href="{{ route('loan.clients.show', $client) }}" class="text-indigo-600 hover:text-indigo-500">{{ $client->full_name }}</a>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $client->branch ?? '—' }}</td>
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
                                    <td colspan="4" class="px-5 py-10 text-center text-slate-500">No members yet. Add clients from the panel on the right.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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
