<div class="animate-pulse space-y-4" aria-busy="true" aria-live="polite">
    <p class="text-sm font-medium text-slate-600">{{ __('Loading dashboard metrics…') }}</p>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        @for ($i = 0; $i < 8; $i++)
            <div class="h-20 rounded-xl bg-slate-200/80"></div>
        @endfor
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="h-56 rounded-xl bg-slate-200/80"></div>
        <div class="h-56 rounded-xl bg-slate-200/80"></div>
    </div>
</div>
