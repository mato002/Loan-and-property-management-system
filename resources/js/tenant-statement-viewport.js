function fitTenantStatementFrames() {
    document.querySelectorAll('[data-tenant-statement-frame]').forEach((frame) => {
        const top = frame.getBoundingClientRect().top;
        const footer = document.getElementById('property-shell-footer');
        const footerHeight = footer?.getBoundingClientRect().height ?? 0;
        const available = Math.floor(window.innerHeight - top - footerHeight - 12);

        frame.style.height = `${Math.max(260, available)}px`;
    });
}

let fitScheduled = false;

function scheduleTenantStatementFit() {
    if (fitScheduled) return;
    fitScheduled = true;

    requestAnimationFrame(() => {
        fitScheduled = false;
        fitTenantStatementFrames();
    });
}

window.addEventListener('resize', scheduleTenantStatementFit, { passive: true });
window.visualViewport?.addEventListener('resize', scheduleTenantStatementFit, { passive: true });
document.addEventListener('turbo:load', scheduleTenantStatementFit);
document.addEventListener('turbo:frame-load', scheduleTenantStatementFit);
document.addEventListener('DOMContentLoaded', scheduleTenantStatementFit);

scheduleTenantStatementFit();
