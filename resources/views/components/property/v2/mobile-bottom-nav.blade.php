@php
    use App\Support\Property\PropertyNavigation;

    $primaryNav = PropertyNavigation::mobilePrimaryNav();
    $currentRoute = Route::currentRouteName() ?? '';
    $moreActive = PropertyNavigation::mobileMoreNavActive($currentRoute);
@endphp

<nav
    class="property-mobile-bottom-nav lg:hidden"
    aria-label="Primary mobile navigation"
    data-property-mobile-bottom-nav
>
    @foreach ($primaryNav as $item)
        @php $active = PropertyNavigation::routeIsActive($currentRoute, $item['patterns']); @endphp
        <a
            href="{{ PropertyNavigation::workspaceHref($item) }}"
            data-turbo-frame="property-main"
            data-property-nav="{{ implode('|', $item['patterns']) }}"
            @if ($active) aria-current="page" @endif
            class="property-mobile-bottom-nav__item {{ $active ? 'is-active' : '' }}"
            title="{{ $item['longLabel'] }}"
        >
            <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach

    <button
        type="button"
        class="property-mobile-bottom-nav__item property-mobile-bottom-nav__more {{ $moreActive ? 'is-active' : '' }}"
        data-property-mobile-more-open
        aria-haspopup="dialog"
        aria-controls="property-mobile-more-drawer"
        aria-expanded="false"
        title="More workspaces"
    >
        <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
        <span>More</span>
    </button>
</nav>
