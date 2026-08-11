/**
 * Phase 2B — reconcile #property-main frame route metadata with the browser URL.
 * Collections workspace tabs derive active state from the server route on each fetch;
 * when Turbo leaves a stale frame after tab clicks or history navigation, re-fetch once.
 */

const PROPERTY_MAIN_FRAME_ID = 'property-main';

let propertyFrameReconcileToken = null;
/** @type {string|null} */
let lastInSyncBrowserKey = null;
/** @type {string|null} */
let lastInSyncFrameRoute = null;
let lastReconcileRefetchAt = 0;
/** @type {string|null} */
let lastReconcileRefetchUrl = null;
/** @type {string|null} */
let pendingWorkspaceNavigationUrl = null;
/** @type {number} */
let deferredReconcileRaf = 0;
/** @type {number} */
let lastMainFrameLoadAt = 0;

const RECONCILE_AFTER_LOAD_COOLDOWN_MS = 2500;

export function stampPropertyMainFrameLoaded() {
    lastMainFrameLoadAt = Date.now();
}

function browserNavigationKey() {
    try {
        const url = new URL(window.location.href);

        return `${url.pathname.replace(/\/+$/, '')}${url.search}`;
    } catch {
        return '';
    }
}

function propertyNavDebug(payload) {
    if (window.__PROPERTY_DEBUG_NAV !== true) {
        return;
    }
    console.debug('[PropertyNav:reconcile]', payload);
}

/**
 * Mirrors App\Support\Property\PropertyNavigation::routeIsActive (wildcard suffix.*).
 *
 * @param {string} routeName
 * @param {string} pattern
 */
export function routeMatchesPattern(routeName, pattern) {
    const route = String(routeName || '').trim();
    const pat = String(pattern || '').trim();
    if (route === '' || pat === '') {
        return false;
    }
    if (pat.endsWith('.*')) {
        const prefix = pat.slice(0, -2);

        return route === prefix || route.startsWith(`${prefix}.`);
    }

    return route === pat;
}

/**
 * @param {string} routeName
 * @param {string[]} patterns
 */
export function routeMatchesAny(routeName, patterns) {
    return patterns.some((pattern) => routeMatchesPattern(routeName, pattern));
}

/**
 * Path suffix must align on a segment boundary (avoids partial segment false positives).
 *
 * @param {string} browserPath
 * @param {string} pathSuffix
 */
function browserPathMatchesSuffix(browserPath, pathSuffix) {
    if (!browserPath.endsWith(pathSuffix)) {
        return false;
    }
    const idx = browserPath.length - pathSuffix.length;
    if (idx === 0) {
        return true;
    }

    return browserPath[idx - 1] === '/';
}

/**
 * @typedef {object} FrameReconcileRule
 * @property {string} pathSuffix
 * @property {string[]} expectedActive
 * @property {(path: string) => boolean} [pathMatch]
 */

