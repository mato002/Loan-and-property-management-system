<x-property.workspace
    title="Commission settings"
    subtitle="Default fee schedules, leasing bonuses, and owner-specific overrides."
    back-route="property.settings.index"
    :stats="[
        ['label' => 'Active plans', 'value' => '0', 'hint' => 'Fee models'],
        ['label' => 'Overrides', 'value' => '0', 'hint' => 'Owner-specific'],
    ]"
    :columns="['Plan', 'Scope', 'Mgmt fee', 'Leasing fee', 'Renewal fee', 'Effective', 'Status']"
    empty-title="No commission plans"
    empty-hint="Version plans — never edit history in place; create effective-dated rows."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'settings-commission-plan') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >New plan</a>
    </x-slot>
</x-property.workspace>
