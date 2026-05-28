/**
 * Property portal action menus — teleport to body + global cleanup.
 * Only menus marked with data-property-dropdown-root (or legacy data-dropdown-root).
 */

const MENU_Z_INDEX = '9999';

const PROPERTY_DROPDOWN_ROOT_SELECTOR = [
    'details[data-property-dropdown-root]',
    'details[data-dropdown-root]',
].join(', ');

const PROPERTY_DROPDOWN_MENU_SELECTOR = [
    '[data-property-dropdown-menu]',
    '[data-dropdown-menu]',
    '[data-table-actions-menu]',
].join(', ');

const PROPERTY_DROPDOWN_TRIGGER_SELECTOR = [
    '[data-property-dropdown-trigger]',
    '[data-dropdown-trigger]',
].join(', ');

const PROPERTY_DROPDOWN_OPEN_SELECTOR = [
    'details[data-property-dropdown-root][open]',
    'details[data-dropdown-root][open]',
].join(', ');

let globalListenersBound = false;

function menuForRoot(details) {
    const id = details?.dataset?.propertyDropdownId || details?.dataset?.dropdownId || details?.dataset?.tableActionsId;
    if (id) {
        const byFor = document.querySelector(
            `[data-property-dropdown-menu][data-property-dropdown-for="${CSS.escape(id)}"], [data-dropdown-menu][data-dropdown-for="${CSS.escape(id)}"], [data-table-actions-menu][data-table-actions-for="${CSS.escape(id)}"]`,
        );
        if (byFor) {
            return byFor;
        }
    }

    return details?.querySelector(PROPERTY_DROPDOWN_MENU_SELECTOR) || null;
}

function placeTeleportedMenu(details, menu, summary) {
    const rect = summary.getBoundingClientRect();
    const gap = 4;
    const pad = 12;
    const footerEl = document.getElementById('property-shell-footer');
    const footerH = footerEl ? footerEl.getBoundingClientRect().height : 0;
    const viewportBottom = window.innerHeight - footerH - pad;

    menu.style.position = 'fixed';
    menu.style.left = 'auto';
    menu.style.right = `${Math.round(window.innerWidth - rect.right)}px`;
    menu.style.zIndex = MENU_Z_INDEX;
    menu.style.maxHeight = '';
    menu.style.overflowY = '';
    menu.style.minWidth = `${Math.max(176, Math.round(rect.width))}px`;

    const menuH = menu.offsetHeight || 0;
    const openBelow = rect.bottom + gap + menuH <= viewportBottom;

    if (openBelow) {
        menu.style.top = `${Math.round(rect.bottom + gap)}px`;
    } else {
        menu.style.top = `${Math.round(Math.max(pad, rect.top - gap - menuH))}px`;
    }

    const menuRect = menu.getBoundingClientRect();
    if (menuRect.bottom > viewportBottom) {
        const maxH = Math.max(120, viewportBottom - menuRect.top);
        menu.style.maxHeight = `${Math.round(maxH)}px`;
        menu.style.overflowY = 'auto';
    }
}

export function restoreDropdownMenu(menu) {
    if (!menu?.nodeType) {
        return;
    }

    menu.style.position = '';
    menu.style.top = '';
    menu.style.left = '';
    menu.style.right = '';
    menu.style.zIndex = '';
    menu.style.maxHeight = '';
    menu.style.overflowY = '';
    menu.style.minWidth = '';
    menu.style.display = '';
    menu.hidden = false;

    const ownerId = menu.dataset.propertyDropdownFor
        || menu.dataset.dropdownFor
        || menu.dataset.tableActionsFor
        || menu.dataset.dropdownOwner
        || '';

    let owner = menu._propertyDropdownOwner instanceof HTMLElement && menu._propertyDropdownOwner.isConnected
        ? menu._propertyDropdownOwner
        : null;

    if (!owner && ownerId) {
        owner = document.querySelector(
            `details[data-property-dropdown-id="${CSS.escape(ownerId)}"], details[data-dropdown-id="${CSS.escape(ownerId)}"], details[data-table-actions-id="${CSS.escape(ownerId)}"]`,
        );
    }

    if (owner) {
        owner.open = false;
        const summary = owner.querySelector('summary');
        summary?.setAttribute('aria-expanded', 'false');
        if (menu.parentElement === document.body) {
            owner.appendChild(menu);
        }
        return;
    }

    if (menu.parentElement === document.body) {
        menu.remove();
    }
}

export function closeAllPropertyDropdowns() {
    document.querySelectorAll(PROPERTY_DROPDOWN_MENU_SELECTOR).forEach((menu) => {
        restoreDropdownMenu(menu);
    });

    document.querySelectorAll(`body > ${PROPERTY_DROPDOWN_MENU_SELECTOR}`).forEach((menu) => {
        menu.remove();
    });

    document.querySelectorAll(PROPERTY_DROPDOWN_OPEN_SELECTOR).forEach((details) => {
        details.open = false;
        details.querySelector('summary')?.setAttribute('aria-expanded', 'false');
    });

    document.querySelectorAll(`${PROPERTY_DROPDOWN_TRIGGER_SELECTOR}[aria-expanded="true"]`).forEach((trigger) => {
        trigger.setAttribute('aria-expanded', 'false');
    });
}

