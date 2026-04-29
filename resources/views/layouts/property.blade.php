<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @php
            $siteFaviconUrl = \App\Models\PropertyPortalSetting::getValue('site_favicon_url', '');
            $faviconHref = $siteFaviconUrl !== '' ? $siteFaviconUrl : asset('favicon.ico');
            $faviconVersioned = $faviconHref.'?v='.rawurlencode(substr(md5($faviconHref), 0, 12));
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Property Management System</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="icon" href="{{ $faviconVersioned }}" />
        <link rel="shortcut icon" href="{{ $faviconVersioned }}" />
        <link rel="apple-touch-icon" href="{{ $faviconVersioned }}" />
        
        <style>
            [x-cloak] { display: none !important; }
            .custom-scrollbar::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #b8c2ce;
                border-radius: 10px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }
            /* Firefox */
            .custom-scrollbar {
                scrollbar-width: auto;
                scrollbar-color: #b8c2ce transparent;
                scrollbar-gutter: stable;
            }
            @media print {
                @page { size: auto; margin: 12mm; }
                html, body {
                    background: #fff !important;
                    color: #000 !important;
                    height: auto !important;
                    overflow: visible !important;
                }
                .property-print-hide,
                .print-hide {
                    display: none !important;
                }
                .property-print-only {
                    display: block !important;
                }
                .property-print-root {
                    display: block !important;
                    width: 100% !important;
                    min-height: auto !important;
                }
                .property-print-main {
                    overflow: visible !important;
                    padding: 0 !important;
                    margin: 0 !important;
                }
                #property-main {
                    display: block !important;
                    width: 100% !important;
                }
                a {
                    text-decoration: none !important;
                    color: #000 !important;
                }
                .shadow-sm, .shadow, .shadow-lg, .rounded-2xl, .rounded-xl, .rounded-lg {
                    box-shadow: none !important;
                }
            }
            .property-print-only {
                display: none;
            }
            /* Reusable high-attention blocks for first-time user guidance */
            .property-attention-card {
                border-width: 2px;
                border-color: #bfdbfe;
                background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
            }
            .property-attention-title {
                font-size: 1.125rem;
                line-height: 1.5rem;
                font-weight: 700;
                color: #0f172a;
                letter-spacing: -0.01em;
            }
            .property-attention-hint {
                font-size: 0.8rem;
                line-height: 1.15rem;
                color: #475569;
            }
            /* Property module table grid lines (global) */
            #property-main table {
                border-collapse: collapse;
            }
            #property-main table th,
            #property-main table td {
                border: 1px solid #cbd5e1;
            }
            .dark #property-main table th,
            .dark #property-main table td {
                border-color: #334155;
            }
        </style>
    </head>
    <body
        class="font-sans antialiased h-screen overflow-hidden text-slate-900 bg-[#e8ecf1] selection:bg-emerald-200/80 @if(($propertyPortal ?? 'agent') === 'tenant') selection:bg-teal-200 @endif"
        x-data="{
            sidebarOpen: false,
            sidebarDesktopOpen: true,
            init() {
                const portal = @js($propertyPortal ?? 'agent');
                const saved = window.localStorage.getItem(`property.sidebar.desktop.open.${portal}`);
                if (saved !== null) {
                    this.sidebarDesktopOpen = saved === '1';
                }
            },
            toggleDesktopSidebar() {
                this.sidebarDesktopOpen = !this.sidebarDesktopOpen;
                const portal = @js($propertyPortal ?? 'agent');
                window.localStorage.setItem(`property.sidebar.desktop.open.${portal}`, this.sidebarDesktopOpen ? '1' : '0');
            }
        }"
    >
        <div class="h-full flex property-print-root">
            
            <!-- Property Module Dedicated Sidebar -->
            <div
                class="property-print-hide h-full flex-shrink-0 transition-all duration-300"
                :class="sidebarDesktopOpen ? 'lg:w-[18rem] lg:max-w-[18rem] lg:min-w-[18rem] lg:opacity-100' : 'lg:w-[5.5rem] lg:max-w-[5.5rem] lg:min-w-[5.5rem] lg:opacity-100'"
                :style="window.matchMedia('(min-width: 1024px)').matches
                    ? (sidebarDesktopOpen
                        ? 'width: 18rem; min-width: 18rem; max-width: 18rem;'
                        : 'width: 5.5rem; min-width: 5.5rem; max-width: 5.5rem;')
                    : ''"
            >
                @include('layouts.property_sidebar')
            </div>

            <!-- Main view container (Header, Content, Footer) -->
            <div class="flex-1 flex flex-col min-w-0 min-h-0 overflow-hidden">
                
                <!-- Dedicated Header -->
                <div class="property-print-hide">
                    @include('layouts.property_header')
                </div>

                <!-- Scrollable Content Area (Header/Footer remain constant) -->
                <main class="property-print-main relative z-0 flex-1 min-h-0 overflow-x-hidden overflow-y-auto w-full custom-scrollbar">
                    <div class="p-4 sm:p-6 lg:p-8 w-full">
                        <turbo-frame id="property-main" data-turbo-action="advance">
                            <div id="property-main-route" data-route-name="{{ Route::currentRouteName() ?? '' }}" hidden></div>
                            <x-property.next-steps-modal />
                            <x-swal-flash />
                            {{ $slot }}
                        </turbo-frame>
                    </div>
                </main>

                <!-- Dedicated Footer (constant) -->
                <div class="property-print-hide">
                    @include('layouts.property_footer')
                </div>

            </div>
        </div>

        @if (($propertyPortal ?? 'agent') === 'agent')
            <a
                href="{{ route('property.advisor') }}"
                data-turbo-frame="property-main"
                class="property-print-hide fixed bottom-5 right-5 z-30 flex items-center gap-2 rounded-full bg-violet-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-900/40 ring-2 ring-white/20 hover:bg-violet-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 transition-colors"
                title="AI advisor"
            >
                <i class="fa-solid fa-robot text-lg" aria-hidden="true"></i>
                <span class="hidden sm:inline">Ask</span>
            </a>
        @endif

        <script>
            (function () {
                const SEARCH_DEBOUNCE_MS = 1100;

                function wireAutoFilterForms(scopeRoot) {
                    const root = scopeRoot || document;
                    const forms = Array.from(root.querySelectorAll('form[method="get"]:not([data-auto-submit="off"])'));

                    forms.forEach((form) => {
                        if (form.dataset.autoSubmitBound === '1') {
                            return;
                        }
                        form.dataset.autoSubmitBound = '1';

                        const searchInputs = Array.from(form.querySelectorAll('input[name="q"], input[type="search"], input[data-auto-search="true"]'))
                            .filter((input) => !input.matches('[data-auto-submit="off"]'));
                        searchInputs.forEach((input) => {
                            input.addEventListener('input', () => {
                                window.clearTimeout(input._autoSearchTimer);
                                input._autoSearchTimer = window.setTimeout(() => form.requestSubmit(), SEARCH_DEBOUNCE_MS);
                            });
                        });

                        const autoControls = Array.from(form.querySelectorAll('select, input[type="date"], input[type="month"], input[type="number"], input[type="checkbox"], input[type="radio"]'))
                            .filter((el) => !el.matches('[data-auto-submit="off"]'));
                        autoControls.forEach((control) => {
                            control.addEventListener('change', () => form.requestSubmit());
                        });
                    });
                }

                function wireExportDropdowns(scopeRoot) {
                    const root = scopeRoot || document;
                    const forms = Array.from(root.querySelectorAll('form[method="get"]'));

                    forms.forEach((form) => {
                        if (form.dataset.exportDropdownBound === '1') {
                            return;
                        }
                        form.dataset.exportDropdownBound = '1';

                        if (form.querySelector('[data-export-dropdown-auto]')) {
                            return;
                        }

                        // Respect any existing manual export dropdown component.
                        if (form.querySelector('select[onchange*="window.location.href"]')) {
                            return;
                        }

                        const exportLinks = Array.from(form.querySelectorAll('a[href*="export="]'));
                        const exportButtons = Array.from(form.querySelectorAll('button[name="export"][value], input[type="submit"][name="export"][value]'));
                        const exportItems = [];

                        exportLinks.forEach((link) => {
                            const href = link.getAttribute('href') || '';
                            if (!href) return;
                            exportItems.push({
                                label: (link.textContent || '').trim().replace(/^Export\s+/i, '') || 'File',
                                mode: 'link',
                                value: href,
                                node: link,
                            });
                        });

                        exportButtons.forEach((button) => {
                            const value = button.getAttribute('value') || '';
                            if (!value) return;
                            exportItems.push({
                                label: (button.textContent || button.getAttribute('value') || '').trim().replace(/^Export\s+/i, '') || String(value).toUpperCase(),
                                mode: 'submit',
                                value,
                                node: button,
                            });
                        });

                        if (exportItems.length < 2) {
                            return;
                        }

                        const insertBeforeNode = exportItems[0].node;
                        const dropdown = document.createElement('select');
                        dropdown.setAttribute('data-export-dropdown-auto', '1');
                        dropdown.className = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 max-w-full';

                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = 'Export';
                        dropdown.appendChild(placeholder);

                        exportItems.forEach((item) => {
                            const option = document.createElement('option');
                            option.value = item.value;
                            option.textContent = item.label;
                            option.dataset.mode = item.mode;
                            dropdown.appendChild(option);
                        });

                        dropdown.addEventListener('change', () => {
                            const selected = dropdown.options[dropdown.selectedIndex];
                            if (!selected || !selected.value) return;

                            if (selected.dataset.mode === 'submit') {
                                let hidden = form.querySelector('input[name="export"][data-auto-export="1"]');
                                if (!hidden) {
                                    hidden = document.createElement('input');
                                    hidden.type = 'hidden';
                                    hidden.name = 'export';
                                    hidden.setAttribute('data-auto-export', '1');
                                    form.appendChild(hidden);
                                }
                                hidden.value = selected.value;
                                form.requestSubmit();
                            } else {
                                window.location.href = selected.value;
                            }

                            dropdown.selectedIndex = 0;
                        });

                        insertBeforeNode.parentNode?.insertBefore(dropdown, insertBeforeNode);
                        exportItems.forEach((item) => item.node.remove());
                    });
                }

                document.addEventListener('DOMContentLoaded', () => wireAutoFilterForms(document));
                document.addEventListener('turbo:load', () => wireAutoFilterForms(document));
                document.addEventListener('turbo:frame-load', (event) => wireAutoFilterForms(event.target));
                document.addEventListener('livewire:navigated', () => wireAutoFilterForms(document));
                document.addEventListener('alpine:navigated', () => wireAutoFilterForms(document));

                document.addEventListener('DOMContentLoaded', () => wireExportDropdowns(document));
                document.addEventListener('turbo:load', () => wireExportDropdowns(document));
                document.addEventListener('turbo:frame-load', (event) => wireExportDropdowns(event.target));
                document.addEventListener('livewire:navigated', () => wireExportDropdowns(document));
                document.addEventListener('alpine:navigated', () => wireExportDropdowns(document));
            })();
        </script>
        @stack('scripts')
    </body>
</html>
