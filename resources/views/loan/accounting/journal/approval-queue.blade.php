<x-loan-layout>
    <x-loan.page title="Controlled Journal Approval Queue" subtitle="Review journals routed by controlled-account governance rules.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.journal.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Journal Entries</a>
        </x-slot>

        @include('loan.accounting.partials.flash')

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-3 py-2">Journal Reference</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Created By</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="px-3 py-2">Required Approver(s)</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        @php
                            $entry = $row->journalEntry;
                            $required = collect($row->required_approver_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();
                            $requiredLabel = $row->required_approval_type === 'role'
                                ? ('Role: '.($row->required_role ?: 'N/A'))
                                : ($required->isEmpty() ? 'No assigned approvers' : $required->join(', '));
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 font-mono text-xs">{{ $entry?->reference ?: ('JE-'.$row->accounting_journal_entry_id) }}</td>
                            <td class="px-3 py-2">{{ optional($entry?->entry_date)->format('Y-m-d') }}</td>
                            <td class="px-3 py-2">{{ $entry?->createdByUser?->name ?? 'System' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $row->reason_detail ?: ucfirst(str_replace('_', ' ', $row->reason_code)) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ $requiredLabel }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $row->status === 'pending' ? 'border-orange-200 bg-orange-50 text-orange-700' : ($row->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700') }}">
                                    {{ ucfirst($row->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    @if ($row->status === 'pending')
                                        <form method="post" action="{{ route('loan.accounting.journal.approval_queue.approve', $row) }}">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-green-300 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 hover:bg-green-100">Approve &amp; Post</button>
                                        </form>
                                        <form method="post" action="{{ route('loan.accounting.journal.approval_queue.reject', $row) }}" class="flex items-center gap-2">
                                            @csrf
                                            <input type="text" name="rejection_reason" required maxlength="500" placeholder="Rejection reason" class="w-40 rounded-lg border border-red-200 px-2 py-1 text-xs">
                                            <button type="submit" class="rounded-lg border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Reject</button>
                                        </form>
                                    @else
                                        <a href="{{ route('loan.accounting.journal.show', $entry) }}" class="rounded-lg border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100">View Details</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-slate-500">No journals pending controlled-account approval.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="mt-4">{{ $rows->links() }}</div>
        @endif
    </x-loan.page>
</x-loan-layout>
