<x-property.workspace
    title="Applications"
    subtitle="Not implemented yet — future phase for applications and screening; use Setup a listing + Vacant units for public pages today."
    back-route="property.listings.index"
    :stats="[
        ['label' => 'In review', 'value' => '0', 'hint' => 'Queued'],
        ['label' => 'Approved', 'value' => '0', 'hint' => 'MTD'],
        ['label' => 'Declined', 'value' => '0', 'hint' => 'MTD'],
    ]"
    :columns="['Application', 'Unit', 'Applicant', 'Income', 'Submitted', 'Checks', 'Decision']"
    empty-title="Applications not enabled"
    empty-hint="When launched, store consent for credit checks and retain decision reasons for fair housing audit."
>
    <x-slot name="footer">
        <p>Keep this module opt-in per jurisdiction where screening laws apply.</p>
    </x-slot>
</x-property.workspace>
