<x-property.workspace
    title="Bulk messaging"
    subtitle="Campaigns to segments — arrears, renewals, inspections — with throttling and unsubscribe respect."
    back-route="property.communications.index"
    :stats="[
        ['label' => 'Active campaigns', 'value' => '0', 'hint' => 'Running'],
        ['label' => 'Recipients (MTD)', 'value' => '0', 'hint' => 'Unique'],
        ['label' => 'Opt-outs', 'value' => '0', 'hint' => 'Honored'],
    ]"
    :columns="['Campaign', 'Segment', 'Channel', 'Scheduled', 'Sent', 'Failed', 'Status']"
    empty-title="No campaigns"
    empty-hint="Preview + test send mandatory; store segment SQL or filter JSON for audit."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'communications-bulk-campaign') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >New campaign</a>
    </x-slot>
</x-property.workspace>
