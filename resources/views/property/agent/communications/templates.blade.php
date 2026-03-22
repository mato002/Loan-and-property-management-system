<x-property.workspace
    title="Templates"
    subtitle="Rent reminders, notices, and broadcasts — merge fields validated before save."
    back-route="property.communications.index"
    :stats="[
        ['label' => 'Templates', 'value' => '0', 'hint' => 'Active'],
        ['label' => 'Draft', 'value' => '0', 'hint' => 'Unpublished'],
    ]"
    :columns="['Name', 'Channel', 'Category', 'Version', 'Last edited', 'Owner', 'Actions']"
    empty-title="No templates"
    empty-hint="Use merge placeholders (tenant name, amount due, due date) with a linter to prevent broken sends."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'communications-template') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >New template</a>
    </x-slot>
</x-property.workspace>
