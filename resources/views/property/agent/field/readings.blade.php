<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Meters">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Meters</title>
    <link rel="manifest" href="{{ route('pwa.manifest.field') }}">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f7f6; color: #0f172a; }
        header { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 14px; background: #0f766e; color: #fff; }
        header h1 { margin: 0; font-size: 1.05rem; }
        header p { margin: 2px 0 0; font-size: 0.75rem; opacity: 0.9; }
        header button { border: 0; border-radius: 999px; background: #fff; color: #0f766e; font-weight: 700; min-height: 36px; padding: 0 12px; }
        main { max-width: 32rem; margin: 0 auto; padding: 12px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; margin-bottom: 10px; }
        .row { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
        label { display: block; margin-top: 8px; font-size: 0.75rem; font-weight: 600; color: #475569; }
        input, select { width: 100%; min-height: 44px; margin-top: 4px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; font-size: 1rem; background: #fff; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px; }
        .actions button, .save { border: 0; border-radius: 12px; min-height: 44px; font-weight: 700; color: #fff; }
        #field-download { background: #0f172a; }
        #field-sync { background: #047857; }
        .muted { color: #64748b; font-size: 0.75rem; }
        .online { color: #047857; font-weight: 700; }
        .offline { color: #b45309; font-weight: 700; }
        article h3 { margin: 0; font-size: 0.95rem; }
        .meter { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; }
        .save { background: #0f172a; padding: 0 12px; font-size: 0.75rem; }
        .meter-actions { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-top: 8px; }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Meters</h1>
            <p>Field readings only</p>
        </div>
        <button type="button" id="field-install">Install</button>
    </header>
    <main>
        <div id="field-readings-app" data-pack-url="{{ $packUrl }}" data-sync-url="{{ $syncUrl }}">
            <section class="card">
                <div class="row">
                    <p id="field-net" class="online">Checking connection…</p>
                    <p id="field-pending" class="muted">0 waiting</p>
                </div>
                <p id="field-downloaded" class="muted">At the office, download the route once. After that this app works without internet.</p>
                <div class="actions">
                    <button type="button" id="field-download">Download route</button>
                    <button type="button" id="field-sync">Send to office</button>
                </div>
                <p id="field-message" class="muted"></p>
                <p id="field-install-help" class="muted">Install lives on this page. On Android or Chrome, tap Install in the green bar. If the phone does not ask, open the browser menu and choose Install app. On iPhone, open this page in Safari, tap Share, then Add to Home Screen.</p>
            </section>
            <section class="card">
                <label for="field-month">Billing month</label>
                <input id="field-month" type="month">
                <label for="field-property">Property</label>
                <select id="field-property"></select>
                <label for="field-search">Find unit</label>
                <input id="field-search" type="text" inputmode="search" placeholder="Unit number" autocomplete="off">
            </section>
            <div id="field-units"></div>
        </div>
    </main>
    <script src="{{ asset('js/field-readings.js') }}?v=3" defer></script>
</body>
</html>
