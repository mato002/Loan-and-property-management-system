<x-property.workspace
    title="Receipts (eTIMS)"
    subtitle="Paid invoices shown as receipt stubs — connect eTIMS or your tax API to populate status and downloads."
    back-route="property.tenant.payments.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No receipts"
    empty-hint="Receipts appear when invoices are fully paid; fiscal integration can extend the eTIMS column."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.tenant.workspace.form.show', 'tenant-email-receipts') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Email all</a>
    </x-slot>
</x-property.workspace>