/** @type {FrameReconcileRule[]} — longest / most specific paths first */
const PROPERTY_FRAME_RECONCILE_RULES = [
    // —— Collections (revenue workspace) ——
    {
        pathSuffix: '/revenue/utilities/periods',
        expectedActive: ['property.revenue.utilities.periods', 'property.revenue.utilities.periods.*'],
    },
    {
        pathSuffix: '/revenue/utilities/analytics',
        expectedActive: ['property.revenue.utilities.analytics'],
    },
    {
        pathSuffix: '/revenue/utilities/reconciliation',
        expectedActive: ['property.revenue.utilities.reconciliation'],
    },
    {
        pathSuffix: '/revenue/utilities/ledger',
        expectedActive: ['property.revenue.utilities.ledger'],
    },
    {
        pathSuffix: '/revenue/utilities-charges',
        expectedActive: [
            'property.revenue.utilities',
            'property.revenue.utilities.store',
            'property.revenue.utilities.destroy',
            'property.revenue.utilities.water_readings.*',
            'property.revenue.utilities.water_invoices.*',
            'property.revenue.utilities.invoices.*',
            'property.revenue.utilities.water_penalties.*',
        ],
    },
    {
        pathSuffix: '/revenue/equity/sync-status',
        expectedActive: ['property.equity.sync_status', 'property.equity.sync_status.*'],
    },
    {
        pathSuffix: '/revenue/equity/unmatched',
        expectedActive: ['property.equity.unmatched', 'property.equity.unmatched.*'],
    },
    {
        pathSuffix: '/revenue/equity/matched',
        expectedActive: ['property.equity.matched', 'property.equity.matched.*'],
    },
    {
        pathSuffix: '/revenue/equity/all',
        expectedActive: ['property.equity.all'],
    },
    {
        pathSuffix: '/revenue/tenant-credits',
        expectedActive: ['property.revenue.tenant_credits'],
    },
    {
        pathSuffix: '/revenue/uninvoiced-leases',
        expectedActive: ['property.revenue.uninvoiced_leases', 'property.revenue.uninvoiced_leases.*'],
    },
    {
        pathSuffix: '/revenue/rent-roll',
        expectedActive: ['property.revenue.rent_roll'],
    },
    {
        pathSuffix: '/revenue/arrears',
        expectedActive: ['property.revenue.arrears', 'property.revenue.arrears.*'],
    },
    {
        pathSuffix: '/revenue/invoices',
        pathMatch: (path) => /\/revenue\/invoices(\/\d+)?$/.test(path) || path.endsWith('/revenue/invoices'),
        expectedActive: ['property.revenue.invoices', 'property.revenue.invoices.*', 'property.invoices.*'],
    },
    {
        pathSuffix: '/revenue/penalties',
        expectedActive: ['property.revenue.penalties', 'property.revenue.penalties.*'],
    },
    {
        pathSuffix: '/revenue/payments',
        pathMatch: (path) => /\/revenue\/payments(\/\d+)?(\/receipt(\/download)?)?$/.test(path) || path.endsWith('/revenue/payments'),
        expectedActive: ['property.revenue.payments', 'property.payments.*', 'property.tenants.credit.*'],
    },
    {
        pathSuffix: '/revenue/receipts',
        expectedActive: ['property.revenue.receipts'],
    },
    {
        pathSuffix: '/revenue/overview',
        expectedActive: ['property.revenue.overview', 'property.revenue.index'],
    },
    {
        pathSuffix: '/revenue',
        pathMatch: (path) => /\/revenue$/.test(path) || path.endsWith('/property/revenue'),
        expectedActive: ['property.revenue.overview', 'property.revenue.index'],
    },

    {
        pathSuffix: '/listings/applications',
        expectedActive: ['property.listings.applications', 'property.listings.applications.*'],
    },
    {
        pathSuffix: '/listings/leads',
        expectedActive: ['property.listings.leads', 'property.listings.leads.*'],
    },
    {
        pathSuffix: '/listings/ads',
        expectedActive: ['property.listings.ads'],
    },
    {
        pathSuffix: '/listings/vacant',
        expectedActive: ['property.listings.vacant', 'property.listings.vacant.*'],
    },
    {
        pathSuffix: '/listings/create',
        expectedActive: ['property.listings.create', 'property.listings.start'],
    },
    {
        pathSuffix: '/listings',
        pathMatch: (path) => /\/listings$/.test(path) || path.endsWith('/property/listings'),
        expectedActive: ['property.listings.create', 'property.listings.vacant', 'property.listings.*'],
    },

    // —— Tenants (most specific paths first) ——
    {
        pathSuffix: '/tenants/notices',
        expectedActive: ['property.tenants.notices', 'property.tenants.notices.*'],
    },
    {
        pathSuffix: '/tenants/movements',
        expectedActive: ['property.tenants.movements', 'property.tenants.movements.*'],
    },
    {
        pathSuffix: '/tenants/profiles',
        expectedActive: ['property.tenants.profiles', 'property.tenants.import', 'property.tenants.import.*'],
    },
    {
        pathSuffix: '/tenants/expiry',
        expectedActive: ['property.tenants.expiry'],
    },
    {
        pathSuffix: '/tenants/leases',
        pathMatch: (path) => path.endsWith('/tenants/leases'),
        expectedActive: ['property.tenants.leases', 'property.tenants.expiry', 'property.leases.*'],
    },
    {
        pathSuffix: '/tenants/directory',
        expectedActive: [
            'property.tenants.directory',
            'property.tenants.directory.*',
            'property.tenants.store',
            'property.tenants.store_json',
            'property.tenants.update',
            'property.tenants.destroy',
            'property.tenants.show',
            'property.tenants.statement',
            'property.tenants.edit',
        ],
    },

    // —— Portfolio (properties workspace) ——
    {
        pathSuffix: '/properties/performance',
        expectedActive: ['property.properties.performance'],
    },
    {
        pathSuffix: '/properties/occupancy',
        expectedActive: ['property.properties.occupancy'],
    },
    {
        pathSuffix: '/properties/amenities',
        expectedActive: ['property.properties.amenities', 'property.properties.amenities.*'],
    },
    {
        pathSuffix: '/properties/units',
        expectedActive: ['property.properties.units', 'property.units.*'],
    },
    {
        pathSuffix: '/properties/list',
        expectedActive: [
            'property.properties.list',
            'property.properties.store',
            'property.properties.show',
            'property.properties.edit',
            'property.properties.landlords.*',
        ],
    },

    // —— Settings ——
    {
        pathSuffix: '/settings/activity-log',
        expectedActive: ['property.settings.activity_log'],
    },
    {
        pathSuffix: '/settings/system-setup',
        expectedActive: ['property.settings.system_setup', 'property.settings.system_setup.*'],
    },
    {
        pathSuffix: '/settings/permissions',
        expectedActive: ['property.settings.permissions'],
    },
    {
        pathSuffix: '/settings/roles',
        expectedActive: ['property.settings.roles', 'property.settings.team_users.*'],
    },
    {
        pathSuffix: '/settings/commission',
        expectedActive: ['property.settings.commission', 'property.settings.commission.store'],
    },
    {
        pathSuffix: '/settings/payments',
        expectedActive: ['property.settings.payments', 'property.settings.payments.store'],
    },
    {
        pathSuffix: '/settings/branding',
        expectedActive: ['property.settings.branding', 'property.settings.branding.store'],
    },
    {
        pathSuffix: '/settings/rules',
        expectedActive: ['property.settings.rules', 'property.settings.rules.store'],
    },
    {
        pathSuffix: '/settings/deposits',
        expectedActive: ['property.settings.deposits', 'property.settings.deposits.store'],
    },
    {
        pathSuffix: '/settings/expenses',
        expectedActive: ['property.settings.expenses', 'property.settings.expenses.store'],
    },
    {
        pathSuffix: '/settings/my-forwarder',
        expectedActive: ['property.settings.forwarder', 'property.settings.forwarder.*'],
    },
    {
        pathSuffix: '/settings',
        pathMatch: (path) => /\/settings$/.test(path) || path.endsWith('/property/settings'),
        expectedActive: ['property.settings.index'],
    },

    // —— Portfolio (property offboarding / detail) ——
    {
        pathSuffix: '/offboarding',
        pathMatch: (path) => /\/properties\/\d+\/offboarding$/.test(path),
        expectedActive: ['property.properties.offboarding', 'property.properties.offboarding.*'],
    },
];

