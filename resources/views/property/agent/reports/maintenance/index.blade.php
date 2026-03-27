@php
    $activeTab = 'maintenance';
    $panelTitle = 'Maintenance Reports';
    $headerTone = 'bg-teal-700';
    $panelItems = [
        ['label' => 'Maintenance History', 'route' => 'property.reports.maintenance.history'],
        ['label' => 'Maintenance Cost Report', 'route' => 'property.reports.maintenance.cost'],
        ['label' => 'Issue Frequency Report', 'route' => 'property.reports.maintenance.frequency'],
        ['label' => 'Audit Trail', 'route' => 'property.reports.maintenance.audit_trail'],
        ['label' => 'Email Logs', 'route' => 'property.reports.maintenance.email_logs'],
        ['label' => 'Log In/Out Logs', 'route' => 'property.reports.maintenance.login_logs'],
    ];
@endphp

@include('property.agent.reports.partials.module_page')
