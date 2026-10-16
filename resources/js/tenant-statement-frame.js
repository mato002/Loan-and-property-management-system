function resizeTenantStatementFrame(frame) {
    try {
        const doc = frame.contentDocument;
        if (!doc?.body || !doc.documentElement) return;

        const height = Math.max(
            520,
            doc.documentElement.scrollHeight,
            doc.body.scrollHeight,
        );
        frame.style.height = `${height}px`;
    } catch {
        // Keep the standard frame height if the embedded page is unavailable.
    }
}

function observeTenantStatementFrame(frame) {
    if (frame.dataset.statementResizeBound === 'true') return;
    frame.dataset.statementResizeBound = 'true';

    let observer = null;
    const sync = () => requestAnimationFrame(() => resizeTenantStatementFrame(frame));
    const connectObserver = () => {
        try {
            const doc = frame.contentDocument;
            if (doc?.body && 'ResizeObserver' in window) {
                observer = new ResizeObserver(sync);
                observer.observe(doc.body);
                observer.observe(doc.documentElement);
            }
        } catch {
            // Cross-origin or unloaded frames retain their minimum height.
        }
    };

    frame.addEventListener('load', () => {
        observer?.disconnect();
        sync();
        connectObserver();
    });

    resizeTenantStatementFrame(frame);
    if (frame.contentDocument?.readyState === 'complete') connectObserver();
}

function observeTenantStatementFrames() {
    document.querySelectorAll('[data-tenant-statement-frame]').forEach(observeTenantStatementFrame);
}

document.addEventListener('DOMContentLoaded', observeTenantStatementFrames);
document.addEventListener('turbo:load', observeTenantStatementFrames);
document.addEventListener('turbo:frame-load', observeTenantStatementFrames);
observeTenantStatementFrames();