/** Any frame route that belongs to the Collections workspace primary/sub tabs. */
const COLLECTIONS_WORKSPACE_ROUTE_PATTERNS = [
    'property.revenue.overview',
    'property.revenue.index',
    'property.revenue.rent_roll',
    'property.revenue.arrears.*',
    'property.revenue.uninvoiced_leases.*',
    'property.revenue.invoices.*',
    'property.invoices.*',
    'property.revenue.penalties.*',
    'property.revenue.payments',
    'property.payments.*',
    'property.tenants.credit.*',
    'property.revenue.receipts',
    'property.revenue.tenant_credits',
    'property.revenue.utilities.*',
    'property.tenants.utility.statement',
    'property.equity.matched.*',
    'property.equity.unmatched.*',
    'property.equity.all',
    'property.equity.sync_status.*',
];

const TENANTS_WORKSPACE_ROUTE_PATTERNS = [
    'property.tenants.directory.*',
    'property.tenants.directory',
    'property.tenants.leases',
    'property.tenants.expiry',
    'property.leases.*',
    'property.tenants.notices.*',
    'property.tenants.movements.*',
    'property.tenants.profiles',
    'property.tenants.import.*',
    'property.tenants.show',
    'property.tenants.statement',
    'property.tenants.edit',
    'property.tenants.store',
    'property.tenants.store_json',
    'property.tenants.update',
    'property.tenants.destroy',
];

