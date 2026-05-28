/**
 * Utility billing operations workspace — tabs, bulk readings, penalty preview.
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('utilityOperations', (config = {}) => ({
        activeTab: config.initialTab || 'overview',
        penaltyModalOpen: false,
        penaltyLoading: false,
        penaltyRows: [],
        penaltyTotal: 0,
        penaltyTotalDisplay: '',
        penaltyError: null,
        bulkFilter: '',
        bulkFilledCount: 0,
        previewUrl: config.penaltyPreviewUrl || '',

        setTab(tab) {
            this.activeTab = tab;
            try {
                sessionStorage.setItem('utility_ops_tab', tab);
            } catch {
                // ignore
            }
        },

        init() {
            try {
                const saved = sessionStorage.getItem('utility_ops_tab');
                if (saved && ['overview', 'readings', 'billing', 'charges'].includes(saved)) {
                    this.activeTab = saved;
                }
            } catch {
                // ignore
            }
            this.$nextTick(() => this.updateBulkFilledCount());
        },

        updateBulkFilledCount() {
            const root = this.$refs.bulkReadingsRoot;
            if (!root) {
                this.bulkFilledCount = 0;
                return;
            }
            const inputs = root.querySelectorAll('[data-bulk-current]');
            let n = 0;
            inputs.forEach((el) => {
                if (el instanceof HTMLInputElement && el.value !== '' && Number(el.value) >= 0) {
                    n++;
                }
            });
            this.bulkFilledCount = n;
        },

        bulkRowVisible(label) {
            const q = String(this.bulkFilter || '').trim().toLowerCase();
            if (!q) return true;
            return String(label || '').toLowerCase().includes(q);
        },

        async openPenaltyPreview() {
            if (!this.previewUrl) return;
            this.penaltyModalOpen = true;
            this.penaltyLoading = true;
            this.penaltyError = null;
            this.penaltyRows = [];
            this.penaltyTotal = 0;
            try {
                const res = await fetch(this.previewUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('Preview request failed');
                const data = await res.json();
                this.penaltyRows = Array.isArray(data.rows) ? data.rows : [];
                this.penaltyTotal = Number(data.total_penalty || 0);
                this.penaltyTotalDisplay = String(data.total_penalty_display || '');
            } catch (e) {
                this.penaltyError = e?.message || 'Could not load penalty preview.';
            } finally {
                this.penaltyLoading = false;
            }
        },

        closePenaltyModal() {
            this.penaltyModalOpen = false;
        },
    }));
});
