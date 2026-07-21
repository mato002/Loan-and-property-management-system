@props([
    'current' => 'property',
    'variant' => 'pill',
])

@php
    use App\Support\Auth\StaffModuleRedirect;

    $user = auth()->user();
    $target = ($user instanceof \App\Models\User)
        ? StaffModuleRedirect::switchTargetModule($user, (string) $current)
        : null;
@endphp

@if ($target)
    @if ($variant === 'menu-property')
        <a
            href="{{ StaffModuleRedirect::moduleSwitchUrl($target) }}"
            data-staff-module-switch="1"
            data-turbo="false"
            class="relative z-[80] flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 transition-colors"
        >
            <i class="fa-solid fa-shuffle w-4 text-center text-slate-400" aria-hidden="true"></i>
            Switch to {{ StaffModuleRedirect::moduleShortLabel($target) }}
        </a>
    @elseif ($variant === 'menu-loan')
        <a
            href="{{ StaffModuleRedirect::moduleSwitchUrl($target) }}"
            data-staff-module-switch="1"
            data-turbo="false"
            class="relative z-[80] block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 hover:text-indigo-600 transition-colors"
        >
            Switch to {{ StaffModuleRedirect::moduleShortLabel($target) }}
        </a>
    @elseif ($variant === 'pill-loan')
        <a
            href="{{ StaffModuleRedirect::moduleSwitchUrl($target) }}"
            data-staff-module-switch="1"
            data-turbo="false"
            class="relative z-[80] hidden sm:inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-800 hover:bg-indigo-100 transition-colors whitespace-nowrap"
            title="Open the {{ StaffModuleRedirect::moduleShortLabel($target) }} module"
        >
            <i class="fa-solid fa-shuffle text-[11px]" aria-hidden="true"></i>
            {{ StaffModuleRedirect::moduleShortLabel($target) }}
        </a>
    @else
        <a
            href="{{ StaffModuleRedirect::moduleSwitchUrl($target) }}"
            data-staff-module-switch="1"
            data-turbo="false"
            class="relative z-[80] hidden sm:inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-3 py-1.5 text-xs font-bold text-white hover:bg-white/20 transition-colors whitespace-nowrap"
            title="Open the {{ StaffModuleRedirect::moduleShortLabel($target) }} module"
        >
            <i class="fa-solid fa-shuffle text-[11px]" aria-hidden="true"></i>
            {{ StaffModuleRedirect::moduleShortLabel($target) }}
        </a>
    @endif
@endif
