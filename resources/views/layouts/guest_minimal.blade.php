<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $companyName = auth()->check()
                ? (\App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name'))
                : (\App\Support\Property\PropertyWorkspaceBranding::forGuestPage('company_name') ?: config('app.name'));
            $resolvedTitle = str_replace(config('app.name'), $companyName, $title);
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $resolvedTitle }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" />
        @vite(['resources/css/app.css', 'resources/js/guest-minimal.js'])
        <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="min-h-screen bg-gradient-to-br from-[#eef5f3] to-[#dbe8e4] text-slate-900 antialiased">
        <x-swal-flash />
        <div class="flex min-h-screen items-center justify-center px-4 py-10">
            <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg ring-1 ring-slate-200/80">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
