<div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-5 text-sm text-rose-900" role="alert">
    <p class="font-semibold">{{ __('Dashboard metrics could not load') }}</p>
    <p class="mt-1 text-rose-800">{{ $message ?? __('Something went wrong while loading collections, charts, and activity.') }}</p>
    <button
        type="button"
        class="mt-3 inline-flex items-center gap-2 rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-800"
        data-property-dashboard-metrics-retry
    >
        <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
        {{ __('Try again') }}
    </button>
</div>
