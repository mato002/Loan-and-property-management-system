<x-property.workspace
    title="Property users"
    subtitle="Switch between roles and the people who can sign in."
    back-route="property.settings.index"
    :stats="$stats"
    :columns="[]"
    :legacy-toolbar="false"
    :show-search="false"
>
    <x-slot name="above">
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.roles'])
        @include('property.agent.settings.partials.roles_users_tabs')
    </x-slot>
</x-property.workspace>
