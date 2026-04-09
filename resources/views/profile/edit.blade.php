@if (auth()->check() && (auth()->user()->is_super_admin ?? false))
    @extends('layouts.superadmin')

    @section('content')
        @include('profile.partials.profile-content')
    @endsection
@else
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
@endif
