import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Swal = Swal;

/** Above property modals (max 7110), below global search (99999). See MODAL_Z.alert in property-modal-manager.js */
const SWAL_Z_INDEX = 95000;

const SWAL_RECOVERY_TIMEOUT_MS = 3000;
const OVERLAY_DEBUG =
    import.meta.env.DEV ||
    window.__overlayDebug === true ||
    window.localStorage?.getItem('overlay_debug') === '1';

function debugLog(...args) {
    if (!OVERLAY_DEBUG) return;
    console.debug('[OverlaySafety]', ...args);
}

function isVisible(el) {
    if (!el) return false;
    const style = window.getComputedStyle(el);
    return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity || '1') > 0;
}

function restoreInteractionState() {
    document.body.classList.remove('swal2-shown', 'swal2-height-auto');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    document.documentElement.style.removeProperty('overflow');
    window.recoverPropertyScrollState?.('swal:cleanup');
}

function cleanupSwalBackdrop(reason = 'manual') {
    const popup = document.querySelector('.swal2-popup.swal2-show');
    if (popup && isVisible(popup)) {
        return;
    }
    document.querySelectorAll('.swal2-container').forEach((el) => el.remove());
    restoreInteractionState();
    debugLog('SweetAlert stale cleanup', reason);
}

function cleanupRecoverableOverlays(reason = 'manual') {
    cleanupSwalBackdrop(reason);

    const recoverable = document.querySelectorAll('[data-overlay-recoverable]');
    let removedCount = 0;
    recoverable.forEach((overlay) => {
        if (!isVisible(overlay)) return;
        const dialog = overlay.querySelector('[data-overlay-dialog], [role="dialog"], dialog');
        if (dialog && isVisible(dialog)) return;
        overlay.remove();
        removedCount += 1;
    });

    if (removedCount > 0) {
        restoreInteractionState();
        debugLog('Removed stale recoverable overlays', { reason, removedCount });
    }
}

function elevateSwalContainer(popup) {
    const container = popup?.closest?.('.swal2-container')
        ?? document.querySelector('.swal2-container.swal2-backdrop-show, .swal2-container.swal2-noanimation');
    if (container instanceof HTMLElement) {
        container.style.zIndex = String(SWAL_Z_INDEX);
    }
}

function safeSwalFire(opts, source = 'unknown') {
    debugLog('SweetAlert open', source);
    let settled = false;

    const mergedOpts = typeof opts === 'object' && opts !== null ? { ...opts } : opts;
    if (typeof mergedOpts === 'object' && mergedOpts !== null) {
        const userDidOpen = mergedOpts.didOpen;
        mergedOpts.didOpen = (popup) => {
            elevateSwalContainer(popup);
            if (typeof userDidOpen === 'function') {
                userDidOpen(popup);
            }
        };
    }

    const watchdog = window.setTimeout(() => {
        if (settled) return;
        cleanupSwalBackdrop(`watchdog:${source}`);
    }, SWAL_RECOVERY_TIMEOUT_MS);

    return Swal.fire(mergedOpts)
        .then((result) => {
            settled = true;
            return result;
        })
        .catch((error) => {
            settled = true;
            debugLog('SweetAlert error', { source, error });
            throw error;
        })
        .finally(() => {
            window.clearTimeout(watchdog);
            cleanupSwalBackdrop(`finally:${source}`);
            debugLog('SweetAlert close', source);
        });
}

/** Ensure direct Swal.fire() calls (lease validation, quick-create, etc.) stack above modals. */
const nativeSwalFire = Swal.fire.bind(Swal);
Swal.fire = function patchedSwalFire(...args) {
    if (args.length === 1 && typeof args[0] === 'object' && args[0] !== null) {
        const opts = { ...args[0] };
        const userDidOpen = opts.didOpen;
        opts.didOpen = (popup) => {
            elevateSwalContainer(popup);
            if (typeof userDidOpen === 'function') {
                userDidOpen(popup);
            }
        };
        return nativeSwalFire(opts);
    }

    return nativeSwalFire(...args);
};
window.Swal = Swal;

let flashDrainPromise = null;

function readFlashPayloadFromDom(scope) {
    const root = scope instanceof Element ? scope : document;
    const payloads = [];

    root.querySelectorAll('[data-swal-flash]').forEach((el) => {
        const raw = el.getAttribute('data-swal-flash');
        if (!raw) {
            return;
        }
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                payloads.push(...parsed);
            } else if (parsed && typeof parsed === 'object') {
                payloads.push(parsed);
            }
        } catch (error) {
            debugLog('SweetAlert flash parse error', error);
        }
        el.removeAttribute('data-swal-flash');
    });

    return payloads;
}

