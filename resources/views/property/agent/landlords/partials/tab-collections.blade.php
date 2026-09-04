@php use App\Support\Property\ResponsiveTableColumns; @endphp

@include('property.agent.landlords.partials.responsive-table-section', [
    'title' => 'Collections ('.$periodLabel.')',
    'columns' => $collectionColumns,
    'rows' => $collectionRows,
    'columnConfig' => ResponsiveTableColumns::landlordCollections(),
    'emptyTitle' => 'No collections in this period',
    'emptyHint' => 'Rent and utility payments allocated to this landlord\'s properties will appear here.',
    'tableMinWidth' => '640px',
])
