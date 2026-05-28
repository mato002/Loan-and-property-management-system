<div
    x-show="penaltyModalOpen"
    x-cloak
    x-on:keydown.escape.window="closePenaltyModal()"
    class="utility-penalty-modal-backdrop"
    @click="closePenaltyModal()"
></div>

<div
    x-show="penaltyModalOpen"
    x-cloak
    class="utility-penalty-modal-panel"
    role="dialog"
    aria-modal="true"
    aria-label="Water penalty preview"
    @click.stop
>
    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-200 shrink-0">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Penalty preview</h2>
            <p class="text-xs text-slate-500">Dry run — no penalties applied until you confirm.</p>
        </div>
        <button type="button" @click="closePenaltyModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-4 py-3">
        <template x-if="penaltyLoading">
            <p class="text-sm text-slate-500 py-6 text-center">Loading preview…</p>
        </template>
        <template x-if="!penaltyLoading && penaltyError">
            <p class="text-sm text-rose-700 py-4" x-text="penaltyError"></p>
        </template>
        <template x-if="!penaltyLoading && !penaltyError && penaltyRows.length === 0">
            <p class="text-sm text-emerald-700 py-4">No water penalties would be applied today.</p>
        </template>
        <template x-if="!penaltyLoading && penaltyRows.length > 0">
            <div class="space-y-2">
                <template x-for="(row, idx) in penaltyRows" :key="idx">
                    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900" x-text="row.invoice_no"></span>
                            <span class="font-bold text-amber-900 tabular-nums" x-text="row.penalty_display || row.penalty"></span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1">
                            Base AR <span x-text="row.base_display || row.base"></span>
                            · Rule: <span x-text="row.rule"></span>
                        </p>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <div class="shrink-0 px-4 py-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-slate-800" x-show="penaltyRows.length > 0">
            Total: <span class="text-amber-800 tabular-nums" x-text="penaltyTotalDisplay || penaltyTotal"></span>
        </p>
        <div class="flex gap-2 ml-auto">
            <button type="button" @click="closePenaltyModal()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 min-h-[44px]">Close</button>
            <form method="post" action="{{ route('property.revenue.utilities.water_penalties.apply', absolute: false) }}">
                @csrf
                <button type="submit" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 min-h-[44px]" data-swal-confirm="Apply water penalties now?">Apply penalties</button>
            </form>
        </div>
    </div>
</div>
