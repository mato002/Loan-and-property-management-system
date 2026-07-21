/**
 * Payment reversal request/approve — reason prompt + form intercept.
 * Uses SweetAlert2 (not x-teleport modals) so clicks are never blocked by portal overlays.
 */

import { closeAllPropertyDropdowns } from './property-dropdown-cleanup';

function closeOpenRowActionMenus(scopeRoot) {
    const root = scopeRoot instanceof Element ? scopeRoot : document;

    root.querySelectorAll('details[open]').forEach((details) => {
        if (details instanceof HTMLDetailsElement && details.closest('[data-row-ignore-click]')) {
            details.open = false;
        }
    });
}

function promptPaymentReversalReason(form, mode) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    closeAllPropertyDropdowns();
    closeOpenRowActionMenus(form.closest('#property-main') ?? document);

    const ref = form.getAttribute('data-payment-ref') || 'payment';
    const isRequest = mode === 'request';

    if (!window.Swal?.fire) {
        const fallback = window.prompt(
            isRequest
                ? `Enter a reason to request reversal for ${ref} (min 5 characters):`
                : `Optional checker note for approving reversal of ${ref}:`,
            '',
        );
        if (fallback === null) {
            return;
        }
        const trimmed = fallback.trim();
        if (isRequest && trimmed.length < 5) {
            window.alert('Please enter a clearer reason (at least 5 characters).');
            return;
        }
        const input = form.querySelector('input[name="reason"]');
        if (input instanceof HTMLInputElement) {
            input.value = trimmed;
        }
        form.dataset.reversalConfirmed = '1';
        form.requestSubmit?.() ?? form.submit();
        return;
    }

    window.Swal.fire({
        icon: 'warning',
        title: isRequest ? `Request reversal for ${ref}` : `Approve reversal for ${ref}`,
        text: isRequest
            ? 'A clear reason is required for maker submission.'
            : 'Checker note is optional but recommended.',
        input: 'textarea',
        inputPlaceholder: 'Enter reason...',
        inputAttributes: {
            maxlength: '500',
            'aria-label': 'Reversal reason',
        },
        showCancelButton: true,
        confirmButtonText: 'Continue',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        focusConfirm: false,
        inputValidator: (value) => {
            if (isRequest && (value || '').trim().length < 5) {
                return 'Please enter a clearer reason (at least 5 characters).';
            }

            return null;
        },
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const input = form.querySelector('input[name="reason"]');
        if (input instanceof HTMLInputElement) {
            input.value = (result.value || '').trim();
        }

        form.dataset.reversalConfirmed = '1';
        form.requestSubmit?.() ?? form.submit();
    });
}

function bindReversalForm(form, mode) {
    if (!(form instanceof HTMLFormElement) || form.dataset.reversalBound === '1') {
        return;
    }

    form.dataset.reversalBound = '1';

    const trigger = (event) => {
        if (form.dataset.reversalConfirmed === '1') {
            delete form.dataset.reversalConfirmed;
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        promptPaymentReversalReason(form, mode);
    };

    form.addEventListener('submit', trigger);

    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        button.type = 'button';
        button.addEventListener('click', trigger);
    });
}

export function setupPropertyPaymentReversal(scopeRoot) {
    const root = scopeRoot instanceof Element ? scopeRoot : document.getElementById('property-main') || document;

    root.querySelectorAll('.js-reversal-request-form').forEach((form) => {
        bindReversalForm(form, 'request');
    });

    root.querySelectorAll('.js-reversal-approve-form').forEach((form) => {
        bindReversalForm(form, 'approve');
    });
}

let globalListenersBound = false;

function bindGlobalPaymentReversalListeners() {
    if (globalListenersBound) {
        return;
    }
    globalListenersBound = true;

    document.addEventListener('DOMContentLoaded', () => setupPropertyPaymentReversal(document));
    document.addEventListener('turbo:load', () => setupPropertyPaymentReversal(document.getElementById('property-main')));
    document.addEventListener('turbo:frame-load', (event) => {
        if (event.target instanceof HTMLElement && event.target.id === 'property-main') {
            setupPropertyPaymentReversal(event.target);
        }
    });
}

bindGlobalPaymentReversalListeners();
