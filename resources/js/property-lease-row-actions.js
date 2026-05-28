function confirmLeaseRowAction(message) {
    return new Promise((resolve) => {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: 'warning',
                title: 'Please confirm',
                text: message,
                showCancelButton: true,
                confirmButtonText: 'Yes, continue',
                cancelButtonText: 'Cancel',
            }).then((result) => resolve(!!result.isConfirmed));

            return;
        }

        resolve(window.confirm(message));
    });
}

function setLeaseRowActionMethod(form, method) {
    const normalized = (method || 'POST').toUpperCase();
    let methodInput = form.querySelector('input[name="_method"]');

    if (normalized === 'DELETE') {
        if (!methodInput) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            form.appendChild(methodInput);
        }
        methodInput.value = 'DELETE';

        return;
    }

    methodInput?.remove();
}

export function setupPropertyLeaseRowActions(scopeRoot) {
    const root = scopeRoot instanceof Element ? scopeRoot : document;

    root.querySelectorAll('[data-property-lease-row-action]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        if (button.dataset.propertyLeaseRowActionBound === '1') {
            return;
        }

        button.dataset.propertyLeaseRowActionBound = '1';

        button.addEventListener('click', async () => {
            const form = document.getElementById('property-lease-row-action-form');
            const actionUrl = button.dataset.actionUrl || '';
            if (!form || actionUrl === '') {
                return;
            }

            const confirmMessage = button.dataset.swalConfirm || '';
            if (confirmMessage !== '' && !(await confirmLeaseRowAction(confirmMessage))) {
                return;
            }

            if (typeof window.closeAllPropertyDropdowns === 'function') {
                window.closeAllPropertyDropdowns();
            }

            form.action = actionUrl;
            setLeaseRowActionMethod(form, button.dataset.actionMethod || 'POST');

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });
}

window.setupPropertyLeaseRowActions = setupPropertyLeaseRowActions;
