<div class="animate-pulse space-y-4 mt-4" aria-busy="true" aria-live="polite">
    <p class="text-sm font-medium text-slate-600">{{ __('Loading collections, charts, and activity…') }}</p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        @for ($i = 0; $i < 3; $i++)
            <div class="h-20 rounded-xl bg-slate-200/80"></div>
        @endfor
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="h-56 rounded-xl bg-slate-200/80"></div>
        <div class="h-56 rounded-xl bg-slate-200/80"></div>
    </div>
    <div class="h-40 rounded-xl bg-slate-200/80"></div>
</div>
