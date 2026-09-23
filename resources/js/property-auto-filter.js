/**
 * Property GET filter forms: live row search as you type + auto-apply on dropdown/date changes.
 */

const PROPERTY_MAIN_FRAME_ID = 'property-main';
const SEARCH_FOCUS_STORAGE_KEY = 'property.portal.searchFocus';
const SEARCH_SUBMIT_DEBOUNCE_MS = 320;

function isPropertyWorkspaceHydrating() {
    return window.__propertyWorkspaceHydrating === true;
}

function liveFilterScope(el) {
    return (
        el.closest('.property-ws-wrap')
        || el.closest('#property-list-results')
        || el.closest('#property-main')
        || el.closest('[data-property-filter-toolbar]')?.parentElement
        || document
    );
}

/**
 * Paginated directory filters must search on the server. Client-only row hiding
 * only covers the current page and looks like "search broken".
 */
function prefersServerSearch(control) {
    if (!(control instanceof HTMLInputElement) || control.disabled) {
        return false;
    }
    // Explicit client-only search (small in-memory tables).
    if (
        control.matches('[data-live-row-filter-only], [data-table-filter]')
        || control.dataset.liveRowFilter === '1'
        || control.dataset.serverSearch === 'false'
    ) {
        return false;
    }

    const form = control.form || control.closest('form');
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') {
        return false;
    }
    if (form.matches('[data-live-row-filter-only]')) {
        return false;
    }

    return (
        control.name === 'q'
        || control.dataset.serverSearch === 'true'
        || control.dataset.serverSearch === '1'
    );
}

function isLiveSearchControl(control) {
    if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement) || control.disabled) {
        return false;
    }
    if (control.closest('[x-data*="listingVacantRoster"]')) {
        return false;
    }
    if (control instanceof HTMLInputElement && (control.type === 'hidden' || control.type === 'submit')) {
        return false;
    }
    if (prefersServerSearch(control)) {
        return false;
    }

    return (
        control.matches('[data-live-row-filter]')
        || control.matches('[data-table-filter]')
        || (control instanceof HTMLInputElement && (
            control.dataset.autoSearch === 'true'
            || control.dataset.liveRowFilter === '1'
        ))
    );
}

function liveFilterNeedles(scope) {
    /** @type {string[]} */
    const needles = [];
    const seen = new Set();

    scope.querySelectorAll('input, select').forEach((control) => {
        if (!isLiveSearchControl(control)) {
            return;
        }
        const value = (control.value || '').toLowerCase().trim();
        if (value === '' || seen.has(value)) {
            return;
        }
        seen.add(value);
        needles.push(value);
    });

    return needles;
}

function liveFilterRows(scope, needles) {
    let rows = [
        ...scope.querySelectorAll('tbody tr[data-filter-text]'),
        ...scope.querySelectorAll('[data-mobile-record-list] article[data-filter-text]'),
    ];

    if (rows.length === 0) {
        rows = [...scope.querySelectorAll('tbody tr')].filter((row) => !row.querySelector('td[colspan]'));
    }

    rows.forEach((row) => {
        const hay = (row.getAttribute('data-filter-text') || row.textContent || '').toLowerCase();
        const visible = needles.every((needle) => hay.includes(needle));
        row.classList.toggle('hidden', !visible);
        row.toggleAttribute('hidden', !visible);
    });
}

/**
 * Instantly hide table/card rows that do not match the current search text.
 * Does not hit the server — same behaviour as the listings vacant-unit search.
 *
 * @param {HTMLElement} input
 */
export function applyLiveWorkspaceSearch(input) {
    if (!(input instanceof HTMLElement) || input.closest('[x-data*="listingVacantRoster"]')) {
        return;
    }

    const scope = liveFilterScope(input);
    liveFilterRows(scope, liveFilterNeedles(scope));
}

/** @typedef {{ inFlight: boolean, queuedSearch: boolean, activeSubmission: object|null }} FilterFormState */
/** @typedef {{ name: string, formAction: string, selectionStart: number, selectionEnd: number }} SearchFocusMeta */

