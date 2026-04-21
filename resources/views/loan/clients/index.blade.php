<x-loan-layout>
    <x-loan.page
        title="Clients"
        subtitle="Active loan clients, assignments, and contact details."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add client
            </a>
        </x-slot>

        <form method="get" action="{{ route('loan.clients.index') }}" class="flex flex-col sm:flex-row gap-2 sm:items-end">
            <div class="flex-1 max-w-md">
                <x-input-label for="q" value="Search" />
                <x-text-input id="q" name="q" type="search" class="mt-1 block w-full" :value="request('q')" placeholder="Name, number, phone…" />
            </div>
            <x-primary-button type="submit" class="shrink-0">Filter</x-primary-button>
            @if (request()->filled('q'))
                <a href="{{ route('loan.clients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shrink-0">Clear</a>
            @endif
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">All clients</h2>
                <p class="text-xs text-slate-500">{{ $clients->total() }} record(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Number</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Assigned</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($clients as $client)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $client->client_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $client->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $client->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $client->assignedEmployee?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $client->client_status }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    <div class="flex flex-col">
                                        @if ($client->phone)<span><x-phone-link :value="$client->phone" /></span>@endif
                                        @if ($client->email)<span class="text-xs">{{ $client->email }}</span>@endif
                                        @if (! $client->phone && ! $client->email)<span>—</span>@endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.clients.show', $client) }}" class="text-slate-600 hover:text-slate-800 font-medium text-sm mr-3">View</a>
                                    <a href="{{ route('loan.clients.edit', $client) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.clients.destroy', $client) }}" class="inline" data-swal-confirm="Remove this client?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                    No clients yet. Use <span class="font-medium text-slate-700">Add Client</span> or convert a lead.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($clients->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $clients->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
