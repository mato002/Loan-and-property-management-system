{{-- Global host for Photos & listing details (opened via [data-listing-publish]). --}}
<div
    id="listing-publish-modal"
    class="fixed inset-0 z-[7110] hidden items-center justify-center p-4 max-md:items-end max-md:p-0"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-label="Photos and listing details"
    data-listings-create-url="{{ route('property.listings.create', absolute: false) }}"
>
    <div
        class="absolute inset-0 z-0 bg-slate-950/50 backdrop-blur-[1px]"
        data-listing-publish-close
        aria-hidden="true"
    ></div>
    <div
        class="relative z-10 flex w-full max-w-5xl max-h-[90vh] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-gray-900 max-md:max-h-[92vh] max-md:rounded-b-none max-md:rounded-t-2xl"
        data-listing-publish-panel
    >
        <div
            id="listing-publish-slot"
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-5"
        ></div>
    </div>
</div>
