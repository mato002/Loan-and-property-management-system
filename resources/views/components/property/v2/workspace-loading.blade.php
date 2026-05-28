<div class="property-workspace-loading pointer-events-none" aria-hidden="true">
    <div class="animate-pulse space-y-4 p-1">
        <div class="h-7 w-48 max-w-[70%] rounded-lg bg-slate-200/90"></div>
        <div class="h-4 w-full max-w-xl rounded bg-slate-200/70"></div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @for ($i = 0; $i < 4; $i++)
                <div class="h-20 rounded-xl bg-slate-200/80"></div>
            @endfor
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white/70 p-4 space-y-3">
            @for ($i = 0; $i < 5; $i++)
                <div class="flex items-center gap-3">
                    <div class="h-4 w-4 rounded bg-slate-200/90 shrink-0"></div>
                    <div class="h-4 flex-1 rounded bg-slate-200/70"></div>
                    <div class="h-4 w-16 rounded bg-slate-200/60 shrink-0"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
