@php
    /** @var array<string, mixed> $sidebar */
    $isErp = (bool) ($sidebar['isErp'] ?? false);
    $asideZ = $isErp ? 'z-[5600] lg:z-50' : 'z-50';
    $asideWidth = $isErp
        ? 'w-[min(100vw-2.5rem,300px)] sm:w-[312px] overflow-x-hidden'
        : 'w-[300px] sm:w-[312px] overflow-hidden';
    $asideCollapseClass = $isErp
        ? "[sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none', sidebarDesktopOpen ? 'lg:w-[19rem] lg:min-w-[19rem] lg:max-w-[19rem]' : 'lg:w-[5.5rem] lg:min-w-[5.5rem] lg:max-w-[5.5rem]']"
        : "[sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none']";
    $asideCollapseStyle = $isErp
        ? "window.matchMedia('(min-width: 1024px)').matches ? (sidebarDesktopOpen ? 'width: 19rem; min-width: 19rem; max-width: 19rem;' : 'width: 5.5rem; min-width: 5.5rem; max-width: 5.5rem;') : ''"
        : "''";
    $dataCollapsed = $isErp ? ":data-collapsed=\"sidebarDesktopOpen ? '0' : '1'\"" : '';
@endphp

<aside
    class="property-sidebar property-sidebar--{{ $sidebar['mode'] ?? 'classic' }} fixed inset-y-0 left-0 {{ $asideZ }} {{ $asideWidth }} h-full bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-[transform,width] duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col min-h-0 shadow-xl shadow-black/20 lg:shadow-none flex-shrink-0"
    @if ($isErp)
        :class="{{ $asideCollapseClass }}"
        :style="{{ $asideCollapseStyle }}"
        {!! $dataCollapsed !!}
    @else
        :class="{{ $asideCollapseClass }}"
    @endif
    data-property-sidebar-mode="{{ $sidebar['mode'] ?? 'classic' }}"
>
