/**
 * Property GET filter forms: debounced search + auto-apply on dropdown/date changes.
 */

const PROPERTY_MAIN_FRAME_ID = 'property-main';

function isPropertyWorkspaceHydrating() {
    return window.__propertyWorkspaceHydrating === true;
}
const SEARCH_DEBOUNCE_MS = 300;
const CONTROL_APPLY_DEBOUNCE_MS = 120;

/** @typedef {{ inFlight: boolean, queuedSearch: boolean, activeSubmission: object|null }} FilterFormState */

/** @type {WeakMap<HTMLFormElement, FilterFormState>} */
const filterFormState = new WeakMap();

/** @type {WeakMap<HTMLInputElement, number>} */
const searchDebounceTimers = new WeakMap();

/** @type {WeakMap<HTMLFormElement, number>} */
const controlApplyDebounceTimers = new WeakMap();

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
 * @param {HTMLFormElement} form
 * @param {'search'|'apply'} source
 */
export function submitPropertyFilterForm(form, source = 'apply') {
    if (!(form instanceof HTMLFormElement) || isPropertyWorkspaceHydrating()) {
        return;
    }

    const state = getFilterFormState(form);
    const nextQuery = serializedFormQuery(form);

    if (source === 'search' && nextQuery === form.dataset.lastFilterQuery) {
        return;
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

function scheduleSearchSubmit(form, input) {
    const existing = searchDebounceTimers.get(input);
    if (existing) {
        window.clearTimeout(existing);
    }

    const timer = window.setTimeout(() => {
        searchDebounceTimers.delete(input);
        submitPropertyFilterForm(form, 'search');
    }, SEARCH_DEBOUNCE_MS);

    searchDebounceTimers.set(input, timer);
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
                    scheduleSearchSubmit(form, input);
                });
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
                form.dataset.lastFilterQuery = serializedFormQuery(form);
            },
            { capture: true },
        );
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
        state.activeSubmission = null;
        form.dataset.lastFilterQuery = serializedFormQuery(form);

        if (state.queuedSearch) {
            state.queuedSearch = false;
            queueMicrotask(() => submitPropertyFilterForm(form, 'search'));
        }
    });
}

function bindFilterLifecycle() {
    const run = (scope) => {
        wireAutoFilterForms(scope || document);
        syncPropertyFilterDesktopForms();
    };

    document.addEventListener('DOMContentLoaded', () => run(document));
    document.addEventListener('turbo:load', () => run(document));
    document.addEventListener('turbo:frame-load', (event) => {
        run(event.target instanceof HTMLElement ? event.target : document);
    });
    document.addEventListener('livewire:navigated', () => run(document));
    document.addEventListener('alpine:navigated', () => run(document));
    window.addEventListener('resize', syncPropertyFilterDesktopForms, { passive: true });
}

bindFilterFormTurboGuards();
bindFilterLifecycle();
