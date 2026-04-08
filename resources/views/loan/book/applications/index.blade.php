<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.app_loans_report') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Application report
            </a>
            <a href="{{ route('loan.book.applications.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Create application
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Pipeline</h2>
                <p class="text-xs text-slate-500">{{ $applications->total() }} file(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Ref</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Source</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Stage</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($applications as $app)
                            @php
                                $sourceLabel = match ((string) ($app->submission_source ?? '')) {
                                    'tenant_portal' => 'Tenant portal',
                                    'landlord_portal' => 'Landlord portal',
                                    'manual_internal' => 'Manual/Internal',
                                    default => (function () use ($app) {
                                        $notes = strtolower((string) ($app->notes ?? ''));
                                        return str_contains($notes, 'tenant portal')
                                            ? 'Tenant portal'
                                            : (str_contains($notes, 'landlord portal') ? 'Landlord portal' : 'Manual/Internal');
                                    })(),
                                };
                                $sourceClass = str_contains($sourceLabel, 'portal')
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                    : 'bg-slate-100 text-slate-700 ring-slate-200';
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $app->reference }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $app->loanClient->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $app->product_name }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $sourceClass }}">{{ $sourceLabel }}</span>
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $app->amount_requested, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $app->stage) }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $app->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.book.applications.edit', $app) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.book.applications.destroy', $app) }}" class="inline" data-swal-confirm="Delete this application? It must not have a loan yet.">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">No applications yet. Create one to start LoanBook.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($applications->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $applications->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
