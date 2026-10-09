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
    <link rel="manifest" href="{{ route('pwa.manifest.field') }}?v=4">
    <link rel="icon" href="{{ asset('pwa/meters-192.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('pwa/meters-192.png') }}">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f7f6; color: #0f172a; }
        header { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 14px; background: #0f766e; color: #fff; }
        header h1 { margin: 0; font-size: 1.05rem; }
        header p { margin: 2px 0 0; font-size: 0.75rem; opacity: 0.9; }
        header button { border: 0; border-radius: 999px; background: #fff; color: #0f766e; font-weight: 700; min-height: 36px; padding: 0 12px; }
        main { max-width: 28rem; margin: 0 auto; padding: 8px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px; margin-bottom: 8px; }
        .row { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
        .filters { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .filters .wide { grid-column: 1 / -1; }
        label { display: block; margin: 0; font-size: 0.68rem; font-weight: 600; color: #475569; }
        input, select { width: 100%; min-height: 40px; margin-top: 2px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 10px; font-size: 1rem; background: #fff; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px; }
        .actions button, .save { border: 0; border-radius: 8px; min-height: 40px; font-weight: 700; color: #fff; }
        #field-download { background: #0f172a; }
        #field-sync { background: #047857; }
        .muted { color: #64748b; font-size: 0.72rem; margin: 0; }
        .online { color: #047857; font-weight: 700; margin: 0; }
        .offline { color: #b45309; font-weight: 700; margin: 0; }
        article h3 { margin: 0; font-size: 0.95rem; }
        .meter { display: grid; grid-template-columns: 5.2rem minmax(0, 1fr) auto; gap: 6px; align-items: center; margin-top: 8px; }
        .meter .who { min-width: 0; }
        .meter .who strong { display: block; font-size: 0.82rem; }
        .meter input { margin: 0; }
        .meter.done input { background: #ecfdf5; color: #065f46; }
        .meter.done .save:disabled { background: #047857; opacity: 1; }
        .save { background: #0f172a; padding: 0 10px; font-size: 0.75rem; }
        .reset { display: flex; align-items: center; gap: 4px; margin: 4px 0 0; font-size: 0.68rem; color: #64748b; }
        .reset input { width: auto; min-height: 0; margin: 0; }
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
                <p id="field-downloaded" class="muted"></p>
                <div class="actions">
                    <button type="button" id="field-download">Download route</button>
                    <button type="button" id="field-sync">Send to office</button>
                </div>
                <p id="field-message" class="muted"></p>
                <p id="field-install-help" class="muted" hidden></p>
            </section>
            <section class="card filters">
                <div>
                    <label for="field-month">Month</label>
                    <input id="field-month" type="month">
                </div>
                <div>
                    <label for="field-search">Unit</label>
                    <input id="field-search" type="text" inputmode="search" placeholder="HSE" autocomplete="off">
                </div>
                <div class="wide">
                    <label for="field-property">Property</label>
                    <select id="field-property"></select>
                </div>
            </section>
            <div id="field-units"></div>
        </div>
    </main>
    <script src="{{ asset('js/field-readings.js') }}?v=7" defer></script>
</body>
</html>
