<style>
    [data-drawer-search-slot]:empty { display: none; }
    @media (max-width: 767.98px) {
        .property-inline-actions:has([data-property-filter-drawer-host]) {
            flex-wrap: wrap !important;
            overflow: visible !important;
            align-items: stretch !important;
        }
        [data-property-filter-drawer-host] {
            width: 100% !important;
            max-width: 100% !important;
            flex: 1 1 100% !important;
        }
        [data-mobile-filter-search],
        [data-mobile-filter-search] input,
        [data-mobile-filter-search] .property-filter-field,
        [data-mobile-filter-search] .property-filter-field__control {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }
        .is-mobile-filter-collapsed {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.5rem !important;
        }
        details.mobile-filter-collapse {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        }
        .dark details.mobile-filter-collapse {
            border-color: #475569;
            background: #1f2937;
            color: #f8fafc;
        }
        details.mobile-filter-collapse > summary {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 44px;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }
        details.mobile-filter-collapse > summary::-webkit-details-marker { display: none; }
        details.mobile-filter-collapse[open] > .mobile-filter-collapse__body {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0.75rem;
            border-top: 1px solid #e2e8f0;
        }
        .dark details.mobile-filter-collapse[open] > .mobile-filter-collapse__body {
            border-top-color: #334155;
        }
        details.mobile-filter-collapse[open] > .mobile-filter-collapse__body > * {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }
        .mobile-filter-collapse__count {
            display: inline-flex;
            min-width: 1.25rem;
            height: 1.25rem;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #059669;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 0 0.35rem;
        }
    }
    @media (min-width: 768px) {
        details.mobile-filter-collapse.mobile-filter-collapse--inline {
            border: 0;
            background: transparent;
            box-shadow: none;
        }
        details.mobile-filter-collapse.mobile-filter-collapse--inline > summary {
            display: none !important;
        }
        details.mobile-filter-collapse.mobile-filter-collapse--inline > .mobile-filter-collapse__body {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
            padding: 0;
            border: 0;
        }
    }
    @media (min-width: 1024px) {
        details.mobile-filter-collapse.mobile-filter-collapse--inline > .mobile-filter-collapse__body {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
</style>
<script>
(function () {
    var journal = [];
    var mobileQuery = window.matchMedia('(max-width: 767.98px)');

    function isSearchInput(el) {
        if (!el || el.tagName !== 'INPUT') return false;
        var type = (el.getAttribute('type') || 'text').toLowerCase();
        if (['hidden', 'checkbox', 'radio', 'file', 'password', 'submit', 'button', 'date', 'month', 'number', 'email', 'datetime-local', 'time'].indexOf(type) !== -1) {
            return false;
        }
        if (type === 'search' || el.getAttribute('data-filter-search') === '1') return true;
        var name = (el.getAttribute('name') || '').toLowerCase();
        if (name === 'q' || name === 'search' || name === 'query') return true;
        return (el.getAttribute('placeholder') || '').toLowerCase().indexOf('search') !== -1;
    }

    function childIsSearch(child) {
        if (isSearchInput(child)) return true;
        if (!child.querySelector) return false;
        if (child.querySelector('select, input[type="date"], input[type="month"], input[type="number"], input[type="datetime-local"]')) return false;
        return Array.prototype.some.call(child.querySelectorAll('input'), isSearchInput);
    }

    function childIsFilter(child) {
        if (!child || !child.querySelector) return false;
        if (child.matches && child.matches('select, input[type="date"], input[type="month"], input[type="number"], input[type="datetime-local"]')) return true;
        if (child.querySelector('select, input[type="date"], input[type="month"], input[type="number"], input[type="datetime-local"]')) return true;
        var text = (child.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (text.length > 80 || !child.querySelector('button, a')) return false;
        return /\b(apply|reset|clear)\b/.test(text);
    }

    function isHidden(el) {
        if (!el || el.hidden) return true;
        var node = el;
        while (node && node.nodeType === 1) {
            if (node.hidden) return true;
            var style = window.getComputedStyle(node);
            if (style.display === 'none' || style.visibility === 'hidden') return true;
            node = node.parentElement;
        }
        return false;
    }

    function looksLikeFilterCluster(el) {
        if (!el || el.nodeType !== 1) return false;
        if (el.getAttribute('data-mobile-filters-bound') === '1') return false;
        if (el.closest('[data-mobile-filters-bound="1"], details.mobile-filter-collapse, details.property-filter-mobile-toggle, [role="dialog"], [data-property-form-modal], header, nav, footer, aside')) return false;
        if (el.querySelector('table, textarea, details.mobile-filter-collapse, details.property-filter-mobile-toggle')) return false;
        var kids = Array.prototype.filter.call(el.children, function (kid) { return kid.nodeType === 1; });
        if (kids.length < 2 || kids.length > 16) return false;
        var searchKids = 0;
        var filterKids = 0;
        var otherKids = 0;
        kids.forEach(function (kid) {
            if (childIsSearch(kid)) searchKids += 1;
            else if (childIsFilter(kid)) filterKids += 1;
            else if ((kid.textContent || '').trim() !== '') otherKids += 1;
        });
        if (otherKids > 1) return false;
        return filterKids >= 1 && (searchKids >= 1 || filterKids >= 2);
    }

    function remember(node) {
        journal.push({ node: node, parent: node.parentNode, next: node.nextSibling });
    }

    function refreshCount(details) {
        var count = 0;
        details.querySelectorAll('select').forEach(function (sel) {
            if (sel.selectedIndex > 0 && sel.value !== '') count += 1;
        });
        details.querySelectorAll('input').forEach(function (input) {
            if (isSearchInput(input) || (input.getAttribute('type') || '') === 'hidden') return;
            if (input.value) count += 1;
        });
        var summary = details.querySelector('summary');
        if (!summary) return;
        var badge = summary.querySelector('.mobile-filter-collapse__count');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'mobile-filter-collapse__count';
                summary.appendChild(badge);
            }
            badge.textContent = String(count);
            badge.hidden = false;
        } else if (badge) {
            badge.hidden = true;
        }
    }

    function collapseCluster(container) {
        if (!looksLikeFilterCluster(container) || isHidden(container)) return;
        if (container.matches('form') && (container.getAttribute('method') || 'get').toLowerCase() === 'post') return;
        if (container.hasAttribute('data-property-filter-form-desktop')) return;

        var kids = Array.prototype.filter.call(container.children, function (kid) { return kid.nodeType === 1; });
        var searchNodes = [];
        var filterNodes = [];
        kids.forEach(function (kid) {
            if (childIsSearch(kid)) searchNodes.push(kid);
            else if (childIsFilter(kid)) filterNodes.push(kid);
        });
        if (!filterNodes.length) return;

        var details = document.createElement('details');
        details.className = 'mobile-filter-collapse';
        details.setAttribute('data-mobile-filter-generated', '1');
        var summary = document.createElement('summary');
        summary.innerHTML = '<i class="fa-solid fa-sliders" aria-hidden="true"></i> Filters';
        var body = document.createElement('div');
        body.className = 'mobile-filter-collapse__body';
        var hostForm = container.matches('form') ? container : null;
        if (hostForm && !hostForm.id) hostForm.id = 'mf-' + Math.random().toString(36).slice(2, 8);
        filterNodes.forEach(function (node) {
            remember(node);
            if (hostForm) {
                var controls = node.matches && node.matches('input, select, textarea, button') ? [node] : [];
                if (node.querySelectorAll) {
                    Array.prototype.forEach.call(node.querySelectorAll('input, select, textarea, button'), function (control) {
                        controls.push(control);
                    });
                }
                controls.forEach(function (control) {
                    if (!control.getAttribute('form')) control.setAttribute('form', hostForm.id);
                });
            }
            body.appendChild(node);
        });

        var exportForm = container.matches('form[data-sa-auto-filter]') ? container : container.closest('form[data-sa-auto-filter]');
        if (exportForm) {
            var sibling = exportForm.nextElementSibling;
            if (sibling && !sibling.querySelector('table, select, textarea, input[type="search"]') && /\b(csv|excel|pdf|approve all|revoke all)\b/i.test(sibling.textContent || '')) {
                remember(sibling);
                body.appendChild(sibling);
            }
        }

        details.appendChild(summary);
        details.appendChild(body);
        if (hostForm && hostForm.parentNode) {
            hostForm.parentNode.insertBefore(details, hostForm.nextSibling);
        } else {
            var anchor = searchNodes.length ? searchNodes[searchNodes.length - 1].nextSibling : container.firstChild;
            container.insertBefore(details, anchor);
        }
        container.setAttribute('data-mobile-filters-bound', '1');
        container.classList.add('is-mobile-filter-collapsed');
        refreshCount(details);
    }

    function hoistDrawerSearch() {
        document.querySelectorAll('[data-property-filter-drawer-host]').forEach(function (host) {
            var dialog = host.querySelector('[role="dialog"]');
            var slot = host.querySelector('[data-drawer-search-slot]');
            if (!dialog || !slot) {
                host.setAttribute('data-mobile-filters-bound', '1');
                return;
            }
            Array.prototype.forEach.call(dialog.querySelectorAll('input'), function (input) {
                if (!isSearchInput(input) || input.closest('[data-property-filter-form-desktop]')) return;
                var node = input.closest('.property-filter-field, [data-mobile-filter-search]') || input;
                var form = node.closest('form');
                if (form) {
                    if (!form.id) form.id = 'mf-' + Math.random().toString(36).slice(2, 8);
                    var controls = node.matches('input, select, textarea') ? [node] : [];
                    Array.prototype.forEach.call(node.querySelectorAll('input, select, textarea'), function (control) {
                        controls.push(control);
                    });
                    controls.forEach(function (control) {
                        if (!control.getAttribute('form')) control.setAttribute('form', form.id);
                    });
                }
                remember(node);
                slot.appendChild(node);
            });
            host.setAttribute('data-mobile-filters-bound', '1');
        });
    }

    function restore() {
        for (var i = journal.length - 1; i >= 0; i -= 1) {
            var entry = journal[i];
            if (!entry.parent || !entry.parent.isConnected) continue;
            if (entry.next && entry.next.parentNode === entry.parent) entry.parent.insertBefore(entry.node, entry.next);
            else entry.parent.appendChild(entry.node);
        }
        journal = [];
        document.querySelectorAll('[data-mobile-filter-generated="1"]').forEach(function (el) { el.remove(); });
        document.querySelectorAll('[data-mobile-filters-bound], .is-mobile-filter-collapsed').forEach(function (el) {
            el.removeAttribute('data-mobile-filters-bound');
            el.classList.remove('is-mobile-filter-collapsed');
        });
    }

    function apply() {
        journal = journal.filter(function (entry) { return entry.parent && entry.parent.isConnected; });
        if (!mobileQuery.matches) {
            restore();
            return;
        }
        hoistDrawerSearch();
        document.querySelectorAll('main form, main div.grid, main div.flex, main section, #property-main form, #property-main div.grid, #loan-main form, #loan-main div.grid').forEach(function (el) {
            if (el.closest('[data-property-filter-drawer-host]')) return;
            collapseCluster(el);
        });
    }

    document.addEventListener('change', function (event) {
        var details = event.target && event.target.closest ? event.target.closest('details.mobile-filter-collapse') : null;
        if (details) refreshCount(details);
    });
    document.addEventListener('turbo:load', apply);
    document.addEventListener('turbo:frame-load', apply);
    document.addEventListener('turbo:render', apply);
    if (mobileQuery.addEventListener) mobileQuery.addEventListener('change', apply);
    else if (mobileQuery.addListener) mobileQuery.addListener(apply);

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
    else apply();
})();
</script>
