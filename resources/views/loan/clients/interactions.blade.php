<x-loan-layout>
    <x-loan.page
        title="Client Interactions"
        subtitle="Track outreach comments, sources, and client status."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.interactions.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Log interaction
            </a>
        </x-slot>

        <form method="get" action="{{ route('loan.clients.interactions') }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-3 sm:p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Source</label>
                    <select name="source_user_id" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">-- Source --</option>
                        @foreach (($sourceUsers ?? collect()) as $sourceUser)
                            <option value="{{ $sourceUser->id }}" @selected((int) ($sourceUserId ?? 0) === (int) $sourceUser->id)>{{ $sourceUser->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Type</label>
                    <select name="type" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All types</option>
                        @foreach (['call', 'visit', 'sms', 'email', 'whatsapp', 'other'] as $t)
                            <option value="{{ $t }}" @selected(($type ?? '') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ml-auto">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search client</label>
                    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search client" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <x-primary-button type="submit" class="h-10">{{ __('Filter') }}</x-primary-button>
                <a href="{{ route('loan.clients.interactions') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Reset</a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('loan.clients.interactions', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.clients.interactions', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.clients.interactions', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Interaction log</h2>
                <p class="text-xs text-slate-500">{{ $interactions->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Loan Officer</th>
                            <th class="px-5 py-3">Comment</th>
                            <th class="px-5 py-3">Source</th>
                            <th class="px-5 py-3">Client Status</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($interactions as $row)
                            @php($rowTarget = $row->loanClient ? route('loan.clients.show', $row->loanClient) : route('loan.clients.interactions.show', $row))
                            <tr
                                class="hover:bg-slate-50/80 cursor-pointer"
                                role="link"
                                tabindex="0"
                                onclick="if (event.target.closest('a, button, input, select, textarea, form, label, summary, details')) return; window.location.href='{{ $rowTarget }}';"
                                onkeydown="if((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, input, select, textarea, form, label, summary, details')){event.preventDefault(); window.location.href='{{ $rowTarget }}';}"
                            >
                                <td class="px-5 py-3">
                                    @if ($row->loanClient)
                                        <a href="{{ route('loan.clients.show', $row->loanClient) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $row->loanClient->full_name }}</a>
                                        <p class="text-xs text-slate-500">{{ $row->loanClient->phone ?: '—' }}</p>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->loanClient?->assignedEmployee?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-sm truncate" title="{{ $row->notes ?: $row->subject }}">
                                    {{ $row->notes ?: ($row->subject ?: '—') }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->user?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @php($status = strtolower((string) ($row->loanClient?->client_status ?? 'n/a')))
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold
                                        {{ $status === 'active' ? 'text-emerald-700' : '' }}
                                        {{ $status === 'dormant' ? 'text-amber-700' : '' }}
                                        {{ in_array($status, ['blacklisted', 'watchlist'], true) ? 'text-purple-700' : '' }}
                                        {{ !in_array($status, ['active', 'dormant', 'blacklisted', 'watchlist'], true) ? 'text-slate-600' : '' }}">
                                        <span class="inline-block h-2 w-2 rounded-full
                                            {{ $status === 'active' ? 'bg-emerald-500' : '' }}
                                            {{ $status === 'dormant' ? 'bg-amber-500' : '' }}
                                            {{ in_array($status, ['blacklisted', 'watchlist'], true) ? 'bg-purple-500' : '' }}
                                            {{ !in_array($status, ['active', 'dormant', 'blacklisted', 'watchlist'], true) ? 'bg-slate-400' : '' }}"></span>
                                        {{ ucfirst($status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.clients.interactions.show', $row) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm" onclick="event.stopPropagation();">View</a>
                                    @if ($row->loanClient)
                                        <a href="{{ route('loan.clients.interactions.for_client.create', $row->loanClient) }}" class="ml-3 text-slate-700 hover:text-slate-900 font-medium text-sm" onclick="event.stopPropagation();">Conversation</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No interactions logged yet.</td>
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
