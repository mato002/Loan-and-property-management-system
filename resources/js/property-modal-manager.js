/**
 * Property portal — unified modal stack, scroll lock, Turbo lifecycle, and debug utilities.
 *
 * Enable debug: localStorage.setItem('overlay_debug', '1') then reload.
 */

import { PropertyFormModal as PropertyFormModalConfig } from './property-form-modal-config';

const MODAL_DEBUG =
    import.meta.env.DEV ||
    window.__overlayDebug === true ||
    window.localStorage?.getItem('overlay_debug') === '1';

/** Z-index scale — above mobile sidebar (5600), below global search (99999). */
export const MODAL_Z = {
    backdrop: 7000,
    modal: 7010,
    nested: 7110,
    drawer: 6500,
    dropdown: 7200,
    /** SweetAlert2 toasts/dialogs — must sit above all property modals */
    alert: 95000,
};

let modalStack = [];
let scrollLockDepth = 0;
let savedScrollY = 0;
let scrollLockRoot = null;
let turboListenersBound = false;

function getPropertyScrollRoot() {
    const main = document.getElementById('property-workspace-main');
    return main instanceof HTMLElement ? main : null;
}

function usesPropertyWorkspaceShell() {
    return Boolean(
        document.body?.dataset?.propertyNavMode
        || document.querySelector('.property-print-root'),
    );
}

function debugLog(...args) {
    if (!MODAL_DEBUG) {
        return;
    }
    console.debug('[PropertyModal]', ...args);
}

function isModalElement(node) {
    if (!(node instanceof Element)) {
        return false;
    }

    return Boolean(
        node.closest('[data-property-modal]') ||
        node.closest('[data-property-modal-panel]') ||
        node.closest('.swal2-container'),
    );
}

function applyScrollLock() {
    if (scrollLockDepth !== 1) {
        return;
    }

    const workspaceMain = getPropertyScrollRoot();
    if (workspaceMain) {
        scrollLockRoot = workspaceMain;
        savedScrollY = workspaceMain.scrollTop;
        workspaceMain.classList.add('property-modal-scroll-lock');
        return;
    }

    if (usesPropertyWorkspaceShell()) {
        return;
    }

    scrollLockRoot = document.body;
    savedScrollY = window.scrollY;
    document.documentElement.classList.add('property-modal-scroll-lock');
    document.body.classList.add('property-modal-scroll-lock');
    document.body.style.setProperty('top', `-${savedScrollY}px`);
}

function releaseScrollLock() {
    const workspaceMain = getPropertyScrollRoot();
    if (workspaceMain?.classList.contains('property-modal-scroll-lock')) {
        workspaceMain.classList.remove('property-modal-scroll-lock');
        workspaceMain.scrollTop = savedScrollY;
    }

    document.documentElement.classList.remove('property-modal-scroll-lock');
    document.body.classList.remove('property-modal-scroll-lock');
    document.body.style.removeProperty('top');

    if (scrollLockRoot === document.body) {
        window.scrollTo(0, savedScrollY);
    }

    scrollLockRoot = null;
}

/** Clear stale scroll locks after Turbo navigations, PWA resume, or overlay glitches. */
export function recoverPropertyScrollState(reason = 'manual') {
    if (modalStack.length > 0) {
        return false;
    }

    scrollLockDepth = 0;
    const hadLock =
        document.body.classList.contains('property-modal-scroll-lock') ||
        document.documentElement.classList.contains('property-modal-scroll-lock') ||
        getPropertyScrollRoot()?.classList.contains('property-modal-scroll-lock') ||
        document.body.style.top !== '';

    if (hadLock) {
        releaseScrollLock();
        debugLog('scroll recovery', { reason });
    }

    if (usesPropertyWorkspaceShell() && hadLock) {
        document.body.classList.remove('property-modal-scroll-lock');
        document.body.style.removeProperty('top');
        document.body.style.removeProperty('position');
        document.body.style.removeProperty('width');
        document.body.style.removeProperty('padding-right');
        document.documentElement.classList.remove('property-modal-scroll-lock');
    }

    document.documentElement.classList.remove('overflow-hidden');

    return hadLock;
}

export function lockPropertyModalScroll() {
    scrollLockDepth += 1;
    applyScrollLock();
    debugLog('scroll lock', { depth: scrollLockDepth });
}

export function unlockPropertyModalScroll() {
    scrollLockDepth = Math.max(0, scrollLockDepth - 1);

    if (scrollLockDepth === 0) {
        releaseScrollLock();
    }

    debugLog('scroll unlock', { depth: scrollLockDepth });
}

