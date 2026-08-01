<div
    id="property-form-modal-host"
    data-turbo-permanent
    x-data="propertyFormModalHost"
    x-cloak
>
    <x-property.modal
        show="open"
        close="handleClose()"
        name="property-form-modal"
        :title="null"
        max-width="4xl"
        :teleport="false"
        :z-index="7120"
    >
        <x-slot name="header">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white" x-text="title"></h2>
        </x-slot>

        <div class="space-y-3">
            <p
                x-show="loading"
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"
            >
                Loading form…
            </p>
            <p
                x-show="error"
                x-text="error"
                class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
            ></p>
            <div x-ref="frameHost" class="property-form-modal-frame-host min-h-[2rem]"></div>
        </div>

        <x-slot name="footer">
            <button
                type="button"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200"
                @click="handleClose()"
            >
                Close
            </button>
        </x-slot>
    </x-property.modal>
</div>
