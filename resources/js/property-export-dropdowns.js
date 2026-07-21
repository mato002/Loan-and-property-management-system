/**
 * Collapse multiple export links/buttons in GET filter forms into one dropdown.
 * Used by property layout and workspace toolbars.
 */

export function wireExportDropdowns(scopeRoot) {
    const root = scopeRoot instanceof Element ? scopeRoot : document;
    const forms = Array.from(root.querySelectorAll('form[method="get"]'));

    forms.forEach((form) => {
        if (form.dataset.exportDropdownBound === '1') {
            return;
        }
        form.dataset.exportDropdownBound = '1';

        if (form.querySelector('[data-export-dropdown-auto]')) {
            return;
        }

        if (form.querySelector('select[onchange*="window.location.href"]')) {
            return;
        }

        const exportLinks = Array.from(form.querySelectorAll('a[href*="export="]'));
        const exportButtons = Array.from(
            form.querySelectorAll('button[name="export"][value], input[type="submit"][name="export"][value]'),
        );
        const exportItems = [];

        exportLinks.forEach((link) => {
            const href = link.getAttribute('href') || '';
            if (!href) {
                return;
            }
            exportItems.push({
                label: (link.textContent || '').trim().replace(/^Export\s+/i, '') || 'File',
                mode: 'link',
                value: href,
                node: link,
            });
        });

        exportButtons.forEach((button) => {
            const value = button.getAttribute('value') || '';
            if (!value) {
                return;
            }
            exportItems.push({
                label: (button.textContent || button.getAttribute('value') || '')
                    .trim()
                    .replace(/^Export\s+/i, '') || String(value).toUpperCase(),
                mode: 'submit',
                value,
                node: button,
            });
        });

        if (exportItems.length < 2) {
            return;
        }

        const insertBeforeNode = exportItems[0].node;
        const dropdown = document.createElement('select');
        dropdown.setAttribute('data-export-dropdown-auto', '1');
        dropdown.className =
            'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 max-w-full';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Export';
        dropdown.appendChild(placeholder);

        exportItems.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;
            option.dataset.mode = item.mode;
            dropdown.appendChild(option);
        });

        dropdown.addEventListener('change', () => {
            const selected = dropdown.options[dropdown.selectedIndex];
            if (!selected || !selected.value) {
                return;
            }

            if (selected.dataset.mode === 'submit') {
                const url = new URL(form.action || window.location.href, window.location.href);
                const params = new URLSearchParams(new FormData(form));
                params.set('export', selected.value);
                url.search = params.toString();
                window.location.href = url.toString();
            } else {
                window.location.href = selected.value;
            }

            dropdown.selectedIndex = 0;
        });

        insertBeforeNode.parentNode?.insertBefore(dropdown, insertBeforeNode);
        exportItems.forEach((item) => item.node.remove());
    });
}

function bindExportDropdownListeners() {
    const run = (event) => {
        const target = event?.target;
        const scope =
            target instanceof Element && target.id === 'property-main'
                ? target
                : document.getElementById('property-main') || document;
        wireExportDropdowns(scope);
    };

    document.addEventListener('DOMContentLoaded', run);
    document.addEventListener('turbo:load', run);
    document.addEventListener('turbo:frame-load', run);
    document.addEventListener('livewire:navigated', run);
    document.addEventListener('alpine:navigated', run);
}

bindExportDropdownListeners();
