<x-property.workspace
    title="Move-in / move-out"
    subtitle="Checklists, meter reads, keys, deposit reconciliation, and handover sign-off."
    back-route="property.tenants.index"
    :stats="[
        ['label' => 'Scheduled move-ins', 'value' => '0', 'hint' => 'Next 30 days'],
        ['label' => 'Scheduled move-outs', 'value' => '0', 'hint' => 'Next 30 days'],
        ['label' => 'Deposit disputes', 'value' => '0', 'hint' => 'Open'],
        ['label' => 'Completed (MTD)', 'value' => '0', 'hint' => 'Closed workflows'],
    ]"
    :columns="['Type', 'Unit', 'Tenant', 'Target date', 'Checklist', 'Deposit', 'Inspector', 'Status']"
    empty-title="No movement workflows"
    empty-hint="Each move should produce a printable pack: photos, readings, signatures, and inventory."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'tenants-schedule-move') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >Schedule move</a>
    </x-slot>
</x-property.workspace>
