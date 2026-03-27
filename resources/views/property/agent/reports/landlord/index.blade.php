@php
    $activeTab = 'landlord';
    $panelTitle = 'Landlord Reports';
    $headerTone = 'bg-emerald-700';
    $panelItems = [
        ['label' => 'Landlord Statements', 'route' => 'property.reports.landlord.statements'],
        ['label' => "Landlord's Detailed Statement", 'route' => 'property.reports.landlord.detailed_statement'],
        ['label' => 'Landlords Balance Summary', 'route' => 'property.reports.landlord.balance_summary'],
        ['label' => 'Rental Income Commissions', 'route' => 'property.reports.landlord.rental_income_commissions'],
        ['label' => 'Rent Collection Report', 'route' => 'property.reports.landlord.rent_collection'],
        ['label' => 'Property Statement', 'route' => 'property.reports.landlord.property_statement'],
    ];
@endphp

@include('property.agent.reports.partials.module_page')
