(function () {
    const root = document.getElementById('field-readings-app');
    if (!root || !window.indexedDB) {
        return;
    }

    const packUrl = root.dataset.packUrl;
    const syncUrl = root.dataset.syncUrl;
    const netEl = document.getElementById('field-net');
    const pendingEl = document.getElementById('field-pending');
    const downloadedEl = document.getElementById('field-downloaded');
    const messageEl = document.getElementById('field-message');
    const monthEl = document.getElementById('field-month');
    const propertyEl = document.getElementById('field-property');
    const searchEl = document.getElementById('field-search');
    const unitsEl = document.getElementById('field-units');

    let pack = null;
    let queue = [];
    let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function db() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('passion-field-readings', 1);
            request.onupgradeneeded = () => {
                const database = request.result;
                if (!database.objectStoreNames.contains('pack')) {
                    database.createObjectStore('pack');
                }
                if (!database.objectStoreNames.contains('queue')) {
                    database.createObjectStore('queue', { keyPath: 'client_id' });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function storeGet(name, key) {
        const database = await db();
        return new Promise((resolve, reject) => {
            const tx = database.transaction(name, 'readonly');
            const request = tx.objectStore(name).get(key);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    async function storePut(name, value, key) {
        const database = await db();
        return new Promise((resolve, reject) => {
            const tx = database.transaction(name, 'readwrite');
            const request = key === undefined ? tx.objectStore(name).put(value) : tx.objectStore(name).put(value, key);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async function storeAll(name) {
        const database = await db();
        return new Promise((resolve, reject) => {
            const request = database.transaction(name, 'readonly').objectStore(name).getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    async function storeDelete(name, key) {
        const database = await db();
        return new Promise((resolve, reject) => {
            const request = database.transaction(name, 'readwrite').objectStore(name).delete(key);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    function setMessage(text) {
        messageEl.textContent = text || '';
    }

    function paintStatus() {
        const online = navigator.onLine;
        netEl.textContent = online ? 'Online' : 'Offline — readings stay on this phone';
        netEl.className = online ? 'online' : 'offline';
        pendingEl.textContent = queue.length + ' waiting to send';
        if (pack?.downloaded_at) {
            downloadedEl.textContent = (pack.properties || []).length + ' properties on this phone';
        }
    }

    function selectedProperty() {
        const id = Number(propertyEl.value || 0);
        return (pack?.properties || []).find((property) => Number(property.id) === id) || null;
    }

    function renderProperties() {
        const properties = pack?.properties || [];
        propertyEl.innerHTML = '';
        if (properties.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No properties on this route';
            propertyEl.appendChild(option);
            return;
        }
        properties.forEach((property) => {
            const option = document.createElement('option');
            option.value = String(property.id);
            option.textContent = property.name;
            propertyEl.appendChild(option);
        });
    }

    function queuedFor(unitId, meter, month) {
        return queue.find((row) => Number(row.property_unit_id) === Number(unitId) && row.meter === meter && row.billing_month === month);
    }

    function renderUnits() {
        const property = selectedProperty();
        const month = monthEl.value;
        const query = (searchEl.value || '').trim().toLowerCase();
        unitsEl.innerHTML = '';
        if (!property) {
            unitsEl.innerHTML = '<p class="card muted">Download your route at the office. The units then stay on this phone.</p>';
            return;
        }
        const units = (property.units || []).filter((unit) => query === '' || String(unit.label).toLowerCase().includes(query));
        if (units.length === 0) {
            unitsEl.innerHTML = '<p class="muted">No unit matches that search.</p>';
            return;
        }
        units.forEach((unit) => {
            const card = document.createElement('article');
            card.className = 'card';
            const title = document.createElement('h3');
            title.textContent = unit.label;
            card.appendChild(title);
            const meters = unit.meters || [];
            if (meters.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = 'No meter charges';
                card.appendChild(empty);
            }
            meters.forEach((meter) => {
                const saved = queuedFor(unit.id, meter.kind, month);
                const already = Boolean(meter.already_recorded) && !saved;
                const row = document.createElement('div');
                row.className = already ? 'meter done' : 'meter';
                const who = document.createElement('div');
                who.className = 'who';
                const name = document.createElement('strong');
                name.textContent = meter.label;
                who.appendChild(name);
                const previous = document.createElement('span');
                previous.className = 'muted';
                previous.textContent = 'Prev ' + Number(meter.previous || 0);
                who.appendChild(previous);
                if (!already) {
                    const reset = document.createElement('input');
                    reset.type = 'checkbox';
                    reset.checked = Boolean(saved?.is_meter_reset);
                    const resetLabel = document.createElement('label');
                    resetLabel.className = 'reset';
                    resetLabel.appendChild(reset);
                    resetLabel.appendChild(document.createTextNode('Replaced'));
                    who.appendChild(resetLabel);
                    row.appendChild(who);
                    const input = document.createElement('input');
                    input.type = 'number';
                    input.inputMode = 'decimal';
                    input.step = '0.001';
                    input.min = '0';
                    input.placeholder = 'Now';
                    input.setAttribute('aria-label', meter.label + ' current reading');
                    input.value = saved ? String(saved.current_reading) : '';
                    row.appendChild(input);
                    const save = document.createElement('button');
                    save.type = 'button';
                    save.className = 'save';
                    save.textContent = saved ? 'Saved' : 'Save';
                    save.addEventListener('click', () => saveLocal(property, unit, meter, month, input.value, reset.checked, save));
                    row.appendChild(save);
                } else {
                    row.appendChild(who);
                    const input = document.createElement('input');
                    input.type = 'number';
                    input.readOnly = true;
                    input.setAttribute('aria-label', meter.label + ' already recorded');
                    input.value = meter.current === null || meter.current === undefined ? '' : String(meter.current);
                    row.appendChild(input);
                    const save = document.createElement('button');
                    save.type = 'button';
                    save.className = 'save';
                    save.disabled = true;
                    save.textContent = 'Already in';
                    row.appendChild(save);
                }
                card.appendChild(row);
            });
            unitsEl.appendChild(card);
        });
    }

    async function saveLocal(property, unit, meter, month, rawValue, reset, button) {
        if (!month) {
            setMessage('Choose the billing month first.');
            return;
        }
        const current = Number(rawValue);
        if (!Number.isFinite(current) || current < 0 || rawValue === '') {
            setMessage('Enter the current meter number.');
            return;
        }
        const clientId = [unit.id, meter.kind, month].join(':');
        await storePut('queue', {
            client_id: clientId,
            property_unit_id: unit.id,
            property_name: property.name,
            unit_label: unit.label,
            meter: meter.kind,
            billing_month: month,
            previous_reading: Number(meter.previous || 0),
            current_reading: current,
            rate_per_unit: Number(meter.rate || 0),
            fixed_charge: Number(meter.fixed || 0),
            is_meter_reset: reset,
            label: meter.label,
        });
        queue = await storeAll('queue');
        button.textContent = 'Saved on phone';
        paintStatus();
        setMessage(unit.label + ' ' + meter.label + ' is on this phone. Send to office when you have internet.');
        if (navigator.onLine) {
            syncQueue();
        }
    }

    async function downloadPack() {
        if (!navigator.onLine) {
            setMessage('You need internet to download the route. Readings already on the phone are safe.');
            return;
        }
        setMessage('Downloading units and last readings…');
        const month = monthEl.value;
        const response = await fetch(packUrl + (month ? '?billing_month=' + encodeURIComponent(month) : ''), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            setMessage(response.status === 401 || response.status === 419 ? 'Sign in again, then download the route.' : 'Could not download the route.');
            return;
        }
        const data = await response.json();
        if (data.csrf) {
            csrf = data.csrf;
        }
        data.downloaded_at = new Date().toLocaleString();
        await storePut('pack', data, 'current');
        pack = data;
        if (data.billing_month) {
            monthEl.value = data.billing_month;
        }
        renderProperties();
        renderUnits();
        paintStatus();
        setMessage((data.properties || []).length ? 'Route ready.' : 'No properties assigned.');
    }

    async function syncQueue() {
        queue = await storeAll('queue');
        paintStatus();
        if (queue.length === 0) {
            setMessage('Nothing is waiting to send.');
            return;
        }
        if (!navigator.onLine) {
            setMessage('Still offline. These readings stay on the phone until you reach the office.');
            return;
        }
        const tokenResponse = await fetch(packUrl + '?billing_month=' + encodeURIComponent(monthEl.value || ''), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (tokenResponse.status === 401 || tokenResponse.status === 419) {
            setMessage('Sign in at the office, open this page, then send again. Nothing was lost.');
            return;
        }
        if (tokenResponse.ok) {
            const tokenData = await tokenResponse.json();
            if (tokenData.csrf) {
                csrf = tokenData.csrf;
            }
        }
        setMessage('Sending ' + queue.length + ' reading(s)…');
        const response = await fetch(syncUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                readings: queue.map((row) => ({
                    client_id: row.client_id,
                    property_unit_id: row.property_unit_id,
                    meter: row.meter,
                    billing_month: row.billing_month,
                    previous_reading: row.previous_reading,
                    current_reading: row.current_reading,
                    rate_per_unit: row.rate_per_unit,
                    fixed_charge: row.fixed_charge,
                    is_meter_reset: row.is_meter_reset,
                    label: row.label,
                })),
            }),
        });
        if (response.status === 419 || response.status === 401) {
            setMessage('Sign in at the office, open this page, then send again. Nothing was lost.');
            return;
        }
        if (!response.ok) {
            setMessage('The office could not take the readings yet. They are still on this phone.');
            return;
        }
        const data = await response.json();
        let sent = 0;
        let failed = 0;
        for (const result of data.results || []) {
            if (result.status === 'saved') {
                await storeDelete('queue', result.client_id);
                sent++;
            } else {
                failed++;
            }
        }
        queue = await storeAll('queue');
        renderUnits();
        paintStatus();
        setMessage(sent + ' saved in the office' + (failed ? '. ' + failed + ' still on this phone — check the meter numbers.' : '.'));
    }

    document.getElementById('field-download')?.addEventListener('click', () => {
        downloadPack().catch(() => setMessage('Download failed. Try again on the office network.'));
    });
    document.getElementById('field-sync')?.addEventListener('click', () => {
        syncQueue().catch(() => setMessage('Send failed. The readings are still on this phone.'));
    });
    propertyEl.addEventListener('change', renderUnits);
    searchEl.addEventListener('input', renderUnits);
    monthEl.addEventListener('change', () => {
        if (navigator.onLine) {
            downloadPack().catch(() => setMessage('Could not load that month. Readings already on the phone are safe.'));
            return;
        }
        if (pack && pack.billing_month && pack.billing_month !== monthEl.value) {
            setMessage('This phone has ' + pack.billing_month + '. Download ' + monthEl.value + ' when you are online.');
        }
        renderUnits();
    });
    window.addEventListener('online', () => {
        paintStatus();
        syncQueue().catch(() => {});
    });
    window.addEventListener('offline', paintStatus);

    const installBtn = document.getElementById('field-install');
    const installHelp = document.getElementById('field-install-help');
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    let installPrompt = null;
    if (standalone) {
        if (installBtn) {
            installBtn.hidden = true;
        }
        if (installHelp) {
            installHelp.hidden = true;
        }
    } else if (installBtn) {
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            installPrompt = event;
            installBtn.hidden = false;
        });
        installBtn.addEventListener('click', async () => {
            if (!installPrompt) {
                if (installHelp) {
                    installHelp.hidden = false;
                }
                setMessage('The browser did not open an install box. Use the browser menu and choose Install app, or on iPhone use Share then Add to Home Screen.');
                return;
            }
            installPrompt.prompt();
            await installPrompt.userChoice;
            installPrompt = null;
            installBtn.hidden = true;
        });
        if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
            installBtn.textContent = 'Add';
        }
    }
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            setMessage('Install is blocked until this page can register its app service. Reload once on the office network.');
        });
    }

    db().then(async () => {
        pack = await storeGet('pack', 'current');
        queue = await storeAll('queue');
        monthEl.value = pack?.billing_month || new Date().toISOString().slice(0, 7);
        renderProperties();
        renderUnits();
        paintStatus();
        if (navigator.onLine && queue.length > 0) {
            syncQueue().catch(() => {});
        }
    }).catch(() => setMessage('This browser cannot store readings on the phone.'));
})();
