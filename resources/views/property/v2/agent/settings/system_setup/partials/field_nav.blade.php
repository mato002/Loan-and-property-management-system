@props(['active' => null])

@php
    $items = [
        ['route' => 'property.settings.system_setup', 'label' => 'System setup hub'],
        ['route' => 'property.settings.system_setup.property_onboarding_fields', 'label' => 'Property onboarding fields'],
        ['route' => 'property.settings.system_setup.unit_fields', 'label' => 'Unit fields'],
        ['route' => 'property.settings.system_setup.amenity_fields', 'label' => 'Amenity fields'],
        ['route' => 'property.settings.system_setup.landlord_fields', 'label' => 'Landlord fields'],
        ['route' => 'property.settings.system_setup.lead_fields', 'label' => 'Lead fields'],
        ['route' => 'property.settings.system_setup.rental_application_fields', 'label' => 'Rental application fields'],
        ['route' => 'property.settings.system_setup.tenant_fields', 'label' => 'Tenant fields'],
        ['route' => 'property.settings.system_setup.lease_fields', 'label' => 'Lease fields'],
        ['route' => 'property.settings.system_setup.maintenance_fields', 'label' => 'Maintenance fields'],
        ['route' => 'property.settings.system_setup.vendor_fields', 'label' => 'Vendor fields'],
        ['route' => 'property.settings.system_setup.invoice_fields', 'label' => 'Invoice/payment fields'],
        ['route' => 'property.settings.system_setup.tenant_notice_fields', 'label' => 'Tenant notice fields'],
        ['route' => 'property.settings.system_setup.movement_fields', 'label' => 'Move-in/move-out fields'],
    ];
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
