@props(['active' => null])

@php
    $items = [
        ['route' => 'property.settings.system_setup', 'label' => 'System setup hub'],
        ['route' => 'property.settings.system_setup.forms', 'label' => 'Form adjustments'],
        ['route' => 'property.settings.system_setup.workflows', 'label' => 'Workflow adjustments'],
        ['route' => 'property.settings.system_setup.templates', 'label' => 'Template adjustments'],
    ];
    if (auth()->user()->hasPmPermission('settings.access.manage')) {
        $items[] = ['route' => 'property.settings.system_setup.access', 'label' => 'Access control'];
    }
    $activeRoute = $active ?? request()->route()?->getName();
@endphp

<x-property.responsive.quick-action-grid {{ $attributes->merge(['class' => 'mb-4']) }}>
    @foreach ($items as $item)
        <a
            href="{{ route($item['route']) }}"
            data-turbo-frame="property-main"
            @class([
                'quick-action-btn border',
                $activeRoute === $item['route']
                    ? 'bg-blue-600 border-blue-600 text-white'
                    : 'border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
            ])
            @if ($activeRoute === $item['route']) aria-current="page" @endif
        >{{ $item['label'] }}</a>
    @endforeach
</x-property.responsive.quick-action-grid>
