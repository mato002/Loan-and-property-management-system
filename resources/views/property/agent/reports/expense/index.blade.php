@php
    $activeTab = 'expense';
    $panelTitle = 'Expense Reports';
    $headerTone = 'bg-teal-700';
    $panelItems = [
        ['label' => 'Income & Expenses Summary', 'route' => 'property.reports.expense.income_expenses_summary'],
        ['label' => 'Maintenance Expense Report', 'route' => 'property.reports.expense.maintenance_expense'],
        ['label' => 'Utility Billing Expenses', 'route' => 'property.reports.expense.utility_billing'],
        ['label' => 'Vendor Expense Work Report', 'route' => 'property.reports.expense.vendor_expense_work'],
        ['label' => 'Expense Cash Book View', 'route' => 'property.reports.expense.cash_book'],
    ];
@endphp

@include('property.agent.reports.partials.module_page')
