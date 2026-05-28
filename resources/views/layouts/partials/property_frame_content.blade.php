@php
    use App\Support\Property\PropertyUiVersion;
    use App\Support\Property\PropertyWorkspaceTabs;

    $shellRouteName = Route::currentRouteName();
@endphp

<turbo-frame id="property-main" data-turbo-action="advance" data-turbo-cache="false">
    <div
        id="property-main-route"
        data-route-name="{{ $shellRouteName ?? '' }}"
        data-page-title="{{ trim((string) ($header ?? '')) }}"
        hidden
    ></div>
    <x-property.next-steps-modal />
    <x-swal-flash />
    @if (! PropertyUiVersion::isV2() && PropertyWorkspaceTabs::shouldShow($shellRouteName))
        <x-property.workspace-tabs :workspace="PropertyWorkspaceTabs::resolveWorkspaceKey($shellRouteName)" />
    @endif
    {{ $slot }}
</turbo-frame>
