<div class="rounded-xl sm:rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-950/30 dark:to-slate-900/40 p-3 md:p-4 shadow-sm">
    <p class="text-sm sm:text-base font-semibold text-slate-900 dark:text-slate-100">Rent flow (Step 1 of 3): Allocate a unit</p>
    <p class="mt-1 text-xs sm:text-sm text-slate-600 dark:text-slate-400">Create an <span class="font-semibold">Active</span> lease and select vacant unit(s). The unit becomes <span class="font-semibold">Occupied</span> automatically.</p>
    <div class="mt-2 flex flex-wrap gap-2">
        <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200">
            <span class="text-slate-500">Next:</span> Create rent bill
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
        <a href="{{ route('property.revenue.payments', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200">
            <span class="text-slate-500">Then:</span> Collect payment
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</div>
