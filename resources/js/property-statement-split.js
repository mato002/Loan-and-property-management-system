(() => {
    let transactionCents = 0;
    let nextRow = 0;

    const money = (cents) => `KES ${(cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const getParts = (element) => {
        const modal = element?.closest?.('[data-statement-split-modal]')
            || document.querySelector('[data-statement-split-modal]');
        if (!modal) return null;
        return {
            modal,
            form: modal.querySelector('[data-statement-split]'),
            rows: modal.querySelector('[data-split-rows]'),
            totalLabel: modal.querySelector('[data-split-total-label]'),
            referenceLabel: modal.querySelector('[data-split-reference-label]'),
            status: modal.querySelector('[data-split-total-status]'),
            submit: modal.querySelector('[data-submit-statement-split]'),
        };
    };

    const close = (modal) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
    };

    const validateTotals = (parts) => {
        if (!parts?.rows || !parts.status || !parts.submit) return;
        const recipientRows = [...parts.rows.querySelectorAll('[data-statement-split-row]')];
        const selected = recipientRows.map((row) => ({
            tenantId: row.querySelector('[data-split-tenant-id]')?.value || '',
            cents: Math.round((Number(row.querySelector('[data-split-amount]')?.value) || 0) * 100),
        }));
        const assignedCents = selected.reduce((sum, item) => sum + item.cents, 0);
        const allTenantsSelected = selected.every((item) => item.tenantId !== '');
        const distinctTenants = new Set(selected.map((item) => item.tenantId)).size === selected.length;
        const allPositive = selected.every((item) => item.cents > 0);
        const matches = assignedCents === transactionCents;
        const remaining = transactionCents - assignedCents;

        parts.status.textContent = matches
            ? `Fully allocated: ${money(assignedCents)}`
            : remaining > 0
                ? `${money(assignedCents)} allocated · ${money(remaining)} will remain unmatched`
                : `${money(Math.abs(remaining))} over the available amount`;
        parts.status.classList.toggle('text-emerald-700', matches);
        parts.status.classList.toggle('text-amber-700', !matches);
        parts.submit.disabled = recipientRows.length < 2 || !allTenantsSelected || !distinctTenants || !allPositive || assignedCents <= 0 || remaining < 0;
    };

    const addRecipient = (parts) => {
        if (!parts?.rows || !parts.form) return;
        const index = nextRow++;
        const row = document.createElement('div');
        row.setAttribute('data-statement-split-row', '1');
        row.className = 'grid grid-cols-1 items-start gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto]';
        row.innerHTML = `
            <div class="relative">
                <input type="hidden" name="allocations[${index}][tenant_id]" data-split-tenant-id>
                <input type="text" data-tenant-query data-search-url="${parts.form.dataset.searchUrl}" autocomplete="off" placeholder="Tenant, account, or phone" required class="h-10 w-full rounded-lg border border-slate-300 px-3 pr-8 text-sm focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                <span data-search-spinner class="pointer-events-none absolute right-2 top-3 hidden text-slate-400" aria-hidden="true">…</span>
            </div>
            <label class="text-xs font-semibold text-slate-600">Amount
                <input type="number" name="allocations[${index}][amount]" data-split-amount min="0.01" step="0.01" inputmode="decimal" required class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums">
            </label>
            <button type="button" data-remove-split-recipient class="mt-1 rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Remove</button>
        `;
        parts.rows.appendChild(row);
        validateTotals(parts);
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const openButton = target.closest('[data-open-statement-split]');
        if (openButton) {
            const parts = getParts(openButton);
            if (!parts?.form || !parts.rows) return;
            transactionCents = Math.round(Number(openButton.dataset.splitAmount || 0) * 100);
            parts.form.action = openButton.dataset.splitUrl;
            parts.form.dataset.hasExistingSplit = openButton.dataset.splitExisting || '0';
            parts.referenceLabel.textContent = openButton.dataset.splitReference || 'Bank transaction';
            parts.totalLabel.textContent = money(transactionCents);
            parts.rows.replaceChildren();
            nextRow = 0;
            addRecipient(parts);
            addRecipient(parts);
            parts.modal.classList.remove('hidden');
            parts.modal.classList.add('flex');
            parts.modal.setAttribute('aria-hidden', 'false');
            parts.rows.querySelector('[data-tenant-query]')?.focus();
            return;
        }

        const modal = target.closest('[data-statement-split-modal]');
        if (modal && (target.closest('[data-close-statement-split]') || target === modal)) {
            close(modal);
            return;
        }
        if (!modal) return;

        const parts = getParts(modal);
        if (target.closest('[data-add-split-recipient]')) {
            addRecipient(parts);
            return;
        }
        const remove = target.closest('[data-remove-split-recipient]');
        const minRows = parts.form?.dataset.hasExistingSplit === '1' ? 1 : 2;
        if (remove && parts.rows.querySelectorAll('[data-statement-split-row]').length > minRows) {
            remove.closest('[data-statement-split-row]')?.remove();
            validateTotals(parts);
        }
    });

    document.addEventListener('input', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-statement-split]')) {
            validateTotals(getParts(event.target));
        }
    });

    document.addEventListener('tenant:selected', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-statement-split]')) {
            validateTotals(getParts(event.target));
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const modal = document.querySelector('[data-statement-split-modal][aria-hidden="false"]');
        if (modal) close(modal);
    });

    document.addEventListener('submit', (event) => {
        if (!(event.target instanceof HTMLFormElement) || !event.target.matches('[data-statement-split]')) return;
        const parts = getParts(event.target);
        validateTotals(parts);
        if (parts?.submit?.disabled) event.preventDefault();
    });
})();
