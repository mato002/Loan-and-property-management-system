/**
 * Property GET filter forms: live row search as you type + auto-apply on dropdown/date changes.
 */

const PROPERTY_MAIN_FRAME_ID = 'property-main';
const SEARCH_FOCUS_STORAGE_KEY = 'property.portal.searchFocus';
const SEARCH_SUBMIT_DEBOUNCE_MS = 450;

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
 * Paginated directory filters can still opt into server search with
 * data-server-search="1". Default search boxes live-filter the table only.
 */
function prefersServerSearch(control) {
    if (!(control instanceof HTMLInputElement) || control.disabled) {
        return false;
    }
    // Explicit client-only search (default for registers / listings-style).
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

    // Only when explicitly marked — never just because name="q".
    return control.dataset.serverSearch === 'true' || control.dataset.serverSearch === '1';
}

function isSearchInput(el) {
    if (!(el instanceof HTMLInputElement) || el.closest('[data-statement-assign]')) {
        return false;
    }

    return el.name === 'q' || el.type === 'search' || el.dataset.autoSearch === 'true' || el.dataset.filterSearch === '1';
}

function isFilterSearchInput(el) {
    return (
        el instanceof HTMLInputElement
        && el.dataset.filterSearch !== '0'
        && (el.dataset.filterSearch === '1' || (el.name === 'q' && !!el.closest('[data-property-filter-toolbar]')))
    );
}

function filterControlIsVisible(el) {
    if (!(el instanceof Element)) {
        return false;
    }

    let node = el;
    while (node instanceof Element) {
        if (node.hidden) {
            return false;
        }
        const style = window.getComputedStyle(node);
        if (style.display === 'none' || style.visibility === 'hidden') {
            return false;
        }
        node = node.parentElement;
    }

    return true;
}

/** Text the user typed, kept so a duplicate hidden box cannot wipe the first search. */
let lastFilterSearchValue = '';
/** Path the remembered search belongs to, so another list does not inherit it. */
let filterSearchPath = '';
/** Non-empty search sent with the last Apply, used if the response comes back blank. */
let pendingFilterSubmit = '';
let filterSearchResubmitted = false;

function readUrlSearchQuery() {
    try {
        return new URL(window.location.href).searchParams.get('q') || '';
    } catch {
        return '';
    }
}

function uniquifyFilterFieldIds(root) {
    const scope = root instanceof Element || root instanceof Document ? root : document;
    /** @type {Map<string, number>} */
    const seen = new Map();

    scope.querySelectorAll('[id^="filter-field-"]').forEach((el) => {
        if (!(el instanceof HTMLElement) || !el.id) {
            return;
        }
        const count = seen.get(el.id) ?? 0;
        seen.set(el.id, count + 1);
        if (count === 0) {
            return;
        }
        const nextId = `${el.id}--${count}`;
        el.closest('.property-filter-field')?.querySelector(`label[for="${CSS.escape(el.id)}"]`)?.setAttribute('for', nextId);
        el.id = nextId;
    });
}

function stampFilterSearchOnSubmit(form) {
    const searches = [...form.querySelectorAll('input[name="q"]')].filter((el) => el instanceof HTMLInputElement);
    if (searches.length === 0) {
        return;
    }

    const active = document.activeElement;
    const preferred = (active instanceof HTMLInputElement && searches.includes(active) ? active : null)
        || searches.find((el) => filterControlIsVisible(el) && !el.disabled)
        || searches.find((el) => !el.disabled)
        || searches[0];

    const typed = preferred.value !== '' ? preferred.value : lastFilterSearchValue;
    if (typed !== '' && preferred.value !== typed) {
        preferred.value = typed;
    }

    searches.forEach((el) => {
        if (el === preferred) {
            return;
        }
        el.disabled = true;
        el.dataset.filterVisibilityDisabled = '1';
    });

    if (preferred.value === '') {
        lastFilterSearchValue = '';
        pendingFilterSubmit = '';
        return;
    }

    lastFilterSearchValue = preferred.value;
    pendingFilterSubmit = preferred.value;
}

