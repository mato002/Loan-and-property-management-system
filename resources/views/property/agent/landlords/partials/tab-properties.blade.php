@php use App\Support\Property\ResponsiveTableColumns; @endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Linked properties ({{ $periodLabel }})</h3>
        @if (auth()->user()?->hasPmPermission('properties.manage'))
            <button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" data-property-modal-open="showHubLinkProperty" @click="showHubLinkProperty = true">Link property</button>
        @endif
    </div>

    @include('property.agent.landlords.partials.responsive-table-section', [
        'title' => 'Portfolio breakdown',
        'columns' => $portfolioColumns,
        'rows' => $portfolioRows,
        'columnConfig' => ResponsiveTableColumns::landlordPortfolio(),
        'emptyTitle' => 'No linked properties',
        'emptyHint' => 'Use Link property above to attach an unlinked property to this landlord.',
        'tableMinWidth' => '960px',
    ])
</div>
