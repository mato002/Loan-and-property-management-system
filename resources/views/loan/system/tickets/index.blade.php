<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.tickets.create') }}" class="inline-flex rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">New ticket</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <form method="get" action="{{ route('loan.system.tickets.index') }}" class="flex flex-wrap gap-3 items-end mb-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                <select name="status" class="rounded-lg border-slate-200 text-sm min-w-[140px]" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" name="mine" value="1" @checked(request()->boolean('mine')) onchange="this.form.submit()" />
                My tickets only
            </label>
            @if (request()->hasAny(['status', 'mine']))
                <a href="{{ route('loan.system.tickets.index') }}" class="text-sm text-indigo-600 hover:underline">Clear filters</a>
            @endif
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">Ticket</th>
                        <th class="px-5 py-3">Raised by</th>
                        <th class="px-5 py-3">Category</th>
                        <th class="px-5 py-3">Priority</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tickets as $t)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-5 py-3">
                                <a href="{{ route('loan.system.tickets.show', $t) }}" class="font-mono text-xs text-indigo-600 font-semibold hover:underline">{{ $t->ticket_number ?? '…' }}</a>
                                <div class="text-slate-800 font-medium mt-0.5 line-clamp-2">{{ $t->subject }}</div>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $t->user->name ?? '—' }}</td>
                            <td class="px-5 py-3 capitalize text-slate-600">{{ str_replace('_', ' ', $t->category) }}</td>
                            <td class="px-5 py-3 capitalize text-slate-600">{{ $t->priority }}</td>
                            <td class="px-5 py-3 capitalize text-slate-700">{{ str_replace('_', ' ', $t->status) }}</td>
                            <td class="px-5 py-3 text-right text-slate-500 whitespace-nowrap">{{ $t->updated_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">No tickets match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if ($tickets->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $tickets->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
