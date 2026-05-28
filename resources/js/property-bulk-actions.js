/**
 * Workspace bulk selection: select-all, indeterminate state, count, visibility, Turbo-safe reinit.
 */

const BAR_SELECTOR = '[data-property-bulk-bar]';
const DEFAULT_ROW_SELECTOR = '.property-bulk-row-checkbox';

function bulkScopeRoot(bar) {
    const frame = bar.closest('#property-main') || document.getElementById('property-main');
    return frame || document;
}

function isRowVisible(row) {
    if (!row) {
        return true;
    }
    if (row.hasAttribute('hidden')) {
        return false;
    }
    const style = window.getComputedStyle(row);
    return style.display !== 'none' && style.visibility !== 'hidden';
}

function getRowCheckboxes(bar) {
    const root = bulkScopeRoot(bar);
    const selector = bar.dataset.bulkCheckboxSelector || DEFAULT_ROW_SELECTOR;
    return Array.from(root.querySelectorAll(selector)).filter((checkbox) => {
        const row = checkbox.closest('tr, article[data-filter-text], [data-mobile-record]');
        return isRowVisible(row);
    });
}

function syncDocumentBulkPadding() {
    const anyVisible = document.querySelector(`${BAR_SELECTOR}[data-bulk-visible="1"]`);
    document.documentElement.classList.toggle('property-bulk-active', Boolean(anyVisible));
}

function setBarLoading(bar, loading) {
    const applyBtn = bar.querySelector('[data-bulk-apply]');
    const label = bar.querySelector('[data-bulk-apply-label]');
    const spinner = bar.querySelector('[data-bulk-apply-loading]');
    if (!applyBtn) {
        return;
    }
    const checkedCount = getRowCheckboxes(bar).filter((c) => c.checked).length;
    applyBtn.disabled = loading || checkedCount === 0;
    if (loading) {
        applyBtn.setAttribute('aria-busy', 'true');
    } else {
        applyBtn.removeAttribute('aria-busy');
    }
    label?.classList.toggle('hidden', loading);
    spinner?.classList.toggle('hidden', !loading);
}

function resetBarLoading(bar) {
    setBarLoading(bar, false);
}

function updateBulkBar(bar) {
    const boxes = getRowCheckboxes(bar);
    const checked = boxes.filter((b) => b.checked);
    const count = checked.length;
    const countEl = bar.querySelector('[data-bulk-count]');
    if (countEl) {
        countEl.textContent = count === 1 ? '1 selected' : `${count} selected`;
    }

    const visible = bar.dataset.bulkShowWhenEmpty === '1' || count > 0;
    bar.toggleAttribute('hidden', !visible);
    bar.dataset.bulkVisible = visible ? '1' : '0';
    syncDocumentBulkPadding();

    const selectAll = bar.querySelector('[data-bulk-select-all]');
    if (selectAll) {
        if (boxes.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = count === boxes.length;
            selectAll.indeterminate = count > 0 && count < boxes.length;
        }
    }

    const applyBtn = bar.querySelector('[data-bulk-apply]');
    if (applyBtn && !applyBtn.hasAttribute('aria-busy')) {
        applyBtn.disabled = count === 0;
    } else if (applyBtn?.getAttribute('aria-busy') !== 'true') {
        applyBtn.disabled = count === 0;
    }

    const targetMode = document.getElementById('arrears-target-mode');
    if (targetMode && count > 0 && targetMode.value !== 'selected') {
        targetMode.value = 'selected';
        targetMode.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function bindBulkBar(bar) {
    if (bar.dataset.bulkBound === '1') {
        resetBarLoading(bar);
        updateBulkBar(bar);
        return;
    }
    bar.dataset.bulkBound = '1';

    const selectAll = bar.querySelector('[data-bulk-select-all]');
    const clearBtn = bar.querySelector('[data-bulk-clear]');
    const formId = bar.dataset.bulkFormId;
    const form = formId ? document.getElementById(formId) : bar.querySelector('form');

    selectAll?.addEventListener('change', () => {
        const on = selectAll.checked;
        getRowCheckboxes(bar).forEach((box) => {
            box.checked = on;
        });
        updateBulkBar(bar);
    });

    clearBtn?.addEventListener('click', () => {
        getRowCheckboxes(bar).forEach((box) => {
            box.checked = false;
        });
        updateBulkBar(bar);
    });

    const root = bulkScopeRoot(bar);
    root.addEventListener('change', (event) => {
        const selector = bar.dataset.bulkCheckboxSelector || DEFAULT_ROW_SELECTOR;
        if (!(event.target instanceof HTMLInputElement) || !event.target.matches(selector)) {
            return;
        }
        updateBulkBar(bar);
    });

    form?.addEventListener('submit', () => {
        setBarLoading(bar, true);
    });

    const syncFormId = bar.dataset.bulkSyncForm;
    const syncInputId = bar.dataset.bulkSyncInput;
    if (syncFormId && syncInputId) {
        const syncForm = document.getElementById(syncFormId);
        const syncInput = document.getElementById(syncInputId);
        syncForm?.addEventListener('submit', () => {
            if (!syncInput) {
                return;
            }
            const modeEl = document.getElementById('arrears-target-mode');
            if (modeEl?.value === 'all') {
                return;
            }
            const ids = getRowCheckboxes(bar)
                .filter((el) => el.checked)
                .map((el) => (el.value || '').toString().trim())
                .filter((v) => v !== '');
            syncInput.value = ids.join(',');
        });
    }

    updateBulkBar(bar);
}

export function setupPropertyBulkActions(event) {
    const target = event?.target;
    let scope = document;
    if (target instanceof Element) {
        if (target.id === 'property-main') {
            scope = target;
        } else {
            const frame = target.closest?.('#property-main') || target.querySelector?.('#property-main');
            if (frame) {
                scope = frame;
            } else {
                scope = document.getElementById('property-main') || document;
            }
        }
    } else {
        scope = document.getElementById('property-main') || document;
    }

    scope.querySelectorAll(BAR_SELECTOR).forEach(bindBulkBar);
}

function bindBulkListeners() {
    document.addEventListener('DOMContentLoaded', setupPropertyBulkActions);
    document.addEventListener('turbo:load', setupPropertyBulkActions);
    document.addEventListener('turbo:frame-load', setupPropertyBulkActions);
}

bindBulkListeners();
