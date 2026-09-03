/**
 * Color vacant / pending / exception rows on every data table (property + loan).
 * Token map must stay aligned with App\Support\Property\WorkspaceRowAlert.
 */

const TONE_RANK = {
    attention: 5,
    'vacant-long': 4,
    vacant: 3,
    notice: 2,
    occupied: 1,
};

const TOKEN_MAP = {
    'no active lease': 'attention',
    overdue: 'attention',
    unpaid: 'attention',
    failed: 'attention',
    rejected: 'attention',
    expired: 'attention',
    emergency: 'attention',
    urgent: 'attention',
    'urgent renewal call': 'attention',
    bounced: 'attention',
    defaulted: 'attention',
    watchlist: 'attention',
    npl: 'attention',
    'written off': 'attention',
    high: 'attention',
    severe: 'attention',
    critical: 'attention',
    blocked: 'attention',
    suspended: 'attention',
    declined: 'attention',
    'past due': 'attention',
    error: 'attention',
    uninvoiced: 'attention',
    'long vacant': 'vacant-long',
    '90+ days': 'vacant-long',
    '90 plus days': 'vacant-long',
    vacant: 'vacant',
    vacancy: 'vacant',
    occupied: 'occupied',
    notice: 'notice',
    pending: 'notice',
    draft: 'notice',
    'in progress': 'notice',
    partial: 'notice',
    sent: 'notice',
    medium: 'notice',
    'due soon': 'notice',
    'send renewal offer': 'notice',
    'not sent to tenant': 'notice',
    'pending disbursement': 'notice',
    expiring: 'notice',
};

const TONE_CLASSES = ['property-row-alert-occupied', 'property-row-alert-vacant', 'property-row-alert-vacant-long', 'property-row-alert-notice', 'property-row-alert-attention'];

function normalizeLine(value) {
    return String(value || '')
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}

function cellLines(cell) {
    if (!(cell instanceof HTMLElement)) {
        return [];
    }

    const clone = cell.cloneNode(true);
    clone.querySelectorAll('br').forEach((node) => node.replaceWith('\n'));
    const text = clone.textContent || '';
    return text
        .split(/\n+/)
        .map((line) => normalizeLine(line))
        .filter(Boolean);
}

function inferToneFromRow(row) {
    let best = '';
    let bestRank = 0;

    const pill = row.querySelector('[class*="property-status-pill--"]');
    if (pill) {
        const match = [...pill.classList].find((name) => name.startsWith('property-status-pill--'));
        const tone = match ? match.slice('property-status-pill--'.length) : '';
        if (TONE_RANK[tone] > bestRank) {
            best = tone;
            bestRank = TONE_RANK[tone];
        }
    }

    row.querySelectorAll('td, th').forEach((cell) => {
        cellLines(cell).forEach((line) => {
            const tone = TOKEN_MAP[line];
            if (tone && TONE_RANK[tone] > bestRank) {
                best = tone;
                bestRank = TONE_RANK[tone];
            }
        });
    });

    return best;
}

function applyRowTone(row) {
    if (!(row instanceof HTMLElement)) {
        return;
    }

    if (row.closest('thead, tfoot')) {
        return;
    }

    const cells = row.querySelectorAll('td, th');
    if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
        return;
    }

    const already = TONE_CLASSES.some((name) => row.classList.contains(name));
    const tone = already
        ? TONE_CLASSES.find((name) => row.classList.contains(name))?.replace('property-row-alert-', '') ?? ''
        : inferToneFromRow(row);

    if (!tone || !TONE_RANK[tone]) {
        return;
    }

    TONE_CLASSES.forEach((name) => row.classList.remove(name));
    row.classList.add(`property-row-alert-${tone}`);
}

export function applyPropertyRowAlerts(scopeRoot = document) {
    const root = scopeRoot instanceof Element || scopeRoot instanceof Document ? scopeRoot : document;
    root.querySelectorAll('table tbody tr').forEach(applyRowTone);
    root.querySelectorAll('[data-mobile-record-list] article').forEach(applyRowTone);
}

function bindRowAlerts() {
    applyPropertyRowAlerts(document);

    document.addEventListener('turbo:load', () => applyPropertyRowAlerts(document));
    document.addEventListener('turbo:frame-render', (event) => {
        applyPropertyRowAlerts(event.target instanceof Element ? event.target : document);
    });
    document.addEventListener('turbo:frame-load', (event) => {
        applyPropertyRowAlerts(event.target instanceof Element ? event.target : document);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindRowAlerts, { once: true });
} else {
    bindRowAlerts();
}
