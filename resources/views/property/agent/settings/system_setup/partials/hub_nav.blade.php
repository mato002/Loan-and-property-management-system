@props(['active' => null])

@php
    use App\Support\Property\PropertyWorkspaceTabs;

    $items = [];
    if (! PropertyWorkspaceTabs::settingsPageNavIsRedundant()) {
        $items = PropertyWorkspaceTabs::settingsSubNavGroupTabs('System setup');
        $activeRoute = $active ?? request()->route()?->getName();
    }
@endphp

@if ($items !== [])
    <x-property.responsive.quick-action-grid {{ $attributes->merge(['class' => 'mb-4']) }}>
        @foreach ($items as $item)
            @php
                $isActive = $activeRoute === ($item['route'] ?? '')
                    || PropertyWorkspaceTabs::tabIsActive($item);
            @endphp
            <a
                href="{{ \App\Support\Property\PropertyWorkspaceTabs::tabUrl($item) }}"
                data-turbo-frame="property-main"
                @class([
                    'quick-action-btn border',
                    $isActive
                        ? 'bg-blue-600 border-blue-600 text-white'
                        : 'border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
                ])
                @if ($isActive) aria-current="page" @endif
            >{{ $item['label'] }}</a>
        @endforeach
    </x-property.responsive.quick-action-grid>
@endif
