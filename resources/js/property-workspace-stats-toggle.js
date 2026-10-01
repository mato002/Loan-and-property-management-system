/**
 * Toggle workspace summary stat cards.
 * Pages can set data-default-visible="0" so the cards stay closed until opened.
 */

const DEFAULT_STORAGE_KEY = 'property.workspace.summaryStatsVisible';

function readStatsVisible(storageKey, defaultVisible) {
    try {
        const stored = localStorage.getItem(storageKey);
        if (stored === null) {
            return defaultVisible;
        }

        return stored !== '0';
    } catch {
        return defaultVisible;
    }
}

function writeStatsVisible(storageKey, visible) {
    try {
        localStorage.setItem(storageKey, visible ? '1' : '0');
    } catch {
        // ignore storage failures
    }
}

function applyStatsVisibility(panel, visible) {
    const toggle = panel.querySelector('[data-property-workspace-stats-toggle]');
    const body = panel.querySelector('[data-property-workspace-stats-body]');
    const label = panel.querySelector('[data-property-workspace-stats-toggle-label]');

    if (!(body instanceof HTMLElement)) {
        return;
    }

    body.classList.toggle('hidden', !visible);

    if (toggle instanceof HTMLElement) {
        toggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
    }

    if (label instanceof HTMLElement) {
        label.textContent = visible ? 'Hide' : 'Show';
    }
}

function bindStatsPanel(panel) {
    if (!(panel instanceof HTMLElement) || panel.dataset.statsToggleBound === '1') {
        return;
    }

    const toggle = panel.querySelector('[data-property-workspace-stats-toggle]');
    const body = panel.querySelector('[data-property-workspace-stats-body]');

    if (!(toggle instanceof HTMLElement) || !(body instanceof HTMLElement)) {
        return;
    }

    panel.dataset.statsToggleBound = '1';

    const storageKey = panel.dataset.storageKey || DEFAULT_STORAGE_KEY;
    const defaultVisible = panel.dataset.defaultVisible !== '0';
    applyStatsVisibility(panel, readStatsVisible(storageKey, defaultVisible));

    toggle.addEventListener('click', () => {
        const nextVisible = body.classList.contains('hidden');
        applyStatsVisibility(panel, nextVisible);
        writeStatsVisible(storageKey, nextVisible);
    });
}

export function setupPropertyWorkspaceStatsToggle(root) {
    const scope = root instanceof Element ? root : document.getElementById('property-main');
    if (!(scope instanceof HTMLElement)) {
        return;
    }

    scope.querySelectorAll('[data-property-workspace-stats]').forEach(bindStatsPanel);
}
