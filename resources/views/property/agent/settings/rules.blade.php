<x-property.workspace
    title="System rules"
    subtitle="Penalties, reminder cadence, auto-allocation, and guardrails — every rule has an owner and audit trail."
    back-route="property.settings.index"
    :stats="[
        ['label' => 'Active rules', 'value' => '0', 'hint' => 'Automation'],
        ['label' => 'Dry-run mode', 'value' => 'Off', 'hint' => 'Global'],
    ]"
    :columns="['Rule', 'Module', 'Trigger', 'Action', 'Scope', 'Version', 'Status']"
    empty-title="No system rules"
    empty-hint="Prefer effective dates and simulation counts before activation."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'settings-automation-rule') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >New rule</a>
    </x-slot>
    <x-slot name="footer">
        <p>Examples: send reminder T-3/T+1/T+7 from due date; auto-allocate M-Pesa using last 4 of account ref; cap penalty at X% of rent.</p>
    </x-slot>
</x-property.workspace>
