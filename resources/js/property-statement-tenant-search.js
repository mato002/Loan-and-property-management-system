const states = new WeakMap();

function closeSuggestions(state) {
    state.menu?.remove();
    state.menu = null;
}

function renderSuggestions(input, state, items) {
    closeSuggestions(state);
    if (!items.length) return;

    const menu = document.createElement('div');
    menu.setAttribute('data-tenant-menu', '1');
    menu.className = 'fixed z-[80] max-h-60 w-72 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg';
    const rect = input.getBoundingClientRect();
    menu.style.top = `${rect.bottom + 4}px`;
    menu.style.left = `${Math.max(8, rect.right - 288)}px`;

    items.forEach((tenant) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'block w-full px-3 py-1.5 text-left text-xs text-slate-800 hover:bg-emerald-50';
        option.textContent = tenant.label || tenant.name || '';
        option.addEventListener('mousedown', (event) => {
            event.preventDefault();
            const form = input.closest('[data-statement-assign]');
            const hidden = form?.querySelector('[name="tenant_id"]');
            if (!hidden) return;
            hidden.value = String(tenant.id);
            input.value = option.textContent;
            input.setCustomValidity('');
            closeSuggestions(state);
        });
        menu.appendChild(option);
    });

    document.body.appendChild(menu);
    state.menu = menu;
}

async function search(input, state) {
    const query = input.value.trim();
    if (query.length < 2) {
        closeSuggestions(state);
        return;
    }

    state.controller?.abort();
    state.controller = new AbortController();
    const requestController = state.controller;
    const spinner = input.closest('[data-statement-assign]')?.querySelector('[data-search-spinner]');
    spinner?.classList.remove('hidden');

    try {
        const url = new URL(input.dataset.searchUrl, window.location.href);
        url.searchParams.set('q', query);
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: requestController.signal,
        });
        if (!response.ok) throw new Error(`Tenant search failed (${response.status})`);
        const payload = await response.json();
        if (states.get(input) !== state || input.value.trim() !== query) return;
        renderSuggestions(input, state, Array.isArray(payload.items) ? payload.items : []);
    } catch (error) {
        if (error.name !== 'AbortError') closeSuggestions(state);
    } finally {
        if (state.controller === requestController) {
            spinner?.classList.add('hidden');
        }
    }
}

document.addEventListener('input', (event) => {
    const input = event.target.closest?.('[data-statement-assign] [data-tenant-query]');
    if (!input) return;

    const form = input.closest('[data-statement-assign]');
    const hidden = form?.querySelector('[name="tenant_id"]');
    if (!hidden) return;
    hidden.value = '';
    input.setCustomValidity('');

    const state = states.get(input) || { timer: null, controller: null, menu: null };
    states.set(input, state);
    clearTimeout(state.timer);
    state.timer = setTimeout(() => search(input, state), 180);
});

document.addEventListener('focusin', (event) => {
    const input = event.target.closest?.('[data-statement-assign] [data-tenant-query]');
    if (input && input.value.trim().length >= 2) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
});

document.addEventListener('keydown', (event) => {
    const input = event.target.closest?.('[data-statement-assign] [data-tenant-query]');
    if (!input) return;
    const state = states.get(input);
    if (event.key === 'Escape' && state) closeSuggestions(state);
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest?.('[data-statement-assign]');
    if (!form) return;
    const input = form.querySelector('[data-tenant-query]');
    const hidden = form.querySelector('[name="tenant_id"]');
    if (!input || !hidden || !hidden.value) {
        event.preventDefault();
        input?.setCustomValidity('Choose a tenant from the suggestions.');
        input?.reportValidity();
    }
});

document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-tenant-menu]')) return;
    document.querySelectorAll('[data-statement-assign] [data-tenant-query]').forEach((input) => {
        const state = states.get(input);
        if (state) closeSuggestions(state);
    });
});
