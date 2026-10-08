@once
    <style>
        [data-maintenance-bell].is-empty { display: none !important; }
        .property-sidebar[data-collapsed="1"] [data-maintenance-bell-variant="inline"] { display: none !important; }
        .property-sidebar:not([data-collapsed="1"]) [data-maintenance-bell-variant="dot"] { display: none !important; }
    </style>
@endonce
@php
    $maintenanceBellCount = max(0, (int) ($count ?? 0));
    $maintenanceBellVariant = ($variant ?? 'inline') === 'dot' ? 'dot' : 'inline';
@endphp
<span
    data-maintenance-bell
    data-maintenance-bell-variant="{{ $maintenanceBellVariant }}"
    class="{{ $maintenanceBellCount < 1 ? 'is-empty ' : '' }}{{ $maintenanceBellVariant === 'dot' ? 'absolute -right-1.5 -top-1.5 h-4 min-w-[1rem] px-1' : 'ml-1.5 px-1.5 py-0.5' }} inline-flex items-center justify-center gap-0.5 rounded-full bg-rose-500 text-[10px] font-bold leading-none text-white shadow-sm"
    title="{{ $maintenanceBellCount }} open maintenance {{ $maintenanceBellCount === 1 ? 'request' : 'requests' }}"
    aria-label="{{ $maintenanceBellCount }} open maintenance {{ $maintenanceBellCount === 1 ? 'request' : 'requests' }}"
>
    @if ($maintenanceBellVariant !== 'dot')
        <i class="fa-solid fa-bell text-[9px]" aria-hidden="true"></i>
    @endif
    <span data-maintenance-bell-count>{{ $maintenanceBellCount > 99 ? '99+' : $maintenanceBellCount }}</span>
</span>
