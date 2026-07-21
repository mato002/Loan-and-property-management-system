/**
 * Staff Property ↔ Loan module switcher.
 * Choose-module entry uses Turbo Drive (no full browser reload).
 * In-portal switches use Turbo Drive with a loading overlay.
 */

import * as Turbo from '@hotwired/turbo';
import { closeAllPropertyDropdowns } from './property-dropdown-cleanup';

const MODULE_SWITCH_RE = /\/switch-module\/(property|loan)\/?$/i;

function isChooseModulePage() {
    return /\/choose-module\/?$/i.test(window.location.pathname);
}

function isModuleSwitchLink(link) {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }

    if (link.dataset.chooseModuleEnter === 'property' || link.dataset.chooseModuleEnter === 'loan') {
        return false;
    }

    if (link.dataset.staffModuleSwitch === '1') {
        return true;
    }

    const href = link.getAttribute('href') || '';
    if (!href.includes('/switch-module/')) {
        return false;
    }

    try {
        return MODULE_SWITCH_RE.test(new URL(href, window.location.href).pathname);
    } catch {
        return false;
    }
}

function dismissBlockingOverlays() {
    if (typeof closeAllPropertyDropdowns === 'function') {
        closeAllPropertyDropdowns();
    }

    document.getElementById('property-search-suggest')?.classList.add('hidden', 'pointer-events-none');
    document.getElementById('property-mobile-search-overlay')?.classList.add('hidden');
    document.getElementById('property-mobile-search-suggest')?.classList.add('hidden');

    document.querySelectorAll('[x-data]').forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }
        if (el.__x?.$data && typeof el.__x.$data.userMenuOpen === 'boolean') {
            el.__x.$data.userMenuOpen = false;
        }
        if (el.__x?.$data && typeof el.__x.$data.bellOpen === 'boolean') {
            el.__x.$data.bellOpen = false;
        }
    });
}

export function wireStaffModuleSwitchLinks(root = document) {
    const scope = root instanceof Document ? root : root;

    scope.querySelectorAll('a[href*="/switch-module/"]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        if (link.dataset.chooseModuleEnter === 'property' || link.dataset.chooseModuleEnter === 'loan') {
            link.removeAttribute('data-turbo');
            link.removeAttribute('data-staff-module-switch');
            return;
        }

        link.dataset.staffModuleSwitch = '1';
        link.classList.add('relative', 'z-[80]');
    });
}

function showModuleSwitchLoading() {
    let el = document.getElementById('staff-module-switch-loading');
    if (!el) {
        el = document.createElement('div');
        el.id = 'staff-module-switch-loading';
        el.className = 'fixed inset-0 z-[200] flex flex-col items-center justify-center gap-3 bg-slate-900/45 backdrop-blur-[1px]';
        el.innerHTML = `
            <div class="h-10 w-10 animate-spin rounded-full border-4 border-white/30 border-t-white" role="status" aria-label="Loading"></div>
            <p class="text-sm font-semibold text-white">Opening module…</p>
        `;
        document.body.appendChild(el);
    }

    el.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function hideModuleSwitchLoading() {
    document.getElementById('staff-module-switch-loading')?.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function wireChooseModuleEntryLinks(root = document) {
    const scope = root instanceof Document ? root : root;

    scope.querySelectorAll('a[data-choose-module-enter]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement) || link.dataset.chooseModuleEnterBound === '1') {
            return;
        }

        link.dataset.chooseModuleEnterBound = '1';
        link.addEventListener('click', () => {
            showModuleSwitchLoading();
        });
    });
}

function handleModuleSwitchClick(event) {
    if (isChooseModulePage()) {
        return;
    }

    const link = event.target?.closest?.('a[href*="/switch-module/"]');
    if (!isModuleSwitchLink(link)) {
        return;
    }

    const href = link.href;
    if (!href) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') {
        event.stopImmediatePropagation();
    }

    dismissBlockingOverlays();
    showModuleSwitchLoading();
    Turbo.visit(href, { action: 'advance' });
}

let listenersBound = false;

function bindStaffModuleSwitchListeners() {
    if (listenersBound) {
        return;
    }
    listenersBound = true;

    document.addEventListener('click', handleModuleSwitchClick, { capture: true });
    document.addEventListener('turbo:load', () => {
        hideModuleSwitchLoading();
        wireStaffModuleSwitchLinks();
        wireChooseModuleEntryLinks();
    });
    document.addEventListener('DOMContentLoaded', () => {
        wireStaffModuleSwitchLinks();
        wireChooseModuleEntryLinks();
    });
}

bindStaffModuleSwitchListeners();
wireStaffModuleSwitchLinks();
wireChooseModuleEntryLinks();
