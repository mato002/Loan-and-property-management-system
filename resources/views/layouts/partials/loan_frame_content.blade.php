@php
    $shellRouteName = Route::currentRouteName();
@endphp

<turbo-frame id="loan-main" data-turbo-action="advance" data-turbo-cache="false">
    <div
        id="loan-main-route"
        data-route-name="{{ $shellRouteName ?? '' }}"
        data-page-title="{{ trim((string) ($title ?? ($header ?? ''))) }}"
        hidden
    ></div>
    <x-swal-flash />
    @include('layouts.partials.loan_workspace_shell')
    {{ $slot }}
</turbo-frame>