const PORTFOLIO_WORKSPACE_ROUTE_PATTERNS = [
    'property.properties.list',
    'property.properties.store',
    'property.properties.show',
    'property.properties.edit',
    'property.properties.units',
    'property.units.*',
    'property.properties.occupancy',
    'property.properties.performance',
    'property.properties.amenities',
    'property.properties.amenities.*',
    'property.properties.offboarding',
    'property.properties.offboarding.*',
];

const SETTINGS_WORKSPACE_ROUTE_PATTERNS = [
    'property.settings.index',
    'property.settings.activity_log',
    'property.settings.roles',
    'property.settings.team_users.*',
    'property.settings.permissions',
    'property.settings.commission',
    'property.settings.commission.store',
    'property.settings.payments',
    'property.settings.payments.store',
    'property.settings.branding',
    'property.settings.branding.store',
    'property.settings.rules',
    'property.settings.rules.store',
    'property.settings.deposits',
    'property.settings.deposits.store',
    'property.settings.expenses',
    'property.settings.expenses.store',
    'property.settings.forwarder',
    'property.settings.forwarder.*',
    'property.settings.system_setup',
    'property.settings.system_setup.*',
];

/**
 * @param {string} frameRoute
 * @returns {FrameReconcileRule|null}
 */
function resolveReconcileRuleForFrameRoute(frameRoute) {
    for (const rule of PROPERTY_FRAME_RECONCILE_RULES) {
        if (routeMatchesAny(frameRoute, rule.expectedActive)) {
            return rule;
        }
    }

    return null;
}

/**
 * @param {FrameReconcileRule} rule
 */
function urlForReconcileRule(rule) {
    const url = new URL(window.location.href);
    const pathname = url.pathname.replace(/\/+$/, '');
    const propertyMarker = '/property';
    const idx = pathname.indexOf(propertyMarker);

    if (idx >= 0) {
        url.pathname = `${pathname.slice(0, idx + propertyMarker.length)}${rule.pathSuffix}`;
    } else {
        url.pathname = rule.pathSuffix;
    }

    return url.toString();
}

/**
 * Record the workspace tab the user clicked (Turbo may load the frame before the URL advances).
 *
 * @param {string} url
 */
export function notePendingWorkspaceNavigation(url) {
    pendingWorkspaceNavigationUrl = String(url || '').trim() || null;
    propertyNavDebug({ action: 'pending-workspace-nav', url: pendingWorkspaceNavigationUrl });
}

export function clearPendingWorkspaceNavigation() {
    pendingWorkspaceNavigationUrl = null;
}

/**
 * Defer reconciliation until after Turbo updates the browser URL (avoids Directory↔Leases races).
 *
 * @param {HTMLElement} frame
 * @param {(url: string) => void} visitMainFrame
 * @param {{ force?: boolean }} [options]
 */
