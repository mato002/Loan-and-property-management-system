@php
    use App\Support\Property\PropertyNavigation;

    $drawerNav = PropertyNavigation::mobileDrawerNav();
    $currentRoute = Route::currentRouteName() ?? '';
@endphp

<div
    id="property-mobile-more-drawer"
    class="property-mobile-more-drawer lg:hidden"
    data-property-mobile-more-drawer
    hidden
    role="dialog"
    aria-modal="true"
    aria-label="More workspaces"
>
    <div class="property-mobile-more-drawer__backdrop" data-property-mobile-more-close aria-hidden="true"></div>
    <div class="property-mobile-more-drawer__panel">
        <div class="property-mobile-more-drawer__header">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">More</p>
                <h2 class="text-base font-semibold text-slate-900">Workspaces & tools</h2>
            </div>
            <button
                type="button"
                class="property-mobile-more-drawer__close"
                data-property-mobile-more-close
                aria-label="Close menu"
            >
                <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
            </button>
        </div>

        <div class="property-mobile-more-drawer__body custom-scrollbar">
            <div class="grid grid-cols-2 gap-2">
                @foreach ($drawerNav as $item)
                    @php
                        $active = PropertyNavigation::routeIsActive($currentRoute, $item['patterns']);
                        $tone = (string) ($item['tone'] ?? 'default');
                        $toneClass = $tone === 'violet'
                            ? 'border-violet-200 bg-violet-50 text-violet-900'
                            : 'border-slate-200 bg-white text-slate-800';
                    @endphp
                    <a
                        href="{{ route($item['route'], [], false) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ implode('|', $item['patterns']) }}"
                        data-property-mobile-more-link
                        @if ($active) aria-current="page" @endif
                        class="property-mobile-more-drawer__tile {{ $toneClass }} {{ $active ? 'is-active' : '' }}"
                    >
                        <i class="fa-solid {{ $item['icon'] }} text-lg" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>

            <div class="mt-4 border-t border-slate-200 pt-4 space-y-2">
                <a
                    href="{{ route('property.notifications', [], false) }}"
                    data-turbo-frame="property-main"
                    data-property-mobile-more-link
                    class="property-mobile-more-drawer__row"
                >
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    <span>Notifications</span>
                </a>
                <a
                    href="{{ route('profile.edit', [], false) }}"
                    data-turbo-frame="property-main"
                    data-property-mobile-more-link
                    class="property-mobile-more-drawer__row"
                >
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                    <span>Profile settings</span>
                </a>
            </div>
        </div>
    </div>
</div>
