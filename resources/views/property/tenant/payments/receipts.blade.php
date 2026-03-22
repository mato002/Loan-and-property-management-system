<x-property.workspace
    title="Receipts (eTIMS)"
    subtitle="Official receipts for settled amounts — download PDF when eTIMS is connected."
    back-route="property.tenant.payments.index"
    :stats="[
        ['label' => 'Receipts', 'value' => '0', 'hint' => 'On file'],
    ]"
    :columns="['Date', 'Receipt #', 'Amount', 'Tax', 'Invoice', 'eTIMS status', 'Download']"
    empty-title="No receipts"
    empty-hint="Receipts generate after payment is allocated and fiscal payload succeeds."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.tenant.workspace.form.show', 'tenant-email-receipts') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Email all</a>
    </x-slot>
</x-property.workspace>
