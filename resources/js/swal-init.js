import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Swal = Swal;

function runFlash() {
    const queue = window.__laravelSwalFlash;
    if (!Array.isArray(queue) || queue.length === 0) {
        return;
    }
    delete window.__laravelSwalFlash;

    (async () => {
        for (const item of queue) {
            const opts = {
                icon: item.icon || 'info',
                confirmButtonText: item.confirmButtonText || 'OK',
                confirmButtonColor: '#2f4f4f',
            };
            if (item.title) {
                opts.title = item.title;
            }
            if (item.html) {
                opts.html = item.html;
            } else if (item.text) {
                opts.text = item.text;
            }
            await Swal.fire(opts);
        }
    })();
}

window.__runSwalFlash = runFlash;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runFlash);
} else {
    runFlash();
}

// Turbo (Hotwire) navigations do not trigger DOMContentLoaded, so also re-run
// flashes on Turbo events across the whole app (loan + public + auth pages too).
document.addEventListener('turbo:load', runFlash);
document.addEventListener('turbo:render', runFlash);
document.addEventListener('turbo:frame-load', runFlash);

document.addEventListener(
    'submit',
    (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        const action = form.getAttribute('action') || '';
        if (action.includes('/loan/book/applications/undefined')) {
            const currentPath = window.location.pathname.replace(/\/+$/, '');
            const repairedPath = currentPath.replace(/\/edit$/, '');
            const canRepair = /^\/loan\/book\/applications\/\d+$/.test(repairedPath);
            if (canRepair) {
                form.setAttribute('action', repairedPath);
            } else {
                e.preventDefault();
                e.stopPropagation();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid application target',
                    text: 'The form target was invalid (undefined). Please reload this page and try again.',
                    confirmButtonColor: '#2f4f4f',
                });
                return;
            }
        }
        const submitter = e.submitter instanceof HTMLElement ? e.submitter : null;
        let msg = submitter?.getAttribute('data-swal-confirm') || form.getAttribute('data-swal-confirm');
        if (!msg) {
            const onsubmitRaw = form.getAttribute('onsubmit') || '';
            const match = onsubmitRaw.match(/confirm\((['"])(.*?)\1\)/);
            if (match && match[2]) {
                msg = match[2];
            }
        }
        if (!msg) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        const title = submitter?.getAttribute('data-swal-title') || form.getAttribute('data-swal-title') || 'Are you sure?';

        Swal.fire({
            icon: 'warning',
            title,
            text: msg,
            showCancelButton: true,
            confirmButtonColor: '#2f4f4f',
            cancelButtonColor: '#64748b',
            confirmButtonText: submitter?.getAttribute('data-swal-confirm-text') || form.getAttribute('data-swal-confirm-text') || 'Yes, continue',
            cancelButtonText: submitter?.getAttribute('data-swal-cancel-text') || form.getAttribute('data-swal-cancel-text') || 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                form.removeAttribute('data-swal-confirm');
                form.removeAttribute('data-swal-title');
                form.removeAttribute('data-swal-confirm-text');
                form.removeAttribute('data-swal-cancel-text');
                submitter?.removeAttribute('data-swal-confirm');
                submitter?.removeAttribute('data-swal-title');
                submitter?.removeAttribute('data-swal-confirm-text');
                submitter?.removeAttribute('data-swal-cancel-text');

                if (submitter && typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter);
                    return;
                }

                form.submit();
            }
        });
    },
    true
);
