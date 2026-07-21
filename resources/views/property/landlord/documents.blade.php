<x-property-layout>
    <x-slot name="header">Document center</x-slot>

    <x-property.page title="Document center">
        <div class="flex flex-wrap gap-2 mb-4">
            <a href="{{ route('property.landlord.reports.owner_statement.pdf') }}" data-turbo="false" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">Owner statement PDF</a>
            <a href="{{ route('property.landlord.earnings.history.export') }}" data-turbo="false" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">Ledger CSV</a>
            <a href="{{ route('property.landlord.reports.income.export') }}" data-turbo="false" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">Income CSV</a>
            <a href="{{ route('property.landlord.reports.expenses.export') }}" data-turbo="false" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">Expenses CSV</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4">
                <h3 class="text-sm font-semibold mb-3">Invoices</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($invoiceDocs as $inv)
                        <li class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2">
                            <span class="min-w-0 truncate">{{ $inv->invoice_no }}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-slate-500">{{ $inv->issue_date?->format('Y-m-d') }}</span>
                                <a href="{{ route('property.landlord.documents.invoice', $inv) }}" data-turbo="false" class="text-xs text-emerald-700 hover:underline">PDF</a>
                            </div>
                        </li>
                    @empty
                        <li class="text-slate-500">No invoices.</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4">
                <h3 class="text-sm font-semibold mb-3">Maintenance</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($maintenanceDocs as $job)
                        <li class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2">
                            <a href="{{ route('property.landlord.maintenance.jobs.show', $job) }}" class="hover:underline">Job #{{ $job->id }}</a>
                            <span class="text-xs text-slate-500">{{ $job->updated_at->format('Y-m-d') }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">No records.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
