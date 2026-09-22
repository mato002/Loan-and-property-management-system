{{-- Standalone page retained for bookmarks; controller redirects to the list modal. --}}
<x-property.workspace :compact-list="false"
    title="Import property register"
    subtitle="Upload a CSV from Passion's legacy system — one row per unit. Properties are grouped by property_name or property_code."
    back-route="property.properties.list"
    back-label="← Back to properties"
>
    <div class="p-5 sm:p-6">
        @include('property.agent.properties.partials.register_import_form')
    </div>
</x-property.workspace>
