@php use App\Support\Property\ResponsiveTableColumns; @endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Linked properties ({{ $periodLabel }})</h3>
    </div>

    @include('property.agent.landlords.partials.responsive-table-section', [
        'title' => 'Portfolio breakdown',
        'columns' => $portfolioColumns,
        'rows' => $portfolioRows,
        'columnConfig' => ResponsiveTableColumns::landlordPortfolio(),
        'emptyTitle' => 'No linked properties',
        'emptyHint' => 'Use Quick Actions → Link property to attach an unlinked property to this landlord.',
        'tableMinWidth' => '960px',
    ])
</div>
