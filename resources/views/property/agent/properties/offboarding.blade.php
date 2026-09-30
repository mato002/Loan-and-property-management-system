<x-property.workspace :compact-list="false"
    :title="'Offboarding: '.$property->name"
    subtitle="Safe property wind-down — no financial records are deleted."
    back-route="property.properties.show"
    :back-route-params="['property' => $property->id, 'tab' => 'offboarding']"
    :show-workspace-tabs="false"
    :stats="[]"
    :columns="[]"
>
    @include('property.agent.properties.partials.offboarding_panel')
</x-property.workspace>