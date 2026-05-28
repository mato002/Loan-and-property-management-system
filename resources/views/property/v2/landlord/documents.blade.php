<x-property-layout>
    <x-slot name="header">Document center</x-slot>

    <x-property.page
        title="Document center"
        subtitle="Central place for invoice, maintenance, and export artifacts from your linked properties."
    >
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('property.landlord.earnings.history.export') }}" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/60">Ledger history CSV</a>
            <a href="{{ route('property.landlord.reports.income.export') }}" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/60">Income CSV</a>
            <a href="{{ route('property.landlord.reports.expenses.export') }}" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/60">Expenses CSV</a>
            <a href="{{ route('property.landlord.properties.export') }}" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/60">Properties CSV</a>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4">
                <h3 class="text-sm font-semibold mb-3">Recent invoices</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($invoiceDocs as $inv)
                        <li class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/70 pb-2">
                            <span class="min-w-0 truncate">{{ $inv->invoice_no }} · {{ $inv->unit?->property?->name ?? '—' }}/{{ $inv->unit?->label ?? '—' }}</span>
                            <span class="text-xs text-slate-500">{{ $inv->issue_date?->format('Y-m-d') }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">No invoice artifacts yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4">
                <h3 class="text-sm font-semibold mb-3">Recent maintenance records</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($maintenanceDocs as $job)
                        <li class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/70 pb-2">
                            <span class="min-w-0 truncate">Job #{{ $job->id }} · {{ $job->request?->unit?->property?->name ?? '—' }}/{{ $job->request?->unit?->label ?? '—' }}</span>
                            <span class="text-xs text-slate-500">{{ $job->updated_at->format('Y-m-d') }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">No maintenance artifacts yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
