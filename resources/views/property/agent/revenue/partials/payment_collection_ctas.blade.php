@php
    $invoicePanelId = $invoicePanelId ?? 'invoice-payment-panel';
    $advancePanelId = $advancePanelId ?? 'advance-payment-panel';
@endphp

<div class="mt-4 flex flex-wrap gap-2 border-t border-blue-100 pt-4">
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
        data-open-payment-panel="{{ $invoicePanelId }}"
    >
        <i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>
        Record payment (invoice)
    </button>
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
        data-open-payment-panel="{{ $advancePanelId }}"
    >
        <i class="fa-solid fa-piggy-bank" aria-hidden="true"></i>
        Record advance (prepay)
    </button>
</div>

<script>
    (function () {
        const bindPaymentPanelControls = () => {
            document.querySelectorAll('[data-open-payment-panel]').forEach((btn) => {
                if (btn.dataset.paymentPanelBound) return;
                btn.dataset.paymentPanelBound = '1';
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-open-payment-panel');
                    if (!id) return;
                    window.dispatchEvent(new CustomEvent('property-payment-panel-open', { detail: { panel: id } }));
                });
            });

            document.querySelectorAll('[data-collapse-payment-panel]').forEach((btn) => {
                if (btn.dataset.paymentPanelCollapseBound) return;
                btn.dataset.paymentPanelCollapseBound = '1';
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-collapse-payment-panel');
                    if (!id) return;
                    window.dispatchEvent(new CustomEvent('property-payment-panel-close', { detail: { panel: id } }));
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindPaymentPanelControls);
        } else {
            bindPaymentPanelControls();
        }

        document.addEventListener('turbo:load', bindPaymentPanelControls);
        document.addEventListener('turbo:frame-load', bindPaymentPanelControls);
    })();
</script>
