<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @php
            $appDisplayName = \App\Models\LoanSystemSetting::getValue('app_display_name', config('app.name', 'Loan Management System'));
            $faviconUrl = \App\Models\LoanSystemSetting::getValue('favicon_url', '');
            $loanContactPhone = trim((string) \App\Models\LoanSystemSetting::getValue('company_phone', ''));
            $loanContactEmail = trim((string) \App\Models\LoanSystemSetting::getValue('company_email', ''));
            $loanContactAddress = trim((string) \App\Models\LoanSystemSetting::getValue('company_address', 'Nakuru, Kenya'));
            $loanContactAddress2 = trim((string) \App\Models\LoanSystemSetting::getValue('company_address_line_2', ''));
            $faviconRaw = trim((string) $faviconUrl);
            $faviconHref = match (true) {
                $faviconRaw === '' => asset('favicon.ico'),
                \Illuminate\Support\Str::startsWith($faviconRaw, ['http://', 'https://', '//']) => $faviconRaw,
                default => asset(ltrim($faviconRaw, '/')),
            };
            $faviconVersioned = $faviconHref.'?v='.rawurlencode(substr(md5($faviconHref), 0, 12));
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $appDisplayName }}</title>
        <link rel="icon" href="{{ $faviconVersioned }}">
        <link rel="shortcut icon" href="{{ $faviconVersioned }}">
        <link rel="apple-touch-icon" href="{{ $faviconVersioned }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            [x-cloak] { display: none !important; }
            .custom-scrollbar::-webkit-scrollbar {
                width: 6px;
            }
            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #475569;
                border-radius: 10px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #64748b;
            }
            /* Firefox + stable gutter */
            .custom-scrollbar {
                scrollbar-width: auto;
                scrollbar-color: #475569 transparent;
                scrollbar-gutter: stable;
            }

            @media print {
                html, body {
                    height: auto !important;
                    overflow: visible !important;
                    background: #fff !important;
                }

                @page {
                    size: landscape;
                    margin: 10mm;
                }

                aside,
                .no-print,
                #global-table-print-btn {
                    display: none !important;
                }

                #loan-app-shell {
                    display: none !important;
                }

                #global-print-surface {
                    display: block !important;
                }

                .h-full,
                .flex,
                .flex-1,
                main {
                    display: block !important;
                    height: auto !important;
                    min-height: 0 !important;
                    overflow: visible !important;
                }

                main > div {
                    padding: 0 !important;
                    margin: 0 !important;
                    width: 100% !important;
                    max-width: 100% !important;
                }

                table {
                    width: 100% !important;
                    table-layout: auto !important;
                    border-collapse: collapse !important;
                }

                thead {
                    display: table-header-group !important;
                }

                tr {
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                }

                th,
                td {
                    color: #000 !important;
                    font-size: 9pt !important;
                    background: #fff !important;
                    box-shadow: none !important;
                }
            }

            #global-print-surface {
                display: none;
                background: #fff;
                color: #111827;
                padding: 0;
            }
            #global-print-surface .print-head {
                border-bottom: 2px solid #1a5f7a;
                padding-bottom: 8px;
                margin-bottom: 10px;
            }
            #global-print-surface .print-head-grid {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 12px;
            }
            #global-print-surface .print-head-right {
                text-align: right;
                font-size: 10px;
                color: #475569;
                line-height: 1.35;
                max-width: 42%;
            }
            #global-print-surface .print-brand {
                font-size: 20px;
                font-weight: 700;
                color: #0f172a;
            }
            #global-print-surface .print-brand-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            #global-print-surface .print-logo {
                max-height: 28px;
                max-width: 120px;
                object-fit: contain;
            }
            #global-print-surface .print-title {
                font-size: 14px;
                font-weight: 700;
                margin-top: 4px;
            }
            #global-print-surface .print-meta {
                font-size: 10px;
                color: #64748b;
                margin-top: 2px;
            }
            #global-print-surface table {
                width: 100%;
                border-collapse: collapse;
                table-layout: auto;
            }
            #global-print-surface thead {
                display: table-header-group;
            }
            #global-print-surface tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            #global-print-surface th,
            #global-print-surface td {
                border: 1px solid #d1d5db;
                padding: 5px;
                font-size: 9px;
                vertical-align: top;
                word-break: break-word;
            }
            #global-print-surface th {
                background: #1a5f7a;
                color: #fff;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                font-size: 8px;
                text-align: left;
            }
            #global-print-surface .print-footer {
                margin-top: 8px;
                border-top: 1px solid #d1d5db;
                padding-top: 6px;
                font-size: 8px;
                color: #6b7280;
                font-style: italic;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
        </style>
    </head>
    <body
        class="font-sans antialiased h-screen overflow-hidden text-slate-900 dark:text-slate-100 selection:bg-indigo-500/30"
        x-data="{
            sidebarOpen: false,
            sidebarDesktopOpen: true,
            init() {
                const saved = window.localStorage.getItem('loan.sidebar.desktop.open');
                if (saved !== null) {
                    this.sidebarDesktopOpen = saved === '1';
                }
            },
            toggleDesktopSidebar() {
                this.sidebarDesktopOpen = !this.sidebarDesktopOpen;
                window.localStorage.setItem('loan.sidebar.desktop.open', this.sidebarDesktopOpen ? '1' : '0');
            }
        }"
    >
        <x-swal-flash />
        <div id="loan-app-shell" class="h-full flex bg-slate-50 dark:bg-slate-900">
            
            <!-- Loan Module Dedicated Sidebar -->
            @include('layouts.loan_sidebar')

            <!-- Main view container (Header, Content, Footer) -->
            <div class="flex-1 flex flex-col min-w-0 min-h-0 overflow-hidden bg-[#f4f7fa]">
                
                <!-- Dedicated Clean Topbar -->
                @include('layouts.loan_topbar')

                <!-- Scrollable Content Area (Topbar/Footer remain constant) -->
                <main class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto w-full custom-scrollbar overscroll-contain">
                    <div class="p-4 sm:p-6 lg:p-8">
                        {{ $slot }}
                    </div>
                </main>

                <!-- Dedicated Footer (constant) -->
                @include('layouts.loan_footer')

            </div>
        </div>
        <div id="global-print-surface" aria-hidden="true"></div>
        <script>
        (function () {
            function buildPrintSurface() {
                const printSurface = document.getElementById('global-print-surface');
                if (!printSurface) return;

                const heading = document.querySelector('main h1');
                const subtitle = document.querySelector('main h1 + p');
                const tables = Array.from(document.querySelectorAll('main table'))
                    .filter(function (table) { return table.offsetParent !== null; });
                const logoImg = document.querySelector('.sidebar img, .navbar img, header img, img[alt*="logo" i], img[src*="logo" i]');

                const titleText = heading ? heading.textContent.trim() : document.title;
                const subtitleText = subtitle ? subtitle.textContent.trim() : '';
                const generatedAt = new Date().toLocaleString();
                const logoSrc = logoImg && logoImg.getAttribute('src')
                    ? new URL(logoImg.getAttribute('src'), window.location.origin).href
                    : '';
                const year = new Date().getFullYear();
                const companyName = @json($appDisplayName);
                const contactLines = [
                    @json($loanContactPhone),
                    @json($loanContactEmail),
                    @json($loanContactAddress),
                    @json($loanContactAddress2)
                ].filter(Boolean);

                let html = ''
                    + '<div class="print-head">'
                    + '<div class="print-head-grid">'
                    + '<div class="print-head-left">'
                    + '<div class="print-brand-wrap">'
                    + (logoSrc !== '' ? '<img class="print-logo" src="' + logoSrc + '" alt="Logo">' : '')
                    + '<div class="print-brand">' + (companyName || 'Gaitho Loans') + '</div>'
                    + '</div>'
                    + '<div class="print-title">' + (titleText || 'Report') + '</div>'
                    + '<div class="print-meta">' + (subtitleText ? subtitleText + ' · ' : '') + 'Generated: ' + generatedAt + '</div>'
                    + '</div>'
                    + '<div class="print-head-right">' + (contactLines.join('<br>') || 'Nakuru, Kenya') + '</div>'
                    + '</div>'
                    + '</div>';

                if (tables.length === 0) {
                    html += '<p>No table found on this page.</p>';
                } else {
                    tables.forEach(function (table, idx) {
                        if (idx > 0) html += '<div style="height:10px"></div>';
                        html += table.outerHTML;
                    });
                }

                html += '<div class="print-footer">'
                    + '<span>Copyright © ' + year + ' ' + (companyName || 'Gaitho Loans') + '. All rights reserved.</span>'
                    + '<span>Generated by system print engine</span>'
                    + '</div>';

                printSurface.innerHTML = html;
            }

            function ensureGlobalPrintButton() {
                const isDedicatedPrintPage = window.location.pathname.includes('/print');
                const existing = document.getElementById('global-table-print-btn');

                if (isDedicatedPrintPage) {
                    if (existing) existing.remove();
                    return;
                }

                if (existing) return;

                const btn = document.createElement('button');
                btn.id = 'global-table-print-btn';
                btn.type = 'button';
                btn.textContent = 'Print';
                btn.setAttribute('aria-label', 'Print current page');
                btn.style.position = 'fixed';
                btn.style.right = '16px';
                btn.style.bottom = '16px';
                btn.style.zIndex = '60';
                btn.style.padding = '10px 16px';
                btn.style.borderRadius = '10px';
                btn.style.border = '1px solid #cbd5e1';
                btn.style.background = '#0f766e';
                btn.style.color = '#ffffff';
                btn.style.fontSize = '13px';
                btn.style.fontWeight = '600';
                btn.style.boxShadow = '0 6px 16px rgba(15, 23, 42, 0.18)';
                btn.style.cursor = 'pointer';
                btn.addEventListener('click', function () {
                    buildPrintSurface();
                    window.focus();
                    setTimeout(function () { window.print(); }, 50);
                });

                document.body.appendChild(btn);
            }

            document.addEventListener('DOMContentLoaded', ensureGlobalPrintButton);
            document.addEventListener('turbo:load', ensureGlobalPrintButton);
            document.addEventListener('livewire:navigated', ensureGlobalPrintButton);
            document.addEventListener('alpine:navigated', ensureGlobalPrintButton);
            window.addEventListener('popstate', ensureGlobalPrintButton);
            window.addEventListener('beforeprint', buildPrintSurface);
        })();
        </script>
    </body>
</html>