function restoreSubmittedFilterSearch(root) {
    const scope = root instanceof Element || root instanceof Document ? root : document;
    const inputs = [...scope.querySelectorAll('[data-property-filter-toolbar] input[name="q"], input[data-filter-search="1"]')]
        .filter((el) => el instanceof HTMLInputElement);
    const visible = inputs.find((el) => filterControlIsVisible(el));
    if (!(visible instanceof HTMLInputElement)) {
        return;
    }

    const urlQ = readUrlSearchQuery();
    if (urlQ !== '') {
        if (visible.value !== urlQ) {
            visible.value = urlQ;
        }
        lastFilterSearchValue = urlQ;
        filterSearchPath = window.location.pathname;
        pendingFilterSubmit = '';
        filterSearchResubmitted = false;
        return;
    }

    if (filterSearchPath !== '' && window.location.pathname !== filterSearchPath) {
        lastFilterSearchValue = '';
        pendingFilterSubmit = '';
        filterSearchResubmitted = false;
        return;
    }

    if (visible.value === '' && lastFilterSearchValue !== '') {
        visible.value = lastFilterSearchValue;
        applyLiveWorkspaceSearch(visible);
    }

    if (pendingFilterSubmit === '' || filterSearchResubmitted) {
        return;
    }

    filterSearchResubmitted = true;
    visible.value = pendingFilterSubmit;
    lastFilterSearchValue = pendingFilterSubmit;
    visible.form?.requestSubmit();
}

function rememberFilterSearchInput(event) {
    const el = event.target;
    if (!isFilterSearchInput(el) || !filterControlIsVisible(el)) {
        return;
    }

    const inputType = typeof event.inputType === 'string' ? event.inputType : '';
    const userCleared = inputType.startsWith('delete') || inputType === 'deleteByCut';
    if (el.value === '' && lastFilterSearchValue !== '' && !userCleared) {
        el.value = lastFilterSearchValue;
        return;
    }

    lastFilterSearchValue = el.value;
    filterSearchPath = window.location.pathname;
    if (el.value === '') {
        pendingFilterSubmit = '';
    }
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

    // Include the main search box even when it also triggers a server refresh.
    if (control instanceof HTMLInputElement && isSearchInput(control)) {
        return true;
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

    const mobileCards = rows.length > 0
        ? []
        : [...scope.querySelectorAll('[data-mobile-record-list] article')];

    const targets = rows.length > 0 ? rows : mobileCards;

    let visible = 0;
    targets.forEach((row) => {
        const hay = (row.getAttribute('data-filter-text') || row.textContent || '').toLowerCase();
        const match = needles.every((needle) => hay.includes(needle));
        row.classList.toggle('hidden', !match);
        row.toggleAttribute('hidden', !match);
        if (match) {
            visible += 1;
        }
    });

    scope.querySelectorAll('[data-live-filter-count]').forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }
        const total = Number(el.dataset.liveFilterTotal || targets.length) || targets.length;
        if (needles.length === 0) {
            el.textContent = `${total} shown`;
        } else {
            el.textContent = `${visible} of ${total} shown`;
        }
        el.hidden = targets.length === 0;
    });

    ensureLiveFilterEmptyHint(scope, needles.length > 0 && targets.length > 0 && visible === 0);
}

