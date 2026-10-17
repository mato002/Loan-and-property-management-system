@php use App\Support\Property\ResponsiveTableColumns; @endphp

<div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
    <div>
        <p class="text-xs font-medium uppercase tracking-wide text-emerald-800 dark:text-emerald-300">Total collected this period</p>
        <p class="text-lg font-semibold text-emerald-950 dark:text-emerald-100">{{ \App\Services\Property\PropertyMoney::kes((float) ($collectionsTotal ?? 0)) }}</p>
    </div>
    <p class="text-xs text-emerald-800 dark:text-emerald-300">Showing the latest 20 allocated collections below.</p>
</div>

@include('property.agent.landlords.partials.responsive-table-section', [
    'title' => 'Collections ('.$periodLabel.')',
    'columns' => $collectionColumns,
    'rows' => $collectionRows,
    'columnConfig' => ResponsiveTableColumns::landlordCollections(),
    'emptyTitle' => 'No collections in this period',
    'emptyHint' => 'Rent and utility payments allocated to this landlord\'s properties will appear here.',
    'tableMinWidth' => '760px',
])
