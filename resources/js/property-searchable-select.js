/**
 * Progressive enhancement: turn native <select> pickers into searchable comboboxes
 * (same UX as x-property.quick-create-select), across property ERP forms and filters.
 *
 * Opt out: data-searchable="false" or data-property-searchable="false"
 * Force on: data-searchable="true" or data-property-searchable="true"
 */

const ENHANCED_ATTR = 'data-property-searchable-enhanced';
const ROOT_ATTR = 'data-property-searchable-root';

const ENTITY_NAME_RE = /(tenant|lease|unit|propert|landlord|vendor|employee|client|product|branch|account|agent|officer|user|invoice_type|charge|payer|payee|owner|guarantor|region|warehouse|item)/i;

function optionCount(select) {
    return select?.options?.length ?? 0;
}

function shouldEnhance(select) {
    if (!(select instanceof HTMLSelectElement)) {
        return false;
    }
    if (select.getAttribute(ENHANCED_ATTR) === '1') {
        return false;
    }
    if (select.multiple || (select.size && select.size > 1)) {
        return false;
    }
    if (select.disabled && select.options.length <= 1) {
        return false;
    }
    if (select.classList.contains('sr-only') || select.getAttribute('aria-hidden') === 'true') {
        return false;
    }
    if (select.closest(`[${ROOT_ATTR}]`)) {
        return false;
    }
    if (select.closest('[x-data*="propertyQuickCreateSelect"]')) {
        return false;
    }
    if (select.closest('.property-print-only')) {
        return false;
    }

    const flag = (select.dataset.propertySearchable || select.dataset.searchable || '').toLowerCase();
    if (flag === 'false' || flag === '0' || flag === 'off') {
        return false;
    }
    if (flag === 'true' || flag === '1' || flag === 'on') {
        return true;
    }

    const name = `${select.name || ''} ${select.id || ''}`.trim();
    const count = optionCount(select);
    if (ENTITY_NAME_RE.test(name) && count >= 2) {
        return true;
    }
    if (count >= 4) {
        return true;
    }

    return false;
}

function readOptions(select) {
    return Array.from(select.options).map((opt) => ({
        value: String(opt.value ?? ''),
        label: String(opt.textContent ?? '').trim(),
        search: String(opt.textContent ?? '').trim().toLowerCase(),
        disabled: Boolean(opt.disabled),
        selected: Boolean(opt.selected),
    }));
}

function selectedLabel(options, value, placeholder) {
    if (value === '' || value === null || value === undefined) {
        return placeholder;
    }
    const match = options.find((o) => String(o.value) === String(value));

    return match ? match.label : placeholder;
}