export function scheduleDeferredPropertyFrameReconciliation(frame, visitMainFrame, options = {}) {
    if (options.force === true) {
        reconcilePropertyFrameWithBrowserUrl(frame, visitMainFrame, options);

        return;
    }

    if (deferredReconcileRaf) {
        cancelAnimationFrame(deferredReconcileRaf);
    }

    let frames = 0;
    const step = () => {
        frames += 1;
        if (frames < 2) {
            deferredReconcileRaf = requestAnimationFrame(step);

            return;
        }

        deferredReconcileRaf = 0;
        reconcilePropertyFrameWithBrowserUrl(frame, visitMainFrame, options);
    };

    deferredReconcileRaf = requestAnimationFrame(step);
}

/**
 * @param {string} browserPath
 * @returns {FrameReconcileRule|null}
 */
function resolveReconcileRule(browserPath) {
    for (const rule of PROPERTY_FRAME_RECONCILE_RULES) {
        const suffixMatch = browserPathMatchesSuffix(browserPath, rule.pathSuffix);
        const customMatch = typeof rule.pathMatch === 'function' ? rule.pathMatch(browserPath) : false;
        if (suffixMatch || customMatch) {
            return rule;
        }
    }

    return null;
}

/**
 * @param {string} browserPath
 * @param {string} frameRoute
 * @param {(url: string) => void} visitMainFrame
 * @param {{ force?: boolean }} [options]
 */
