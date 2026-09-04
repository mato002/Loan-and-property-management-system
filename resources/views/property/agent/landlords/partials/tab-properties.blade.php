@php use App\Support\Property\ResponsiveTableColumns; @endphp

@include('property.agent.landlords.partials.responsive-table-section', [
    'title' => 'Linked properties ('.$periodLabel.')',
    'columns' => $portfolioColumns,
    'rows' => $portfolioRows,
    'columnConfig' => ResponsiveTableColumns::landlordPortfolio(),
    'emptyTitle' => 'No linked properties',
    'emptyHint' => 'Attach this landlord from the property list or portfolio workspace.',
    'tableMinWidth' => '960px',
])