function buildEnhancement(select) {
    const options = readOptions(select);
    const placeholderOption = options.find((o) => o.value === '');
    const placeholder = placeholderOption?.label || 'Select…';
    let currentValue = String(select.value ?? '');

    const root = document.createElement('div');
    root.setAttribute(ROOT_ATTR, '1');
    root.className = 'relative min-w-0 w-full';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = [
        'property-searchable-select__trigger',
        'flex w-full items-center justify-between gap-2',
        'rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-slate-900',
        'dark:border-slate-600 dark:bg-gray-900 dark:text-slate-100',
        'min-h-[38px]',
    ].join(' ');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const labelEl = document.createElement('span');
    labelEl.className = 'truncate';
    labelEl.textContent = selectedLabel(options, currentValue, placeholder);

    const chevron = document.createElement('i');
    chevron.className = 'fa-solid fa-chevron-down shrink-0 text-xs text-slate-400';
    chevron.setAttribute('aria-hidden', 'true');

    trigger.appendChild(labelEl);
    trigger.appendChild(chevron);

    const panel = document.createElement('div');
    panel.className = [
        'property-searchable-select__panel',
        'absolute z-[80] mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg',
        'dark:border-slate-600 dark:bg-gray-900',
        'hidden',
    ].join(' ');

    const searchWrap = document.createElement('div');
    searchWrap.className = 'border-b border-slate-100 p-2 dark:border-slate-700';

    const searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.autocomplete = 'off';
    searchInput.placeholder = 'Search…';
    searchInput.className = 'w-full rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm dark:border-slate-600 dark:bg-gray-950';

    searchWrap.appendChild(searchInput);

    const list = document.createElement('ul');
    list.className = 'max-h-56 overflow-y-auto py-1 text-sm';
    list.setAttribute('role', 'listbox');

    panel.appendChild(searchWrap);
    panel.appendChild(list);

    const parent = select.parentNode;
    if (!parent) {
        return;
    }

    parent.insertBefore(root, select);
    root.appendChild(trigger);
    root.appendChild(panel);
    root.appendChild(select);

    select.classList.add('sr-only');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.setAttribute(ENHANCED_ATTR, '1');

    let open = false;

    const renderList = (query = '') => {
        const q = String(query || '').trim().toLowerCase();
        const filtered = !q
            ? options
            : options.filter((opt) => opt.search.includes(q));

        list.replaceChildren();

        if (filtered.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'px-3 py-2 text-slate-500';
            empty.textContent = 'No matches';
            list.appendChild(empty);

            return;
        }

        filtered.forEach((opt) => {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = [
                'flex w-full px-3 py-2 text-left hover:bg-emerald-50 dark:hover:bg-emerald-950/40',
                String(opt.value) === String(currentValue)
                    ? 'bg-emerald-50 font-semibold text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100'
                    : 'text-slate-800 dark:text-slate-100',
                opt.disabled ? 'opacity-50 cursor-not-allowed' : '',
            ].join(' ');
            btn.textContent = opt.label || (opt.value === '' ? placeholder : opt.value);
            btn.disabled = Boolean(opt.disabled);
            btn.addEventListener('click', () => {
                if (opt.disabled) {
                    return;
                }
                currentValue = String(opt.value);
                if (select.value !== currentValue) {
                    select.value = currentValue;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                }
                labelEl.textContent = selectedLabel(options, currentValue, placeholder);
                closePanel();
            });
            li.appendChild(btn);
            list.appendChild(li);
        });
    };

    const openPanel = () => {
        open = true;
        panel.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        searchInput.value = '';
        renderList('');
        queueMicrotask(() => searchInput.focus());
    };

    const closePanel = () => {
        open = false;
        panel.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
        searchInput.value = '';
    };

    const togglePanel = () => {
        if (open) {
            closePanel();
        } else {
            openPanel();
        }
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        togglePanel();
    });

    searchInput.addEventListener('input', () => {
        renderList(searchInput.value);
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closePanel();
            trigger.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!open) {
            return;
        }
        if (event.target instanceof Node && !root.contains(event.target)) {
            closePanel();
        }
    });

    select.addEventListener('change', () => {
        currentValue = String(select.value ?? '');
        labelEl.textContent = selectedLabel(options, currentValue, placeholder);
        // Refresh option cache if options were mutated externally
        const refreshed = readOptions(select);
        options.splice(0, options.length, ...refreshed);
    });

    // Keep label in sync if options are rewritten (AJAX append, etc.)
    const mo = new MutationObserver(() => {
        const refreshed = readOptions(select);
        options.splice(0, options.length, ...refreshed);
        currentValue = String(select.value ?? '');
        labelEl.textContent = selectedLabel(options, currentValue, placeholder);
        if (open) {
            renderList(searchInput.value);
        }
    });
    mo.observe(select, { childList: true, subtree: true, attributes: true });
}

function enhanceSelects(root = document) {
    const scope = root instanceof Element || root instanceof Document ? root : document;
    const selects = scope.querySelectorAll('select');
    selects.forEach((select) => {
        if (shouldEnhance(select)) {
            try {
                buildEnhancement(select);
            } catch {
                // leave native select usable
            }
        }
    });
}

function bindSearchableSelectEnhancer() {
    const run = (target) => enhanceSelects(target || document);

    run(document);

    document.addEventListener('turbo:load', () => run(document));
    document.addEventListener('turbo:frame-load', (event) => {
        run(event.target instanceof Element ? event.target : document);
    });
    document.addEventListener('turbo:render', () => run(document));

    // Modals / dynamically injected forms
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) {
                    return;
                }
                if (node.matches?.('select') || node.querySelector?.('select')) {
                    run(node);
                }
            });
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindSearchableSelectEnhancer, { once: true });
} else {
    bindSearchableSelectEnhancer();
}

export { enhanceSelects, shouldEnhance };