/** @type {WeakMap<HTMLFormElement, FilterFormState>} */
const filterFormState = new WeakMap();

const CONTROL_APPLY_DEBOUNCE_MS = 120;

/** @type {WeakMap<HTMLFormElement, number>} */
const controlApplyDebounceTimers = new WeakMap();

/** @type {WeakMap<HTMLFormElement, number>} */
const searchSubmitDebounceTimers = new WeakMap();

/** @type {SearchFocusMeta|null} */
let pendingSearchFocusMeta = null;

function getFilterFormState(form) {
    let state = filterFormState.get(form);
    if (!state) {
        state = { inFlight: false, queuedSearch: false, activeSubmission: null };
        filterFormState.set(form, state);
    }

    return state;
}

function serializedFormQuery(form) {
    try {
        return new URLSearchParams(new FormData(form)).toString();
    } catch {
        return '';
    }
}

function isSearchInput(el) {
    return (
        el instanceof HTMLInputElement &&
        (el.name === 'q' || el.type === 'search' || el.dataset.autoSearch === 'true')
    );
}

function isAutoApplyControl(el) {
    if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)) {
        return false;
    }

    if (el.matches('[data-auto-submit="off"]') || el.disabled) {
        return false;
    }

    if (isSearchInput(el)) {
        return false;
    }

    if (el instanceof HTMLSelectElement) {
        return true;
    }

    if (el instanceof HTMLTextAreaElement) {
        return true;
    }

    const type = (el.type || 'text').toLowerCase();
    if (['hidden', 'submit', 'button', 'reset', 'file', 'image', 'password'].includes(type)) {
        return false;
    }

    return ['date', 'month', 'number', 'checkbox', 'radio', 'time', 'datetime-local', 'week'].includes(type);
}

function formFilterControls(form) {
    const controls = form?.elements ? Array.from(form.elements) : [];

    return controls.filter((el) => el instanceof HTMLElement && !el.matches('[data-auto-submit="off"]'));
}

function ensurePropertyTurboFrame(form) {
    if (form.method.toLowerCase() === 'get' && !form.hasAttribute('data-turbo-frame') && form.dataset.turbo !== 'false') {
        form.setAttribute('data-turbo-frame', PROPERTY_MAIN_FRAME_ID);
    }
}

function abortActiveFilterSubmission(form) {
    const state = getFilterFormState(form);
    const submission = state.activeSubmission;
    if (submission && typeof submission.abort === 'function') {
        try {
            submission.abort();
        } catch {
            // ignore abort races
        }
    }
    state.activeSubmission = null;
}

/**
 * @param {HTMLInputElement} input
 */
function trackSearchFocus(input) {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const form = input.form;
    pendingSearchFocusMeta = {
        name: input.name || 'q',
        formAction: form?.action || window.location.href,
        selectionStart: input.selectionStart ?? input.value.length,
        selectionEnd: input.selectionEnd ?? input.value.length,
    };
}

/**
 * @param {HTMLInputElement} input
 */
function persistSearchFocusForSubmit(input) {
    trackSearchFocus(input);
    if (!pendingSearchFocusMeta) {
        return;
    }

    try {
        sessionStorage.setItem(SEARCH_FOCUS_STORAGE_KEY, JSON.stringify(pendingSearchFocusMeta));
    } catch {
        // ignore quota / private mode
    }
}

function readStoredSearchFocus() {
    try {
        const raw = sessionStorage.getItem(SEARCH_FOCUS_STORAGE_KEY);
        if (!raw) {
            return pendingSearchFocusMeta;
        }

        return JSON.parse(raw);
    } catch {
        return pendingSearchFocusMeta;
    }
}

function clearStoredSearchFocus() {
    pendingSearchFocusMeta = null;
    try {
        sessionStorage.removeItem(SEARCH_FOCUS_STORAGE_KEY);
    } catch {
        // ignore
    }
}

function formActionMatches(form, expectedAction) {
    if (!(form instanceof HTMLFormElement) || !expectedAction) {
        return true;
    }

    try {
        const expected = new URL(expectedAction, window.location.href);
        const actual = new URL(form.action || window.location.href, window.location.href);

        return expected.pathname === actual.pathname;
    } catch {
        return String(form.action || '') === String(expectedAction || '');
    }
}

