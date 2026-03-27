@php
    $activeTab = 'financial';
    $panelTitle = 'Financial Reports';
    $headerTone = 'bg-emerald-700';
    $panelItems = [
        ['label' => 'Profit and Loss Summary', 'route' => 'property.reports.financial.profit_loss_summary'],
        ['label' => 'Profit and Loss Comparison', 'route' => 'property.reports.financial.profit_loss_comparison'],
        ['label' => 'Profit and Loss by Department', 'route' => 'property.reports.financial.profit_loss_department'],
        ['label' => 'Profit and Loss by Months', 'route' => 'property.reports.financial.profit_loss_months'],
        ['label' => 'Manufacturing Account', 'route' => 'property.reports.financial.manufacturing_account'],
        ['label' => 'Balance Sheet Standard', 'route' => 'property.reports.financial.balance_sheet_standard'],
        ['label' => 'Balance Sheet Itemised', 'route' => 'property.reports.financial.balance_sheet_itemised'],
    ];
@endphp

@include('property.agent.reports.partials.module_page')
