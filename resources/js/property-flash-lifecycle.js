/**
 * Phase 2D — single flash owner for the property portal (#property-main).
 * Coalesces __runSwalFlash after confirmed frame swaps; avoids duplicate scheduling.
 */

const PROPERTY_MAIN_FRAME_ID = 'property-main';
const FLASH_COALESCE_MS = 16;

let flashCoalesceTimer = null;
let lastPropertyFlashKey = null;

function isPropertyPortalShell() {
    return Boolean(
        document.getElementById(PROPERTY_MAIN_FRAME_ID) || document.body?.dataset?.propertyNavMode,
    );
}

function resolveFlashScope(scope) {
    if (scope instanceof HTMLElement && scope.id === PROPERTY_MAIN_FRAME_ID) {
        return scope;
    }
    if (scope instanceof Element) {
        const nested = scope.querySelector(`#${PROPERTY_MAIN_FRAME_ID}`);
        if (nested instanceof HTMLElement) {
            return nested;
        }
    }

    const frame = document.getElementById(PROPERTY_MAIN_FRAME_ID);

    return frame instanceof HTMLElement ? frame : document;
}

function propertyFlashDedupeKey(scope) {
    const frame = resolveFlashScope(scope);
    const route = frame.querySelector('#property-main-route')?.dataset?.routeName ?? '';
    const gen = frame.getAttribute('data-property-hydration-gen') ?? '';

    return `${window.location.pathname}${window.location.search}|${route}|${gen}`;
}

/**
 * Schedule flash drain once per frame generation / navigation (property portal only).
 *
 * @param {Document|Element} scope
 * @param {string} source
 * @param {{ force?: boolean }} [options]
 */
export function schedulePropertyFlash(scope, source = 'unknown', options = {}) {
    if (!isPropertyPortalShell() || typeof window.__runSwalFlash !== 'function') {
        return;
    }

    const { force = false } = options;
    const scopeEl = resolveFlashScope(scope);

    window.clearTimeout(flashCoalesceTimer);
    flashCoalesceTimer = window.setTimeout(() => {
        flashCoalesceTimer = null;

        if (window.__propertyWorkspaceHydrating === true) {
            schedulePropertyFlash(scopeEl, source, options);

            return;
        }

        const key = propertyFlashDedupeKey(scopeEl);
        if (!force && key === lastPropertyFlashKey) {
            return;
        }

        lastPropertyFlashKey = key;
        window.__runSwalFlash(scopeEl);
    }, FLASH_COALESCE_MS);
}

export function resetPropertyFlashDedupe(reason = 'manual') {
    lastPropertyFlashKey = null;
    window.clearTimeout(flashCoalesceTimer);
    flashCoalesceTimer = null;
    if (window.__PROPERTY_DEBUG_NAV === true) {
        console.debug('[PropertyFlash]', { action: 'reset-dedupe', reason });
    }
}

function bindPropertyFlashLifecycle() {
    const scheduleFromMainFrame = (source, options = {}) => {
        const frame = document.getElementById(PROPERTY_MAIN_FRAME_ID);
        if (frame) {
            schedulePropertyFlash(frame, source, options);
        }
    };

    document.addEventListener('DOMContentLoaded', () => scheduleFromMainFrame('dom:ready', { force: true }));
    document.addEventListener('turbo:load', () => scheduleFromMainFrame('turbo:load'));

    document.addEventListener('turbo:frame-load', (event) => {
        if (!(event.target instanceof HTMLElement) || event.target.id !== PROPERTY_MAIN_FRAME_ID) {
            return;
        }

        const frame = event.target;
        const hasFlash = Boolean(frame.querySelector('[data-swal-flash]'));
        schedulePropertyFlash(frame, 'turbo:frame-load', { force: hasFlash });
    });

    document.addEventListener('turbo:submit-start', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.id !== 'lease-form-wrapper') {
            return;
        }
        if (!form.closest(`#${PROPERTY_MAIN_FRAME_ID}`)) {
            return;
        }
        window.clearLeaseCreateFormPersistedOpen?.();
    });

    document.addEventListener('turbo:submit-end', (event) => {
        if (!event.detail?.success) {
            return;
        }

        const form = event.detail?.formSubmission?.formElement;
        if (!(form instanceof HTMLFormElement) || !form.closest(`#${PROPERTY_MAIN_FRAME_ID}`)) {
            return;
        }

        resetPropertyFlashDedupe('turbo:submit-end');

        // Frame HTML is updated before submit-end; drain flash from the new #property-main payload.
        requestAnimationFrame(() => {
            const frame = document.getElementById(PROPERTY_MAIN_FRAME_ID);
            if (!frame) {
                return;
            }

            if (form.id === 'lease-form-wrapper') {
                window.clearLeaseCreateFormPersistedOpen?.();
                if (frame.querySelector('[data-swal-flash]')) {
                    schedulePropertyFlash(frame, 'turbo:submit-end', { force: true });
                }
                window.setTimeout(() => {
                    if (document.querySelector('.swal2-popup.swal2-show')) {
                        return;
                    }
                    if (typeof window.Swal?.fire === 'function') {
                        window.Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'Lease saved.',
                            timer: 2400,
                            showConfirmButton: false,
                        });
                    }
                }, 120);
                return;
            }

            const hasFlash = Boolean(frame.querySelector('[data-swal-flash]'));
            if (hasFlash) {
                schedulePropertyFlash(frame, 'turbo:submit-end', { force: true });
            }
        });
    });

    window.addEventListener('popstate', () => resetPropertyFlashDedupe('popstate'));
}

bindPropertyFlashLifecycle();
