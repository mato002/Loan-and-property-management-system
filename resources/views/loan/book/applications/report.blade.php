<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Pipeline view</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">All applications (report)</h2>
                <p class="text-xs text-slate-500 mt-1">{{ $applications->total() }} rows · use filters/export when APIs connect.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Ref</th>
                            <th class="px-5 py-3">Client #</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Term</th>
                            <th class="px-5 py-3">Stage</th>
                            <th class="px-5 py-3">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($applications as $app)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs">{{ $app->reference }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $app->loanClient->client_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $app->loanClient->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $app->product_name }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $app->amount_requested, 2) }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ $app->term_months }} mo</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $app->stage) }}</td>
                                <td class="px-5 py-3 text-slate-500 tabular-nums text-xs">{{ $app->submitted_at?->format('Y-m-d') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">No data.</td>
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
