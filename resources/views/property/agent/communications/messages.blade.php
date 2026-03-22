<x-property.workspace
    title="SMS / email"
    subtitle="Transactional log — delivery status, template used, and correlation to tenant or invoice."
    back-route="property.communications.index"
    :stats="[
        ['label' => 'Sent (24h)', 'value' => '0', 'hint' => 'All channels'],
        ['label' => 'Failed', 'value' => '0', 'hint' => 'Needs retry'],
        ['label' => 'Queued', 'value' => '0', 'hint' => 'Outbound'],
    ]"
    :columns="['Time', 'Channel', 'To', 'Template', 'Related', 'Provider ID', 'Status']"
    empty-title="No messages logged"
    empty-hint="Integrate Africa’s Talking / Twilio / SMTP with webhooks for delivery receipts."
>
    <x-slot name="toolbar">
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Channel: All</option>
            <option value="sms">SMS</option>
            <option value="email">Email</option>
        </select>
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search phone or email…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
</x-property.workspace>