/**
 * @param {HTMLElement|Document} scopeRoot
 */
export function restorePropertySearchFocus(scopeRoot) {
    const meta = readStoredSearchFocus();
    if (!meta?.name) {
        return;
    }

    const root = scopeRoot instanceof HTMLElement ? scopeRoot : document;
    const forms = root.querySelectorAll('form[method="get"]');
    /** @type {HTMLInputElement[]} */
    const matches = [];

    forms.forEach((form) => {
        if (!(form instanceof HTMLFormElement) || !formActionMatches(form, meta.formAction)) {
            return;
        }

        form.querySelectorAll(`input[name="${meta.name}"]`).forEach((el) => {
            if (el instanceof HTMLInputElement && isSearchInput(el) && !el.disabled) {
                matches.push(el);
            }
        });
    });

    if (matches.length === 0) {
        return;
    }

    const visible = matches.find((el) => {
        return el.getClientRects().length > 0 && !el.closest('[hidden]');
    }) || matches[0];

    const length = visible.value.length;
    const start = Math.min(meta.selectionStart ?? length, length);
    const end = Math.min(meta.selectionEnd ?? length, length);

    visible.focus({ preventScroll: true });
    try {
        visible.setSelectionRange(start, end);
    } catch {
        // ignore for unsupported input types
    }

    clearStoredSearchFocus();
}

/**
 * @param {HTMLFormElement} form
 * @param {'search'|'apply'} source
 * @param {HTMLInputElement|null} searchInput
 */
export function submitPropertyFilterForm(form, source = 'apply', searchInput = null) {
    if (!(form instanceof HTMLFormElement) || isPropertyWorkspaceHydrating()) {
        return;
    }

    const state = getFilterFormState(form);
    const nextQuery = serializedFormQuery(form);

    if (source === 'search' && nextQuery === form.dataset.lastFilterQuery) {
        return;
    }

    if (source === 'search' && searchInput instanceof HTMLInputElement) {
        persistSearchFocusForSubmit(searchInput);
    }

    if (state.inFlight) {
        if (source === 'search') {
            state.queuedSearch = true;
            abortActiveFilterSubmission(form);
        } else {
            return;
        }
    }

    state.inFlight = true;
    form.requestSubmit();
}

function scheduleControlApply(form) {
    const existing = controlApplyDebounceTimers.get(form);
    if (existing) {
        window.clearTimeout(existing);
    }

    const timer = window.setTimeout(() => {
        controlApplyDebounceTimers.delete(form);
        submitPropertyFilterForm(form, 'apply');
    }, CONTROL_APPLY_DEBOUNCE_MS);

    controlApplyDebounceTimers.set(form, timer);
}

/**
 * @param {HTMLFormElement} form
 * @param {HTMLInputElement} input
 */
function scheduleServerSearchSubmit(form, input) {
    const existing = searchSubmitDebounceTimers.get(form);
    if (existing) {
        window.clearTimeout(existing);
    }

    const timer = window.setTimeout(() => {
        searchSubmitDebounceTimers.delete(form);
        // New search should always start at page 1.
        const pageInput = form.querySelector('input[name="page"]');
        if (pageInput instanceof HTMLInputElement) {
            pageInput.value = '1';
        }
        submitPropertyFilterForm(form, 'search', input);
    }, SEARCH_SUBMIT_DEBOUNCE_MS);

    searchSubmitDebounceTimers.set(form, timer);
}

