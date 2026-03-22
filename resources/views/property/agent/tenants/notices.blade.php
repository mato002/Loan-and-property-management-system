<x-property.workspace
    title="Notices"
    subtitle="Vacate, rent increase, eviction pathway, and statutory letters — template-driven with delivery proof."
    back-route="property.tenants.index"
    :stats="[
        ['label' => 'Draft', 'value' => '0', 'hint' => 'Awaiting review'],
        ['label' => 'Sent', 'value' => '0', 'hint' => 'Delivered'],
        ['label' => 'Acknowledged', 'value' => '0', 'hint' => 'Tenant confirmed'],
        ['label' => 'Escalated', 'value' => '0', 'hint' => 'Legal handoff'],
    ]"
    :columns="['Notice', 'Tenant', 'Unit', 'Type', 'Issued', 'Due response', 'Channel', 'Status']"
    empty-title="No notices issued"
    empty-hint="Merge fields from lease and jurisdiction rules; store PDF + delivery receipt per notice."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'tenants-notice') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >New notice</a>
    </x-slot>
    <x-slot name="toolbar">
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Template: All</option>
            <option value="vacate">Vacate</option>
            <option value="rent">Rent increase</option>
            <option value="statutory">Statutory</option>
        </select>
    </x-slot>
</x-property.workspace>
