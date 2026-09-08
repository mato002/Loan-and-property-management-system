@props(['active' => null])

@php
    use App\Support\Property\PropertyWorkspaceTabs;

    $tabs = [];
    if (! PropertyWorkspaceTabs::settingsPageNavIsRedundant()) {
        $tabs = PropertyWorkspaceTabs::tabsFor('settings');
        $activeRoute = $active ?? request()->route()?->getName();
    }
@endphp

@if ($tabs !== [])
    <x-property.responsive.quick-action-grid {{ $attributes->merge(['class' => 'mb-4']) }}>
        @foreach ($tabs as $tab)
            @php
                $isActive = $activeRoute === ($tab['route'] ?? '')
                    || PropertyWorkspaceTabs::tabIsActive($tab);
            @endphp
            <a
                href="{{ \App\Support\Property\PropertyWorkspaceTabs::tabUrl($tab) }}"
                data-turbo-frame="property-main"
                @class([
                    'quick-action-btn border',
                    $isActive
                        ? 'bg-blue-600 border-blue-600 text-white'
                        : 'border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
                ])
                @if ($isActive) aria-current="page" @endif
            >{{ $tab['label'] }}</a>
        @endforeach
    </x-property.responsive.quick-action-grid>
@endif