function runFlash(scope) {
    if (flashDrainPromise) {
        return flashDrainPromise;
    }

    const queue = [];

    if (Array.isArray(window.__laravelSwalFlash) && window.__laravelSwalFlash.length > 0) {
        queue.push(...window.__laravelSwalFlash);
        delete window.__laravelSwalFlash;
    }

    queue.push(...readFlashPayloadFromDom(scope));

    if (queue.length === 0) {
        return Promise.resolve();
    }

    flashDrainPromise = (async () => {
        for (const item of queue) {
            const opts = {
                icon: item.icon || 'info',
                confirmButtonText: item.confirmButtonText || 'OK',
                confirmButtonColor: item.confirmButtonColor || '#2f4f4f',
            };
            if (item.title) {
                opts.title = item.title;
            }
            if (item.html) {
                opts.html = item.html;
            } else if (item.text) {
                opts.text = item.text;
            }
            if (item.timer !== undefined) {
                opts.timer = item.timer;
            }
            if (item.showConfirmButton !== undefined) {
                opts.showConfirmButton = item.showConfirmButton;
            } else if (opts.icon === 'success' && !opts.html) {
                opts.timer = 2400;
                opts.showConfirmButton = false;
            }
            await safeSwalFire(opts, 'flash_queue');
        }
    })().finally(() => {
        flashDrainPromise = null;
    });

    return flashDrainPromise;
}

window.__runSwalFlash = runFlash;

/**
 * Turbo applies the new document before inline scripts in the body run. Read flash
 * payloads from data-swal-flash nodes (available immediately) and defer once so
 * frame swaps (e.g. lease save → show page) reliably show SweetAlert feedback.
 */
function scheduleRunFlashAfterTurboDom(scope) {
    queueMicrotask(() => {
        window.setTimeout(() => {
            runFlash(scope);
        }, 0);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        cleanupRecoverableOverlays('dom_ready');
        runFlash();
    });
} else {
    cleanupRecoverableOverlays('immediate');
    runFlash();
}

// Turbo (Hotwire) navigations do not trigger DOMContentLoaded, so also re-run
// flashes on Turbo events across the whole app (loan + public + auth pages too).
document.addEventListener('turbo:load', () => {
    cleanupRecoverableOverlays('turbo:load');
    scheduleRunFlashAfterTurboDom(document.getElementById('property-main') || document);
});
document.addEventListener('turbo:render', () => {
    cleanupRecoverableOverlays('turbo:render');
    scheduleRunFlashAfterTurboDom(document.getElementById('property-main') || document);
});
document.addEventListener('turbo:frame-load', (event) => {
    cleanupRecoverableOverlays('turbo:frame-load');
    const frame = event.target instanceof Element ? event.target : document.getElementById('property-main');
    scheduleRunFlashAfterTurboDom(frame || document);
});
document.addEventListener('turbo:submit-end', (event) => {
    if (!event.detail?.success) {
        return;
    }
    const frame = document.getElementById('property-main');
    scheduleRunFlashAfterTurboDom(frame || document);
});

document.addEventListener(
    'submit',
    (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (form.dataset.swalSubmitting === '1') {
            delete form.dataset.swalSubmitting;
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
                safeSwalFire({
                    icon: 'error',
                    title: 'Invalid application target',
                    text: 'The form target was invalid (undefined). Please reload this page and try again.',
                    confirmButtonColor: '#2f4f4f',
                }, 'invalid_form_target');
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

        safeSwalFire({
            icon: 'warning',
            title,
            text: msg,
            showCancelButton: true,
            confirmButtonColor: '#2f4f4f',
            cancelButtonColor: '#64748b',
            confirmButtonText: submitter?.getAttribute('data-swal-confirm-text') || form.getAttribute('data-swal-confirm-text') || 'Yes, continue',
            cancelButtonText: submitter?.getAttribute('data-swal-cancel-text') || form.getAttribute('data-swal-cancel-text') || 'Cancel',
        }, 'form_confirm').then((result) => {
            if (result.isConfirmed) {
                form.dataset.swalSubmitting = '1';
                // Prevent the native confirm() prompt from re-blocking submit on forms
                // that still use inline onsubmit="return confirm(...)"
                form.removeAttribute('onsubmit');
                form.removeAttribute('data-swal-confirm');
                form.removeAttribute('data-swal-title');
                form.removeAttribute('data-swal-confirm-text');
                form.removeAttribute('data-swal-cancel-text');
                submitter?.removeAttribute('data-swal-confirm');
                submitter?.removeAttribute('data-swal-title');
                submitter?.removeAttribute('data-swal-confirm-text');
                submitter?.removeAttribute('data-swal-cancel-text');

                if (submitter && typeof form.requestSubmit === 'function') {
                    try {
                        form.requestSubmit(submitter);
                        return;
                    } catch (err) {
                        // Fallback for edge-cases where submitter-bound requestSubmit fails.
                    }
                }

                form.submit();
            }
        });
    },
    true
);