export function registerPropertyModal(entry) {
    const existing = modalStack.findIndex((m) => m.id === entry.id);
    if (existing >= 0) {
        modalStack[existing] = { ...modalStack[existing], ...entry };
        return;
    }

    modalStack.push(entry);
    lockPropertyModalScroll();
    debugLog('open', { id: entry.id, stack: modalStack.map((m) => m.id) });

    window.dispatchEvent(new CustomEvent('property-modal:open', { detail: { id: entry.id } }));
}

export function unregisterPropertyModal(id) {
    const idx = modalStack.findIndex((m) => m.id === id);
    if (idx < 0) {
        return;
    }

    modalStack.splice(idx, 1);
    unlockPropertyModalScroll();
    debugLog('close', { id, stack: modalStack.map((m) => m.id) });

    window.dispatchEvent(new CustomEvent('property-modal:close', { detail: { id } }));
}

export function getPropertyModalStack() {
    return [...modalStack];
}

export function isTopPropertyModal(id) {
    if (!modalStack.length) {
        return false;
    }

    return modalStack[modalStack.length - 1].id === id;
}

export function closeTopPropertyModal() {
    const top = modalStack[modalStack.length - 1];
    if (!top) {
        return;
    }

    if (typeof top.onClose === 'function') {
        top.onClose();
        return;
    }

    if (top.element instanceof HTMLElement) {
        top.element.dispatchEvent(new CustomEvent('property-modal:request-close', { bubbles: true }));
    }
}

export function closeAllPropertyModals(reason = 'manual') {
    const ids = modalStack.map((m) => m.id);
    debugLog('close all', { reason, ids });

    [...modalStack].reverse().forEach((entry) => {
        if (typeof entry.onClose === 'function') {
            entry.onClose();
        } else if (entry.element instanceof HTMLElement) {
            entry.element.dispatchEvent(new CustomEvent('property-modal:request-close', { bubbles: true }));
        }
    });

    modalStack = [];
    scrollLockDepth = 0;
    releaseScrollLock();
}

/**
 * After #property-main frame swap — clear stack without Alpine x-show teardown (avoids flicker).
 */
export function resetPropertyModalStackSilently(reason = 'manual') {
    if (modalStack.length === 0) {
        return;
    }

    debugLog('silent stack reset', { reason, ids: modalStack.map((m) => m.id) });
    modalStack = [];
    scrollLockDepth = 0;
    releaseScrollLock();
}

/**
 * Alpine x-teleport leaves modals on document.body when #property-main swaps.
 * Remove stale dialogs so they do not reappear (e.g. payment reversal "Reason" on Payments load).
 */
function isLeaseCreateFormOpen() {
    const root = document.querySelector('#property-main [data-lease-form-root]');
    if (root instanceof HTMLElement && root.dataset.leaseFormOpen === '1') {
        return true;
    }

    try {
        return sessionStorage.getItem('property.leases.createFormOpen') === '1';
    } catch {
        return false;
    }
}

function isPersistentPropertyFormModalShell(modal) {
    if (!(modal instanceof HTMLElement)) {
        return false;
    }

    if (modal.getAttribute('data-property-modal-id') === PropertyFormModalConfig.HOST_MODAL_ID) {
        return true;
    }

    return Boolean(modal.closest('#property-form-modal-host'));
}

export function purgeOrphanedTeleportedPropertyModals(reason = 'manual') {
    const frame = document.getElementById('property-main');
    let removed = 0;

    document.querySelectorAll('[data-property-modal]').forEach((modal) => {
        if (!(modal instanceof HTMLElement)) {
            return;
        }
        if (isPersistentPropertyFormModalShell(modal)) {
            return;
        }
        if (modal.hasAttribute('data-lease-submodal')) {
            if (! isLeaseCreateFormOpen()) {
                modal.remove();
                removed += 1;
            }

            return;
        }
        if (frame?.contains(modal)) {
            return;
        }
        if (isLeaseCreateFormOpen() && frame?.querySelector('[data-lease-form-root]')) {
            return;
        }
        modal.remove();
        removed += 1;
    });

    if (removed > 0) {
        debugLog('purged teleported modals', { reason, removed });
        window.dispatchEvent(new CustomEvent('property-modal:purge-orphans'));
        recoverPropertyScrollState('modal:purge-orphans');
    }
}

/**
 * Alpine helper — sync open state with modal manager.
 * Usage: x-data="propertyModalState('my-modal-id', false)"
 * Pair with x-model-like: :open / @close from x-property.modal
 */
