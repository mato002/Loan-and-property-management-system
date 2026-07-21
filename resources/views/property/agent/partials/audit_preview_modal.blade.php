@php
    $previewPayload = $previewPayload ?? [];
@endphp

<div
    id="audit-preview-root"
    x-data="{
        open: false,
        preview: {},
        payload: @js($previewPayload),
        openPreview(id) {
            const row = this.payload[String(id)] || this.payload[id];
            if (!row) return;
            this.preview = row;
            this.open = true;
        },
        close() {
            this.open = false;
        },
        reversalText() {
            if (this.preview.reversal_of) {
                return `This batch reverses ${this.preview.reversal_of}.`;
            }
            if ((this.preview.reversal_count || 0) > 0) {
                return `This batch has ${this.preview.reversal_count} linked reversal batch(es).`;
            }
            return 'No reversal linkage found for this batch.';
        }
    }"
>
    <x-property.modal
        show="open"
        close="close()"
        name="audit-preview"
        title="Audit quick preview"
        max-width="2xl"
    >
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div><span class="text-slate-500">Batch</span><div class="font-medium text-slate-900 dark:text-slate-100" x-text="preview.batch || '—'"></div></div>
            <div><span class="text-slate-500">Date</span><div class="font-medium text-slate-900 dark:text-slate-100" x-text="preview.date || '—'"></div></div>
            <div><span class="text-slate-500">Action</span><div class="font-medium text-slate-900 dark:text-slate-100" x-text="preview.action || '—'"></div></div>
            <div><span class="text-slate-500">Entity</span><div class="font-medium text-slate-900 dark:text-slate-100" x-text="preview.entity || '—'"></div></div>
            <div><span class="text-slate-500">Reference</span><div class="font-medium text-slate-900 dark:text-slate-100" x-text="preview.reference || '—'"></div></div>
            <div><span class="text-slate-500">Status</span><div class="font-medium text-slate-900 dark:text-slate-100" x-text="preview.status || '—'"></div></div>
        </div>
        <div class="mt-3"><span class="text-slate-500">Financial impact</span><div class="font-medium text-slate-900 dark:text-slate-100 mt-1" x-text="preview.impact || '—'"></div></div>
        <div class="mt-3"><span class="text-slate-500">Description</span><div class="text-slate-700 dark:text-slate-300 mt-1" x-text="preview.description || '—'"></div></div>
        <div class="mt-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-3 py-2 text-slate-700 dark:text-slate-300" x-text="reversalText()"></div>
    </x-property.modal>
</div>

<script>
    (function () {
        if (window.__auditPreviewModalBound) {
            return;
        }
        window.__auditPreviewModalBound = true;

        const bindPreviewButtons = (root) => {
            root.querySelectorAll('[data-audit-preview-id]').forEach((btn) => {
                if (btn.dataset.auditPreviewBound === '1') {
                    return;
                }
                btn.dataset.auditPreviewBound = '1';
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const alpineRoot = document.getElementById('audit-preview-root');
                    const state = window.Alpine?.$data(alpineRoot);
                    state?.openPreview(btn.getAttribute('data-audit-preview-id'));
                });
            });
        };

        const init = () => bindPreviewButtons(document);
        document.addEventListener('DOMContentLoaded', init);
        document.addEventListener('turbo:load', init);
        document.addEventListener('turbo:frame-load', (event) => {
            if (event.target instanceof Element) {
                bindPreviewButtons(event.target);
            }
        });
    })();
</script>