export function wireAutoFilterForms(scopeRoot) {
    if (isPropertyWorkspaceHydrating()) {
        return;
    }

    const root = scopeRoot || document;
    const forms = Array.from(root.querySelectorAll('form[method="get"]:not([data-auto-submit="off"])'));

    forms.forEach((form) => {
        if (form.dataset.autoSubmitBound === '1') {
            return;
        }
        form.dataset.autoSubmitBound = '1';

        ensurePropertyTurboFrame(form);
        form.dataset.lastFilterQuery = serializedFormQuery(form);

        formFilterControls(form)
            .filter((control) => isSearchInput(control))
            .forEach((input) => {
                input.addEventListener('input', () => {
                    if (prefersServerSearch(input)) {
                        scheduleServerSearchSubmit(form, input);
                        return;
                    }
                    applyLiveWorkspaceSearch(input);
                });
                input.addEventListener('focus', () => {
                    trackSearchFocus(input);
                });
                if (!prefersServerSearch(input)) {
                    applyLiveWorkspaceSearch(input);
                }
            });

        formFilterControls(form)
            .filter((control) => isAutoApplyControl(control))
            .forEach((control) => {
                control.addEventListener('change', () => {
                    scheduleControlApply(form);
                });
            });

        form.addEventListener(
            'submit',
            () => {
                const active = document.activeElement;
                if (active instanceof HTMLInputElement && form.contains(active) && isSearchInput(active)) {
                    persistSearchFocusForSubmit(active);
                }
                form.dataset.lastFilterQuery = serializedFormQuery(form);
            },
            { capture: true },
        );
    });

    requestAnimationFrame(() => {
        restorePropertySearchFocus(root);
    });
}

export function syncPropertyFilterDesktopForms() {
    const isMobile = window.matchMedia('(max-width: 767px)').matches;
    document.querySelectorAll('[data-property-filter-form-desktop]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        Array.from(form.elements).forEach((el) => {
            if (
                !(
                    el instanceof HTMLInputElement ||
                    el instanceof HTMLSelectElement ||
                    el instanceof HTMLTextAreaElement ||
                    el instanceof HTMLButtonElement
                )
            ) {
                return;
            }
            if (isMobile) {
                el.setAttribute('disabled', 'disabled');
            } else {
                el.removeAttribute('disabled');
            }
        });
    });
}

function bindFilterFormTurboGuards() {
    document.addEventListener('turbo:submit-start', (event) => {
        const submission = event.detail?.formSubmission;
        const form = submission?.formElement;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') {
            return;
        }
        if (!form.dataset.autoSubmitBound) {
            return;
        }

        const state = getFilterFormState(form);
        if (state.activeSubmission && state.activeSubmission !== submission) {
            try {
                state.activeSubmission.abort?.();
            } catch {
                // ignore
            }
        }
        state.activeSubmission = submission;
    });

    document.addEventListener('turbo:submit-end', (event) => {
        const submission = event.detail?.formSubmission;
        const form = submission?.formElement;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const state = getFilterFormState(form);
        state.inFlight = false;
        state.queuedSearch = false;
        state.activeSubmission = null;
        form.dataset.lastFilterQuery = serializedFormQuery(form);

        const searchInput = form.querySelector('input[name="q"], input[type="search"], input[data-auto-search="true"]');
        if (searchInput instanceof HTMLInputElement && !prefersServerSearch(searchInput)) {
            queueMicrotask(() => applyLiveWorkspaceSearch(searchInput));
        }
    });
}

function bindFilterLifecycle() {
    const run = (scope) => {
        wireAutoFilterForms(scope || document);
        syncPropertyFilterDesktopForms();
    };

    document.addEventListener('input', (event) => {
        const el = event.target;
        if (!(el instanceof HTMLInputElement) || !isSearchInput(el) || el.matches('[data-auto-submit="off"]')) {
            return;
        }
        if (prefersServerSearch(el)) {
            const form = el.form || el.closest('form');
            if (form instanceof HTMLFormElement) {
                scheduleServerSearchSubmit(form, el);
            }
            return;
        }
        applyLiveWorkspaceSearch(el);
    });

    document.addEventListener('DOMContentLoaded', () => run(document));
    document.addEventListener('turbo:load', () => run(document));
    document.addEventListener('turbo:frame-load', (event) => {
        const frame = event.target instanceof HTMLElement ? event.target : document;
        run(frame);
    });
    document.addEventListener('livewire:navigated', () => run(document));
    document.addEventListener('alpine:navigated', () => run(document));
    window.addEventListener('resize', syncPropertyFilterDesktopForms, { passive: true });
}

bindFilterFormTurboGuards();
bindFilterLifecycle();