export function propertyModalState(modalId, initialOpen = false) {
    return {
        open: Boolean(initialOpen),
        modalId: modalId || `modal-${Math.random().toString(36).slice(2, 9)}`,

        init() {
            this.$watch('open', (value) => {
                if (value) {
                    registerPropertyModal({
                        id: this.modalId,
                        element: this.$el,
                        onClose: () => {
                            this.open = false;
                        },
                    });
                } else {
                    unregisterPropertyModal(this.modalId);
                }
            });

            this.$el.addEventListener('property-modal:request-close', () => {
                this.open = false;
            });

            if (this.open) {
                registerPropertyModal({
                    id: this.modalId,
                    element: this.$el,
                    onClose: () => {
                        this.open = false;
                    },
                });
            }
        },

        close() {
            this.open = false;
        },

        destroy() {
            unregisterPropertyModal(this.modalId);
        },
    };
}

export function inspectPropertyModals() {
    const modals = document.querySelectorAll('[data-property-modal]');
    const visible = [...modals].filter((el) => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden';
    });

    const report = {
        stack: getPropertyModalStack().map((m) => m.id),
        scrollLockDepth,
        bodyOverflow: document.body.style.overflow || '(class-based)',
        htmlOverflow: document.documentElement.classList.contains('property-modal-scroll-lock'),
        visibleModals: visible.map((el) => ({
            id: el.getAttribute('data-property-modal-id') || '(anonymous)',
            zIndex: window.getComputedStyle(el).zIndex,
        })),
    };

    console.table(report.visibleModals);
    console.info('[PropertyModal] inspect', report);

    return report;
}

function shouldDismissModalsForTurboEvent(event) {
    if (event.type === 'turbo:before-visit' || event.type === 'turbo:before-render') {
        return true;
    }

    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    // Only workspace navigation should close modals — not frames loading inside a modal
    // (e.g. lease-create-modal fetching its form partial).
    return target.id === 'property-main';
}

function bindTurboModalLifecycle() {
    if (turboListenersBound) {
        return;
    }
    turboListenersBound = true;

    const dismiss = (event) => {
        if (!shouldDismissModalsForTurboEvent(event)) {
            return;
        }
        debugLog('turbo dismiss', event.type);
        closeAllPropertyModals(event.type);
    };

    document.addEventListener('turbo:before-visit', dismiss);
    document.addEventListener('turbo:before-render', dismiss);

    document.addEventListener('turbo:before-frame-render', (event) => {
        if (!(event.target instanceof HTMLElement) || event.target.id !== 'property-main') {
            return;
        }
        // Frame navigations do not fire turbo:before-visit; clear stack before purge so the
        // capture-phase backdrop guard cannot block clicks on the incoming page.
        resetPropertyModalStackSilently('turbo:before-frame-render');
        purgeOrphanedTeleportedPropertyModals('turbo:before-frame-render');
    });

    document.addEventListener('turbo:frame-load', (event) => {
        if (!(event.target instanceof HTMLElement) || event.target.id !== 'property-main') {
            return;
        }
        // Orphan purge runs on turbo:before-frame-render only — purging here races Alpine.initTree
        // and removes freshly teleported create/edit modals from the new page.
        requestAnimationFrame(() => {
            resetPropertyModalStackSilently('turbo:frame-load');
        });
    });
}

function bindEscapeHandler() {
    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key !== 'Escape' || !modalStack.length) {
                return;
            }

            const top = modalStack[modalStack.length - 1];
            if (top?.closeOnEscape !== true) {
                return;
            }

            closeTopPropertyModal();

            event.stopImmediatePropagation();
            event.preventDefault();
        },
        true,
    );
}

bindTurboModalLifecycle();
function bindModalBackdropGuard() {
    document.addEventListener(
        'click',
        (event) => {
            if (modalStack.length === 0) {
                return;
            }

            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const modalRoot = target.closest('[data-property-modal]');
            if (!modalRoot) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();

                return;
            }

            if (!target.closest('[data-property-modal-panel]')) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
            }
        },
        true,
    );
}

bindEscapeHandler();
bindModalBackdropGuard();

window.PropertyModalManager = {
    MODAL_Z,
    register: registerPropertyModal,
    unregister: unregisterPropertyModal,
    closeTop: closeTopPropertyModal,
    closeAll: closeAllPropertyModals,
    resetStackSilently: resetPropertyModalStackSilently,
    purgeOrphaned: purgeOrphanedTeleportedPropertyModals,
    getStack: getPropertyModalStack,
    isTop: isTopPropertyModal,
    lockScroll: lockPropertyModalScroll,
    unlockScroll: unlockPropertyModalScroll,
    recoverScroll: recoverPropertyScrollState,
    inspect: inspectPropertyModals,
    isModalElement,
};

window.inspectPropertyModals = inspectPropertyModals;
window.recoverPropertyScrollState = recoverPropertyScrollState;

if (MODAL_DEBUG) {
    window.addEventListener('property-modal:open', (e) => debugLog('event open', e.detail));
    window.addEventListener('property-modal:close', (e) => debugLog('event close', e.detail));
}
