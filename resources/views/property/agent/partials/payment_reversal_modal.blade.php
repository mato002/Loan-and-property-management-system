<div
    id="payment-reversal-root"
    x-data="paymentReversalModalState()"
    @payment-reversal-open.window="openFor($event.detail.form, $event.detail.mode)"
    @property-modal:purge-orphans.window="close()"
>
    <x-property.modal
        show="open"
        close="close()"
        name="payment-reversal"
        :title="null"
        max-width="lg"
    >
        <x-slot name="header">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white" x-text="title"></h2>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400" x-text="subtitle"></p>
            </div>
        </x-slot>

        <div>
            <label for="payment-reversal-modal-reason" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Reason</label>
            <textarea
                id="payment-reversal-modal-reason"
                x-ref="reasonInput"
                x-model="reason"
                rows="4"
                maxlength="500"
                class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-950 px-3 py-2 text-sm"
                placeholder="Enter reason..."
            ></textarea>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="hint"></p>
        </div>

        <x-slot name="footer">
            <div class="flex items-center justify-end gap-2">
                <button type="button" @click="close()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
                <button type="button" @click="submit()" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Continue</button>
            </div>
        </x-slot>
    </x-property.modal>
</div>
