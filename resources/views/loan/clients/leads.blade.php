<x-loan-layout>
    <x-loan.page
        title="Client leads"
        subtitle="Prospects before onboarding as full clients."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.leads.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Create a lead
            </a>
        </x-slot>

        <form method="get" action="{{ route('loan.clients.leads') }}" class="flex flex-col sm:flex-row gap-2 sm:items-end">
            <div class="flex-1 max-w-md">
                <x-input-label for="q" value="Search" />
                <x-text-input id="q" name="q" type="search" class="mt-1 block w-full" :value="request('q')" placeholder="Name, number, phone…" />
            </div>
            <x-primary-button type="submit" class="shrink-0">Filter</x-primary-button>
            @if (request()->filled('q'))
                <a href="{{ route('loan.clients.leads') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shrink-0">Clear</a>
            @endif
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">All leads</h2>
                <p class="text-xs text-slate-500">{{ $leads->total() }} record(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Number</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Assigned</th>
                            <th class="px-5 py-3">Phone</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leads as $lead)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $lead->client_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $lead->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $lead->lead_status ?? 'new' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $lead->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $lead->assignedEmployee?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $lead->phone ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.clients.show', $lead) }}" class="text-slate-600 hover:text-slate-800 font-medium text-sm mr-3">View</a>
                                    <form method="post" action="{{ route('loan.clients.leads.convert', $lead) }}" class="inline mr-3" data-swal-confirm="Convert this lead to a client?">
                                        @csrf
                                        <button type="submit" class="text-emerald-700 hover:text-emerald-600 font-medium text-sm">Convert</button>
                                    </form>
                                    <a href="{{ route('loan.clients.edit', $lead) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.clients.destroy', $lead) }}" class="inline" data-swal-confirm="Remove this lead?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                    No leads yet. Use <span class="font-medium text-slate-700">Create a Lead</span>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($leads->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $leads->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
