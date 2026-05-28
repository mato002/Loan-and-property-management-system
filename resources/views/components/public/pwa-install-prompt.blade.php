@props([
    'context' => 'public',
    'position' => 'right',
])

@php
    $horizontalClass = $position === 'left'
        ? 'left-4 sm:left-6'
        : 'right-4 sm:right-6';
@endphp

{{-- Floating install control for mobile and desktop browsers. --}}
<div
    id="pwa-install-fab"
    class="hidden fixed bottom-6 {{ $horizontalClass }} z-[60] flex flex-col items-end gap-2 max-w-[min(100vw-2rem,20rem)]"
    aria-live="polite"
>
    <button
        type="button"
        id="pwa-install-dismiss"
        class="self-end text-xs font-semibold text-gray-500 hover:text-gray-800 bg-white/90 backdrop-blur px-2 py-1 rounded-md shadow-sm border border-gray-200"
        aria-label="{{ __('Dismiss install hint') }}"
    >
        {{ __('Not now') }}
    </button>
    <button
        type="button"
        id="pwa-install-btn"
        class="group flex items-center gap-3 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white pl-4 pr-5 py-3 shadow-lg shadow-emerald-900/25 ring-2 ring-emerald-500/30 transition-all hover:scale-[1.02] active:scale-[0.98]"
    >
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15">
            <svg id="pwa-install-icon-mobile" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <svg id="pwa-install-icon-desktop" class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </span>
        <span class="text-left">
            <span
                id="pwa-install-title"
                class="block text-sm font-bold leading-tight"
                data-desktop-title="{{ __('Install app') }}"
            >{{ __('Install app') }}</span>
            <span
                id="pwa-install-subtitle"
                class="block text-xs text-emerald-100 font-medium"
                data-desktop-subtitle="{{ __('Install on this computer') }}"
            >{{ __('Add to home screen') }}</span>
        </span>
    </button>
</div>

<div
    id="pwa-install-ios-panel"
    class="hidden fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-4 bg-gray-900/50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pwa-ios-title"
>
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h2 id="pwa-ios-title" class="text-lg font-bold text-gray-900 mb-2">{{ __('Install on iPhone / iPad') }}</h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 mb-6">
            <li>{{ __('Open this site in Safari') }}</li>
            <li>{{ __('Tap the Share button') }}</li>
            <li>{{ __('Choose “Add to Home Screen”') }}</li>
        </ol>
        <button
            type="button"
            id="pwa-install-ios-close"
            class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3"
        >
            {{ __('Got it') }}
        </button>
    </div>
</div>

<div
    id="pwa-install-desktop-panel"
    class="hidden fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-4 bg-gray-900/50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pwa-desktop-title"
>
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h2 id="pwa-desktop-title" class="text-lg font-bold text-gray-900 mb-2">{{ __('Install on desktop') }}</h2>
        <div class="space-y-4 text-sm text-gray-600 mb-6">
            <div>
                <p class="font-semibold text-gray-800">{{ __('Chrome or Edge') }}</p>
                <p class="mt-1">{{ __('Click the install icon in the address bar, or use the Install app button again if your browser shows a prompt.') }}</p>
            </div>
            <div>
                <p class="font-semibold text-gray-800">{{ __('Safari on Mac') }}</p>
                <p class="mt-1">{{ __('Choose File → Add to Dock, or open the Share menu and choose Add to Dock.') }}</p>
            </div>
        </div>
        <button
            type="button"
            id="pwa-install-desktop-close"
            class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3"
        >
            {{ __('Got it') }}
        </button>
    </div>
</div>
