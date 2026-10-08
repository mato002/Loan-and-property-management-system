@php
    $openTasksScope = $openTasksScope ?? 'app';
    $openTasksFrame = $openTasksFrame ?? '';
    $openTasksUser = auth()->id() ?? 0;
@endphp
<nav
    class="open-tasks property-print-hide print-hide shrink-0"
    data-turbo-permanent
    data-open-tasks
    data-open-tasks-scope="{{ $openTasksScope }}"
    data-open-tasks-frame="{{ $openTasksFrame }}"
    data-open-tasks-user="{{ $openTasksUser }}"
    aria-label="Open tasks"
    role="list"
    hidden
></nav>
@once
    <style>
        .open-tasks {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            overflow-x: auto;
            overflow-y: hidden;
            background: #e7eef3;
            border-bottom: 1px solid #d3dde4;
            padding: 6px 8px 0;
            min-height: 40px;
            scrollbar-width: thin;
        }
        .open-tasks[hidden] { display: none !important; }
        .open-tasks__tab {
            display: inline-flex;
            align-items: stretch;
            flex: 0 0 auto;
            max-width: 16rem;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            background: transparent;
            color: #334155;
        }
        .open-tasks__tab.is-active {
            background: #fff;
            border-color: #d3dde4;
            position: relative;
            top: 1px;
            color: #0f172a;
        }
        .open-tasks__link {
            display: flex;
            align-items: center;
            min-height: 34px;
            max-width: 12rem;
            padding: 6px 2px 6px 10px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
            color: inherit;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .open-tasks__close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            min-height: 34px;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: #64748b;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
        }
        .open-tasks__close:hover,
        .open-tasks__tab:hover { background: rgba(255, 255, 255, 0.72); }
        .open-tasks__tab.is-active .open-tasks__close:hover { background: #f1f5f9; }
        .dark .open-tasks,
        html.dark .open-tasks {
            background: #0f172a;
            border-bottom-color: #334155;
        }
        .dark .open-tasks__tab,
        html.dark .open-tasks__tab { color: #cbd5e1; }
        .dark .open-tasks__tab.is-active,
        html.dark .open-tasks__tab.is-active {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }
        .dark .open-tasks__close:hover,
        html.dark .open-tasks__close:hover,
        .dark .open-tasks__tab:hover,
        html.dark .open-tasks__tab:hover { background: rgba(30, 41, 59, 0.85); }
        @media print {
            .open-tasks { display: none !important; }
        }
    </style>
    <script>
        (function () {
            if (window.__phOpenTasks) {
                return;
            }
            window.__phOpenTasks = true;

            var MAX = 12;
            var VIEW_PARAMS = { tab: 1, channel: 1, section: 1, view: 1, mode: 1, focus: 1, report: 1 };
            var SKIP_PATH = /\/(login|logout|register|password|sanctum)(\/|$)|(?:^|\/)(export|download|print)(?:\/|$)/i;

            function bars() {
                return Array.from(document.querySelectorAll('[data-open-tasks]'));
            }

            function storageKey(bar) {
                return 'ph.openTasks.v1.' + (bar.getAttribute('data-open-tasks-scope') || 'app') + '.' + (bar.getAttribute('data-open-tasks-user') || '0');
            }

            function load(bar) {
                try {
                    var parsed = JSON.parse(localStorage.getItem(storageKey(bar)) || '[]');
                    return Array.isArray(parsed) ? parsed.filter(function (task) {
                        return task && typeof task.key === 'string' && typeof task.url === 'string' && task.url.charAt(0) === '/' && task.url.charAt(1) !== '/';
                    }) : [];
                } catch (error) {
                    return [];
                }
            }

            function save(bar, list) {
                try {
                    localStorage.setItem(storageKey(bar), JSON.stringify(list.slice(0, MAX)));
                } catch (error) {}
            }

            function frameOf(bar) {
                var id = bar.getAttribute('data-open-tasks-frame') || '';
                return id ? document.getElementById(id) : null;
            }

            function scrollParent(bar) {
                return document.getElementById('property-workspace-main')
                    || document.getElementById('loan-workspace-main')
                    || (bar.parentElement ? bar.parentElement.querySelector('main') : null);
            }

            function cleanTitle(value) {
                return String(value || '')
                    .replace(/\s+[—–|-]\s+Super Admin.*$/i, '')
                    .trim()
                    .slice(0, 80);
            }

            function prettyPath(pathname) {
                var parts = String(pathname || '').split('/').filter(Boolean);
                var last = parts[parts.length - 1] || 'Page';
                if (/^\d+$/.test(last) && parts.length > 1) {
                    last = parts[parts.length - 2];
                }
                return last.replace(/[-_]+/g, ' ').replace(/\b\w/g, function (letter) {
                    return letter.toUpperCase();
                });
            }

            function parseUrl(href) {
                try {
                    var url = new URL(href, window.location.origin);
                    if (url.origin !== window.location.origin || SKIP_PATH.test(url.pathname)) {
                        return null;
                    }
                    if (url.searchParams.has('export')) {
                        return null;
                    }
                    return url;
                } catch (error) {
                    return null;
                }
            }

            function taskKey(url) {
                var pathname = url.pathname.replace(/\/+$/, '') || '/';
                var bits = [];
                Object.keys(VIEW_PARAMS).sort().forEach(function (name) {
                    if (url.searchParams.has(name)) {
                        bits.push(name + '=' + url.searchParams.get(name));
                    }
                });
                return pathname + (bits.length ? '?' + bits.join('&') : '');
            }

            function taskUrl(url) {
                return url.pathname + url.search;
            }

            function hasExtraQuery(url) {
                var extra = false;
                url.searchParams.forEach(function (_value, name) {
                    if (!VIEW_PARAMS[name]) {
                        extra = true;
                    }
                });
                return extra;
            }

            function pageTitle(bar) {
                var frame = frameOf(bar);
                var routeEl = frame ? frame.querySelector('[data-page-title]') : null;
                var title = routeEl ? routeEl.getAttribute('data-page-title') : '';
                if (!title) {
                    var marked = document.querySelector('[data-open-task-title]');
                    title = marked ? marked.textContent : '';
                }
                if (!title) {
                    var heading = frame ? frame.querySelector('h1') : document.querySelector('main h1');
                    title = heading ? heading.textContent : '';
                }
                title = cleanTitle(title);
                return title || prettyPath(window.location.pathname);
            }

            function currentMeta(bar) {
                var url = parseUrl(window.location.href);
                if (!url) {
                    return null;
                }
                return {
                    key: taskKey(url),
                    url: taskUrl(url),
                    title: pageTitle(bar)
                };
            }

            function render(bar, list, activeKey) {
                bar.hidden = list.length === 0;
                bar.replaceChildren();
                list.forEach(function (task) {
                    var tab = document.createElement('div');
                    tab.className = 'open-tasks__tab' + (task.key === activeKey ? ' is-active' : '');
                    tab.setAttribute('role', 'listitem');

                    var link = document.createElement('a');
                    link.className = 'open-tasks__link';
                    link.href = task.url;
                    link.title = task.title;
                    link.textContent = task.title;
                    var frame = bar.getAttribute('data-open-tasks-frame') || '';
                    if (frame) {
                        link.setAttribute('data-turbo-frame', frame);
                    }
                    if (task.key === activeKey) {
                        link.setAttribute('aria-current', 'page');
                    }
                    link.addEventListener('click', function (event) {
                        if (task.key === activeKey) {
                            event.preventDefault();
                            return;
                        }
                        persistScroll(bar);
                        sessionStorage.setItem('ph.openTasks.restore', task.key);
                    });

                    var close = document.createElement('button');
                    close.type = 'button';
                    close.className = 'open-tasks__close';
                    close.setAttribute('aria-label', 'Close ' + task.title);
                    close.textContent = '×';
                    close.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        closeTask(bar, task.key);
                    });

                    tab.appendChild(link);
                    tab.appendChild(close);
                    bar.appendChild(tab);
                });
                var active = bar.querySelector('.open-tasks__tab.is-active');
                if (active && typeof active.scrollIntoView === 'function') {
                    active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            }

            function remember(bar, meta) {
                if (!meta) {
                    render(bar, load(bar), '');
                    return;
                }
                var suppressed = sessionStorage.getItem('ph.openTasks.suppress') || '';
                if (suppressed === meta.key + '\n' + meta.url) {
                    render(bar, load(bar), '');
                    return;
                }
                sessionStorage.removeItem('ph.openTasks.suppress');
                var list = load(bar);
                var index = list.findIndex(function (task) { return task.key === meta.key; });
                var next = { key: meta.key, url: meta.url, title: meta.title, scroll: 0 };
                if (index >= 0) {
                    next.scroll = list[index].url === meta.url ? (list[index].scroll || 0) : 0;
                    list[index] = next;
                } else {
                    list.push(next);
                    while (list.length > MAX) {
                        list.shift();
                    }
                }
                save(bar, list);
                render(bar, list, meta.key);
                if (sessionStorage.getItem('ph.openTasks.restore') === meta.key) {
                    sessionStorage.removeItem('ph.openTasks.restore');
                    var parent = scrollParent(bar);
                    if (parent) {
                        var top = next.scroll || 0;
                        window.requestAnimationFrame(function () {
                            parent.scrollTop = top;
                        });
                    }
                }
            }

            function persistScroll(bar) {
                var meta = currentMeta(bar);
                if (!meta) {
                    return;
                }
                var list = load(bar);
                var task = list.find(function (item) { return item.key === meta.key; });
                var parent = scrollParent(bar);
                if (!task || !parent) {
                    return;
                }
                task.scroll = parent.scrollTop || 0;
                save(bar, list);
            }

            function visit(bar, url) {
                var frame = bar.getAttribute('data-open-tasks-frame') || '';
                if (frame && window.Turbo && typeof window.Turbo.visit === 'function') {
                    window.Turbo.visit(url, { frame: frame, action: 'advance' });
                    return;
                }
                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                    window.Turbo.visit(url, { action: 'advance' });
                    return;
                }
                window.location.assign(url);
            }

            function closeTask(bar, key) {
                var list = load(bar);
                var index = list.findIndex(function (task) { return task.key === key; });
                if (index < 0) {
                    return;
                }
                var meta = currentMeta(bar);
                var wasActive = meta && meta.key === key;
                var removed = list.splice(index, 1)[0];
                save(bar, list);
                if (wasActive && removed) {
                    sessionStorage.setItem('ph.openTasks.suppress', removed.key + '\n' + removed.url);
                }
                if (wasActive && list.length) {
                    var neighbor = list[Math.min(index, list.length - 1)];
                    sessionStorage.setItem('ph.openTasks.restore', neighbor.key);
                    render(bar, list, neighbor.key);
                    visit(bar, neighbor.url);
                    return;
                }
                render(bar, list, wasActive ? '' : (meta ? meta.key : ''));
            }

            function isShellLink(link) {
                return !!link.closest('#property-shell-sidebar, #property-shell-header, #loan-shell-sidebar, #loan-shell-header, .superadmin-sidebar, .property-mobile-bottom-nav, .property-mobile-more-drawer');
            }

            function sync() {
                bars().forEach(function (bar) {
                    remember(bar, currentMeta(bar));
                });
            }

            document.addEventListener('click', function (event) {
                var link = event.target.closest('a[href]');
                if (!link || link.closest('[data-open-tasks]') || event.defaultPrevented) {
                    return;
                }
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')) {
                    return;
                }
                if ((link.getAttribute('data-turbo-frame') || '') === '_top') {
                    return;
                }
                sessionStorage.removeItem('ph.openTasks.suppress');
                if (!isShellLink(link)) {
                    return;
                }
                var dest = parseUrl(link.href);
                if (!dest || hasExtraQuery(dest)) {
                    return;
                }
                var bar = bars()[0];
                if (!bar) {
                    return;
                }
                var existing = load(bar).find(function (task) { return task.key === taskKey(dest); });
                if (!existing || existing.url === taskUrl(dest)) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                persistScroll(bar);
                sessionStorage.setItem('ph.openTasks.restore', existing.key);
                visit(bar, existing.url);
            }, true);

            document.addEventListener('submit', function () {
                sessionStorage.removeItem('ph.openTasks.suppress');
            }, true);

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sync);
            } else {
                sync();
            }
            document.addEventListener('turbo:load', sync);
            document.addEventListener('turbo:frame-load', function (event) {
                var frame = event.target;
                if (!frame || !frame.id) {
                    return;
                }
                var bar = document.querySelector('[data-open-tasks-frame="' + frame.id + '"]');
                if (bar) {
                    remember(bar, currentMeta(bar));
                }
            });
        })();
    </script>
@endonce
