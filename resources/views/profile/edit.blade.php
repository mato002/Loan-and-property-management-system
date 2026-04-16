{{-- Always use the same shell as the rest of the app (loan / property) so chrome stays consistent.
     A separate Super Admin layout here caused the main header bar to render in the wrong place. --}}
@php
    $layoutName = session('active_system', 'loan') . '-layout';
@endphp
<x-dynamic-component :component="$layoutName">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    @include('profile.partials.profile-content')
</x-dynamic-component>
