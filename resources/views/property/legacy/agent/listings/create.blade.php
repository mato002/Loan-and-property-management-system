<x-property.workspace
    :compact-list="true"
    :show-search="false"
    :legacy-toolbar="false"
    title="Setup a public listing"
    back-route="property.listings.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="actions">
        <a
            href="{{ route('property.properties.units', absolute: false) }}"
            data-turbo-frame="property-main"
            data-property-nav="property.properties.units"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            Properties → Units
        </a>
    </x-slot>

    @include('property.agent.listings.partials.create_workspace')
</x-property.workspace>
