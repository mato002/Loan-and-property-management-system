<x-loan-layout>
    <x-loan.page
        title="Interactions"
        subtitle="Calls, visits, and messages logged against leads and clients."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.interactions.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Log interaction
            </a>
        </x-slot>

        <form method="get" action="{{ route('loan.clients.interactions') }}" class="flex flex-col sm:flex-row gap-2 sm:items-end">
            <div class="max-w-xs">
                <x-input-label for="type" value="Interaction type" />
                <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All types</option>
                    @foreach (['call', 'visit', 'sms', 'email', 'whatsapp', 'other'] as $t)
                        <option value="{{ $t }}" @selected(request('type') === $t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <x-primary-button type="submit" class="shrink-0">Filter</x-primary-button>
            @if (request()->filled('type'))
                <a href="{{ route('loan.clients.interactions') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shrink-0">Clear</a>
            @endif
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Activity log</h2>
                <p class="text-xs text-slate-500">{{ $interactions->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3">Person</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Subject</th>
                            <th class="px-5 py-3">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($interactions as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $row->interacted_at->format('M j, Y H:i') }}</td>
                                <td class="px-5 py-3">
                                    @if ($row->loanClient)
                                        <a href="{{ route('loan.clients.show', $row->loanClient) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $row->loanClient->full_name }}</a>
                                        <p class="text-xs text-slate-500">{{ $row->loanClient->client_number }} · {{ $row->loanClient->kind }}</p>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ ucfirst($row->interaction_type) }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-xs truncate" title="{{ $row->subject }}">{{ $row->subject ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-slate-500">No interactions logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($interactions->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $interactions->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