function ensureLiveFilterEmptyHint(scope, show) {
    const host =
        scope.querySelector('[data-property-list-results], #property-list-results, .property-ws-wrap table')?.closest('.property-ws-wrap')
        || scope.querySelector('table')?.parentElement
        || (scope instanceof HTMLElement ? scope : null);

    if (!(host instanceof HTMLElement)) {
        return;
    }

    let hint = host.querySelector('[data-live-filter-empty-hint]');
    if (!show) {
        hint?.remove();
        return;
    }

    if (!hint) {
        hint = document.createElement('p');
        hint.setAttribute('data-live-filter-empty-hint', '1');
        hint.className = 'mt-3 text-sm text-slate-600 dark:text-slate-300 px-1';
        const tableWrap = host.querySelector('.overflow-x-auto, table')?.parentElement || host;
        tableWrap.appendChild(hint);
    }
    hint.textContent = 'No rows on this page match. Click Apply to search the full register.';
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

/**
 * Instant table filter only (listings-style). Server search runs when the user
 * clicks Apply — never on each keystroke, so registers do not full-reload while typing.
 * @param {HTMLInputElement} input
 */
function handleSearchInput(input) {
    applyLiveWorkspaceSearch(input);
}

/**
 * Dropdown/date auto-submit only when the form opts in. Default is Apply-only so
 * filtering does not replace the whole workspace frame while adjusting filters.
 */
function formAllowsAutoApply(form) {
    if (!(form instanceof HTMLFormElement)) {
        return false;
    }
    return form.dataset.autoApplyFilters === 'true' || form.dataset.autoApplyFilters === '1';
}

function parseCount(value) {
    const n = parseInt(String(value || '').replace(/,/g, ''), 10);
    return Number.isFinite(n) && n > 0 ? n : null;
}

function findListTotal(select) {
    const scopes = [
        select.closest('form')?.parentElement,
        select.closest('section, article, .card'),
        select.closest('#property-main, #loan-main, main'),
        document.getElementById('property-main'),
        document.body,
    ].filter((node) => node instanceof HTMLElement);

    for (const scope of scopes) {
        const marked = scope.querySelector('[data-list-total]');
        const markedTotal = parseCount(marked?.getAttribute('data-list-total'));
        if (markedTotal) {
            return markedTotal;
        }
        const text = scope.innerText || '';
        const showing = text.match(/Showing\s+[\d,]+\s*[-–—]\s*[\d,]+\s+of\s+([\d,]+)/i);
        if (showing) {
            return parseCount(showing[1]);
        }
        const records = text.match(/\(([\d,]+)\s+records\)/i);
        if (records) {
            return parseCount(records[1]);
        }
    }

    return null;
}

function pageSizeSteps(total) {
    const steps = [10, 20, 30, 50, 100, 200, 500, 1000];
    if (total && total > 1000) {
        steps.push(2000, 5000);
    }
    return steps.filter((size) => !total || size <= Math.max(total, 100));
}

function ensurePageSizeOption(select, value, label) {
    let option = Array.from(select.options).find((item) => item.value === value);
    if (!option) {
        option = document.createElement('option');
        option.value = value;
        const all = Array.from(select.options).find((item) => item.value === 'all');
        if (all) {
            select.insertBefore(option, all);
        } else {
            select.appendChild(option);
        }
    }
    option.textContent = label;
    return option;
}

function bindCustomPageSize(select) {
    const holder = select.parentElement;
    if (!holder) {
        return;
    }
    let input = holder.querySelector('[data-page-size-custom]');
    if (!(input instanceof HTMLInputElement)) {
        input = document.createElement('input');
        input.type = 'number';
        input.min = '1';
        input.step = '1';
        input.placeholder = 'No.';
        input.setAttribute('data-page-size-custom', '1');
        input.setAttribute('aria-label', 'Custom rows per page');
        input.title = 'Type how many rows to show, then click Apply';
        input.className = 'min-h-[38px] w-[4.25rem] shrink-0 rounded-lg border border-slate-200 bg-white px-2 text-sm';
        if (!holder.classList.contains('flex')) {
            holder.classList.add('flex', 'items-center', 'gap-1');
        }
        select.after(input);
    }
    if (input.dataset.pageSizeBound === '1') {
        return;
    }
    input.dataset.pageSizeBound = '1';

    const applyCustom = () => {
        const raw = input.value.trim();
        if (raw === '') {
            return;
        }
        const size = parseCount(raw);
        if (!size) {
            return;
        }
        ensurePageSizeOption(select, String(size), `${size.toLocaleString()} / page`);
        select.value = String(size);
    };

    input.addEventListener('change', applyCustom);
    select.addEventListener('change', () => {
        if (select.value !== input.value.trim()) {
            input.value = '';
        }
    });
    select.form?.addEventListener('submit', () => {
        if (input.value.trim() !== '') {
            applyCustom();
        }
    }, { capture: true });
}

function enhancePageSizeSelects(root) {
    const scope = root || document;
    scope.querySelectorAll('select[name="per_page"], select[name$="_per_page"]').forEach((select) => {
        if (!(select instanceof HTMLSelectElement) || select.dataset.serverPageSize === '1') {
            return;
        }
        const total = findListTotal(select);
        if (select.dataset.pageSizeEnhanced === '1' && (select.dataset.pageSizeTotal || !total)) {
            return;
        }
        select.dataset.pageSizeSelect = '1';
        select.dataset.propertySearchable = 'false';
        select.dataset.pageSizeEnhanced = '1';
        if (total) {
            select.dataset.pageSizeTotal = String(total);
        }
        const requested = new URLSearchParams(window.location.search).get(select.name) || select.value;
        const steps = pageSizeSteps(total);
        const requestedNum = parseCount(requested);
        if (requestedNum && !steps.includes(requestedNum) && (!total || requestedNum < total)) {
            steps.push(requestedNum);
            steps.sort((a, b) => a - b);
        }

        const previous = String(requested || select.value || '');
        select.innerHTML = '';
        steps.forEach((size) => {
            const option = document.createElement('option');
            option.value = String(size);
            option.textContent = `${size.toLocaleString()} / page`;
            select.appendChild(option);
        });
        const all = document.createElement('option');
        all.value = 'all';
        all.textContent = total ? `All (${total.toLocaleString()})` : 'All';
        select.appendChild(all);

        if (previous.toLowerCase() === 'all') {
            select.value = 'all';
        } else if (Array.from(select.options).some((option) => option.value === previous)) {
            select.value = previous;
        }

        bindCustomPageSize(select);
        const custom = select.parentElement?.querySelector('[data-page-size-custom]');
        if (custom instanceof HTMLInputElement && requestedNum && !pageSizeSteps(total).includes(requestedNum)) {
            custom.value = String(requestedNum);
        }
    });
}

export function wireAutoFilterForms(scopeRoot) {
    const root = scopeRoot || document;
    enhancePageSizeSelects(root);

    if (isPropertyWorkspaceHydrating()) {
        return;
    }
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
                // Prefer listings-style live row filter; do not auto-submit on type.
                if (input.dataset.serverSearch === '1' || input.dataset.serverSearch === 'true') {
                    input.dataset.serverSearch = 'false';
                }
                if (!input.hasAttribute('data-live-row-filter')) {
                    input.dataset.liveRowFilter = '1';
                }
                input.addEventListener('input', () => {
                    handleSearchInput(input);
                });
                input.addEventListener('focus', () => {
                    trackSearchFocus(input);
                });
                applyLiveWorkspaceSearch(input);
            });

        if (formAllowsAutoApply(form)) {
            formFilterControls(form)
                .filter((control) => isAutoApplyControl(control))
                .forEach((control) => {
                    control.addEventListener('change', () => {
                        scheduleControlApply(form);
                    });
                });
        }

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
    uniquifyFilterFieldIds(document);

    document.querySelectorAll('[data-property-filter-toolbar] form').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const visible = Array.from(form.elements).some((el) => {
            return el instanceof HTMLElement && el.type !== 'hidden' && filterControlIsVisible(el);
        });

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

            if (!visible) {
                if (!el.disabled) {
                    el.disabled = true;
                    el.dataset.filterVisibilityDisabled = '1';
                }
                return;
            }

            if (el.dataset.filterVisibilityDisabled === '1') {
                el.disabled = false;
                delete el.dataset.filterVisibilityDisabled;
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
        if (searchInput instanceof HTMLInputElement) {
            queueMicrotask(() => applyLiveWorkspaceSearch(searchInput));
        }
    });
}

function bindFilterLifecycle() {
    const run = (scope) => {
        wireAutoFilterForms(scope || document);
        syncPropertyFilterDesktopForms();
        restoreSubmittedFilterSearch(scope || document);
    };

    document.addEventListener('input', (event) => {
        rememberFilterSearchInput(event);
        const el = event.target;
        if (!(el instanceof HTMLInputElement) || !isSearchInput(el) || el.matches('[data-auto-submit="off"]')) {
            return;
        }
        handleSearchInput(el);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') {
            return;
        }
        if (!form.closest('[data-property-filter-toolbar]')) {
            return;
        }
        stampFilterSearchOnSubmit(form);
    }, true);

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!(link instanceof HTMLAnchorElement) || !link.closest('[data-property-filter-toolbar]')) {
            return;
        }
        try {
            const url = new URL(link.href, window.location.href);
            if ((url.searchParams.get('q') || '') === '') {
                lastFilterSearchValue = '';
                pendingFilterSubmit = '';
                filterSearchResubmitted = false;
            }
        } catch {
            // ignore malformed reset links
        }
    }, true);

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
