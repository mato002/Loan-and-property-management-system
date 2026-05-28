<div class="property-frame-skeleton animate-pulse pointer-events-none" data-property-frame-skeleton aria-hidden="true">
    <div class="property-erp-panel space-y-4 p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2 flex-1">
                <div class="property-skeleton-line h-7 w-48 max-w-[70%]"></div>
                <div class="property-skeleton-line h-4 w-full max-w-xl"></div>
            </div>
            <div class="hidden sm:flex gap-2">
                <div class="property-skeleton-line h-10 w-24 rounded-lg"></div>
                <div class="property-skeleton-line h-10 w-28 rounded-lg"></div>
            </div>
        </div>
        <div class="grid gap-3 grid-cols-2 lg:grid-cols-4">
            @for ($i = 0; $i < 4; $i++)
                <div class="property-skeleton-block h-20 rounded-xl"></div>
            @endfor
        </div>
        <div class="property-skeleton-block rounded-2xl border border-slate-200/70 p-4 space-y-3">
            <div class="property-skeleton-line h-4 w-32"></div>
            @for ($i = 0; $i < 6; $i++)
                <div class="flex items-center gap-3">
                    <div class="property-skeleton-block h-4 w-4 rounded shrink-0"></div>
                    <div class="property-skeleton-line h-4 flex-1"></div>
                    <div class="property-skeleton-line h-4 w-16 shrink-0"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
