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

document.addEventListener(
    'submit',
    (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        let msg = form.getAttribute('data-swal-confirm');
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

        const title = form.getAttribute('data-swal-title') || 'Are you sure?';

        Swal.fire({
            icon: 'warning',
            title,
            text: msg,
            showCancelButton: true,
            confirmButtonColor: '#2f4f4f',
            cancelButtonColor: '#64748b',
            confirmButtonText: form.getAttribute('data-swal-confirm-text') || 'Yes, continue',
            cancelButtonText: form.getAttribute('data-swal-cancel-text') || 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                form.removeAttribute('data-swal-confirm');
                form.removeAttribute('data-swal-title');
                form.removeAttribute('data-swal-confirm-text');
                form.removeAttribute('data-swal-cancel-text');
                form.submit();
            }
        });
    },
    true
);
