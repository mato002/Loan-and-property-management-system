{{-- Always use the same shell as the rest of the app (loan / property) so chrome stays consistent.
     A separate Super Admin layout here caused the main header bar to render in the wrong place. --}}
@php
    $layoutName = session('active_system', 'loan') . '-layout';
@endphp
<x-dynamic-component :component="$layoutName">
    <x-slot name="header">
        <div>
            <h2 class="text-lg font-bold tracking-tight text-[#0f2744]">{{ __('Profile') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">Account settings, security, and access summary.</p>
        </div>
    </x-slot>

    @include('profile.partials.profile-content')
</x-dynamic-component>