function handleOutsideClick(event) {
    const target = event.target;
    if (!(target instanceof Node)) {
        return;
    }

    if (window.PropertyModalManager?.isModalElement?.(target)) {
        return;
    }

    document.querySelectorAll(`${PROPERTY_DROPDOWN_ROOT_SELECTOR}[open]`).forEach((details) => {
        const summary = details.querySelector('summary');
        const menu = menuForRoot(details);
        const inside = details.contains(target) || menu?.contains(target);
        if (!inside) {
            details.open = false;
            summary?.setAttribute('aria-expanded', 'false');
            if (menu) {
                restoreDropdownMenu(menu);
            }
        }
    });

    document.querySelectorAll(`body > ${PROPERTY_DROPDOWN_MENU_SELECTOR}`).forEach((menu) => {
        if (!menu.contains(target)) {
            restoreDropdownMenu(menu);
        }
    });
}

function bindGlobalDropdownListeners() {
    if (globalListenersBound) {
        return;
    }
    globalListenersBound = true;

    const close = () => closeAllPropertyDropdowns();

    document.addEventListener('turbo:before-visit', close);
    document.addEventListener('turbo:before-render', close);
    document.addEventListener('turbo:before-cache', close);
    document.addEventListener('turbo:before-frame-render', close);
    document.addEventListener('turbo:frame-request-started', close);
    document.addEventListener('livewire:navigating', close);
    document.addEventListener('alpine:navigated', close);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        if (window.PropertyModalManager?.getStack?.()?.length) {
            return;
        }
        closeAllPropertyDropdowns();
    });

    document.addEventListener('click', handleOutsideClick, true);

    document.addEventListener('click', (event) => {
        const actionable = event.target?.closest?.(
            `${PROPERTY_DROPDOWN_MENU_SELECTOR} a[href], ${PROPERTY_DROPDOWN_MENU_SELECTOR} button[type="submit"], ${PROPERTY_DROPDOWN_MENU_SELECTOR} button:not([type="button"]), ${PROPERTY_DROPDOWN_MENU_SELECTOR} [data-property-lease-row-action]`,
        );
        if (actionable) {
            closeAllPropertyDropdowns();
        }
    }, true);

    window.addEventListener('scroll', close, { passive: true, capture: true });
    window.addEventListener('resize', close, { passive: true });
}

export function setupPropertyActionMenus(scopeRoot) {
    const root = scopeRoot instanceof Element ? scopeRoot : document;

    root.querySelectorAll(PROPERTY_DROPDOWN_ROOT_SELECTOR).forEach((details) => {
        if (!(details instanceof HTMLDetailsElement)) {
            return;
        }
        if (details.dataset.propertyDropdownBound === '1') {
            return;
        }

        const summary = details.querySelector('summary');
        const menu = menuForRoot(details);
        if (!summary || !menu || menu.tagName !== 'DIV') {
            return;
        }

        details.dataset.propertyDropdownBound = '1';

        if (!details.dataset.propertyDropdownId) {
            details.dataset.propertyDropdownId = `pd-${Math.random().toString(36).slice(2, 11)}`;
        }

        if (!summary.hasAttribute('data-property-dropdown-trigger')) {
            summary.setAttribute('data-property-dropdown-trigger', '');
        }
        summary.setAttribute('aria-haspopup', 'menu');
        summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');

        if (!menu.hasAttribute('data-property-dropdown-menu')) {
            menu.setAttribute('data-property-dropdown-menu', '');
        }
        menu.dataset.propertyDropdownFor = details.dataset.propertyDropdownId;
        menu._propertyDropdownOwner = details;

        details.addEventListener('toggle', () => {
            const activeMenu = menuForRoot(details) || menu;
            summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');

            if (!details.open) {
                restoreDropdownMenu(activeMenu);
                return;
            }

            root.querySelectorAll(`${PROPERTY_DROPDOWN_ROOT_SELECTOR}[open]`).forEach((other) => {
                if (other !== details) {
                    other.open = false;
                    other.querySelector('summary')?.setAttribute('aria-expanded', 'false');
                    const otherMenu = menuForRoot(other);
                    if (otherMenu) {
                        restoreDropdownMenu(otherMenu);
                    }
                }
            });

            document.body.appendChild(activeMenu);
            activeMenu._propertyDropdownOwner = details;
            placeTeleportedMenu(details, activeMenu, summary);
        });
    });
}

/** @deprecated Use setupPropertyActionMenus */
export function setupPropertyTableActionDropdowns(scopeRoot) {
    setupPropertyActionMenus(scopeRoot);
}

export function setupPropertyDropdownUi(scopeRoot) {
    setupPropertyActionMenus(scopeRoot);
    if (typeof window.setupPropertyLeaseRowActions === 'function') {
        window.setupPropertyLeaseRowActions(scopeRoot);
    }
}

bindGlobalDropdownListeners();
window.closeAllPropertyDropdowns = closeAllPropertyDropdowns;
window.setupPropertyActionMenus = setupPropertyActionMenus;
window.setupPropertyTableActionDropdowns = setupPropertyTableActionDropdowns;
window.setupPropertyDropdownUi = setupPropertyDropdownUi;

document.addEventListener('DOMContentLoaded', () => setupPropertyDropdownUi(document));
document.addEventListener('turbo:load', () => setupPropertyDropdownUi(document));
document.addEventListener('turbo:frame-load', (event) => setupPropertyDropdownUi(event.target));