export function reconcilePropertyFrameWithBrowserUrl(frame, visitMainFrame, options = {}) {
    const force = options.force === true;
    if (!(frame instanceof HTMLElement) || frame.id !== PROPERTY_MAIN_FRAME_ID) {
        return;
    }

    if (
        !force
        && lastMainFrameLoadAt > 0
        && Date.now() - lastMainFrameLoadAt < RECONCILE_AFTER_LOAD_COOLDOWN_MS
    ) {
        propertyNavDebug({
            action: 'skip',
            reason: 'recent-frame-load',
            msSinceLoad: Date.now() - lastMainFrameLoadAt,
        });

        return;
    }

    const frameRoute = frame.querySelector('#property-main-route')?.dataset?.routeName ?? '';
    if (frameRoute === '') {
        return;
    }

    let browserPath;
    try {
        browserPath = new URL(window.location.href).pathname.replace(/\/+$/, '');
    } catch {
        return;
    }

    const browserRule = resolveReconcileRule(browserPath);
    const frameRule = resolveReconcileRuleForFrameRoute(frameRoute);

    if (pendingWorkspaceNavigationUrl) {
        try {
            const pendingUrl = new URL(pendingWorkspaceNavigationUrl, window.location.href);
            const pendingPath = pendingUrl.pathname.replace(/\/+$/, '');
            const pendingRule = resolveReconcileRule(pendingPath);

            if (pendingRule && routeMatchesAny(frameRoute, pendingRule.expectedActive)) {
                const browserMatchesPending =
                    browserPath === pendingPath
                    || browserPathMatchesSuffix(browserPath, pendingRule.pathSuffix)
                    || (typeof pendingRule.pathMatch === 'function' && pendingRule.pathMatch(browserPath));

                if (!browserMatchesPending) {
                    propertyNavDebug({
                        action: 'sync-pending-tab',
                        pendingWorkspaceNavigationUrl,
                        browserPath,
                        frameRoute,
                    });
                    visitMainFrame(pendingWorkspaceNavigationUrl);
                }

                clearPendingWorkspaceNavigation();
                propertyFrameReconcileToken = null;
                lastInSyncBrowserKey = browserNavigationKey();
                lastInSyncFrameRoute = frameRoute;

                return;
            }
        } catch {
            // fall through to standard reconcile
        }
    }

    if (browserRule === null) {
        propertyNavDebug({
            action: 'skip',
            reason: 'no-rule-for-path',
            browserPath,
            frameRoute,
        });

        return;
    }

    const browserKey = browserNavigationKey();
    const rule = browserRule;

    if (routeMatchesAny(frameRoute, rule.expectedActive)) {
        propertyFrameReconcileToken = null;
        lastInSyncBrowserKey = browserKey;
        lastInSyncFrameRoute = frameRoute;
        clearPendingWorkspaceNavigation();
        propertyNavDebug({
            action: 'in-sync',
            browserPath,
            frameRoute,
            pathSuffix: rule.pathSuffix,
        });

        return;
    }

    const frameMatchesFrameRule = frameRule !== null && routeMatchesAny(frameRoute, frameRule.expectedActive);
    const browserMatchesFrameRulePath =
        frameRule !== null
        && (browserPathMatchesSuffix(browserPath, frameRule.pathSuffix)
            || (typeof frameRule.pathMatch === 'function' && frameRule.pathMatch(browserPath)));

    if (frameMatchesFrameRule && !browserMatchesFrameRulePath) {
        propertyFrameReconcileToken = null;
        lastReconcileRefetchAt = Date.now();
        lastReconcileRefetchUrl = urlForReconcileRule(frameRule);
        propertyNavDebug({
            action: 'sync-frame-ahead-of-url',
            browserPath,
            frameRoute,
            target: lastReconcileRefetchUrl,
        });
        visitMainFrame(lastReconcileRefetchUrl);
        clearPendingWorkspaceNavigation();

        return;
    }

    if (
        !force
        && lastInSyncBrowserKey === browserKey
        && lastInSyncFrameRoute === frameRoute
    ) {
        propertyNavDebug({
            action: 'skip',
            reason: 'already-verified-in-sync',
            browserPath,
            frameRoute,
        });

        return;
    }

    const now = Date.now();
    const currentHref = window.location.href;
    if (
        !force
        && lastReconcileRefetchUrl === currentHref
        && now - lastReconcileRefetchAt < 4000
    ) {
        propertyNavDebug({
            action: 'skip',
            reason: 'refetch-cooldown',
            browserPath,
            frameRoute,
        });

        return;
    }

    const workspacePatterns = rule.pathSuffix.includes('/revenue')
        ? COLLECTIONS_WORKSPACE_ROUTE_PATTERNS
        : (rule.pathSuffix.includes('/settings')
            ? SETTINGS_WORKSPACE_ROUTE_PATTERNS
            : (rule.pathSuffix.includes('/properties')
                ? PORTFOLIO_WORKSPACE_ROUTE_PATTERNS
                : TENANTS_WORKSPACE_ROUTE_PATTERNS));

    if (!routeMatchesAny(frameRoute, workspacePatterns)) {
        propertyNavDebug({
            action: 'skip',
            reason: 'frame-route-outside-workspace',
            browserPath,
            frameRoute,
            pathSuffix: rule.pathSuffix,
        });

        return;
    }

    const retryKey = `${browserPath}:${frameRoute}`;
    if (propertyFrameReconcileToken === retryKey) {
        propertyNavDebug({
            action: 'skip',
            reason: 'reconcile-already-attempted',
            browserPath,
            frameRoute,
        });

        return;
    }

    propertyFrameReconcileToken = retryKey;
    lastReconcileRefetchAt = now;
    lastReconcileRefetchUrl = currentHref;
    propertyNavDebug({
        action: 'refetch',
        browserPath,
        frameRoute,
        pathSuffix: rule.pathSuffix,
        expectedActive: rule.expectedActive,
        force,
    });
    visitMainFrame(currentHref);
    clearPendingWorkspaceNavigation();
}

export function resetPropertyFrameReconcileToken(reason = 'manual') {
    propertyFrameReconcileToken = null;
    if (reason === 'popstate' || reason.startsWith('pageshow')) {
        lastInSyncBrowserKey = null;
        lastInSyncFrameRoute = null;
        clearPendingWorkspaceNavigation();
    }
    propertyNavDebug({ action: 'reset-token', reason });
}
