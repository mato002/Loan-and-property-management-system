/**
 * In-page workspace tabs for loan command centers (collections reports, etc.).
 */

function readStoredTab(storageKey, validTabs, fallback) {
    if (!storageKey || typeof window === 'undefined') {
        return fallback;
    }

    try {
        const stored = window.localStorage.getItem(storageKey);
        if (stored && Array.isArray(validTabs) && validTabs.includes(stored)) {
            return stored;
        }
    } catch {
        // ignore private mode / quota
    }

    return fallback;
}

export function loanWorkspacePanels(config = {}) {
    const initial = readStoredTab(config.storageKey, config.validTabs, config.initialTab || 'overview');

    return {
        activeTab: initial,
        validTabs: Array.isArray(config.validTabs) ? config.validTabs : [],
        mountedTabs: new Set([initial]),
        setTab(key) {
            if (!this.validTabs.includes(key)) {
                return;
            }
            this.activeTab = key;
            this.mountedTabs.add(key);
            if (config.storageKey) {
                try {
                    window.localStorage.setItem(config.storageKey, key);
                } catch {
                    // ignore
                }
            }
        },
        shouldRender(key) {
            return this.mountedTabs.has(key);
        },
    };
}

export function loanWorkspaceHeader(config = {}) {
    return {
        focusMode: 'default',
        searchQuery: '',
        applyFocusMode() {
            // Reserved for future focus-mode filtering.
        },
        runFirstMatch() {
            const query = (this.searchQuery || '').trim().toLowerCase();
            if (!query) {
                return;
            }

            const commands = Array.isArray(config.searchCommands) ? config.searchCommands : [];
            const match = commands.find((command) => {
                const label = String(command.label || '').toLowerCase();
                const keywords = Array.isArray(command.keywords) ? command.keywords : [];
                if (label.includes(query)) {
                    return true;
                }

                return keywords.some((keyword) => String(keyword).toLowerCase().includes(query));
            });

            if (match?.href) {
                if (typeof window.visitLoanMain === 'function') {
                    window.visitLoanMain(match.href);
                } else {
                    window.location.href = match.href;
                }
            }
        },
    };
}

export function registerLoanWorkspaceAlpine(Alpine) {
    Alpine.data('loanWorkspacePanels', loanWorkspacePanels);
    Alpine.data('loanWorkspaceHeader', loanWorkspaceHeader);
}
