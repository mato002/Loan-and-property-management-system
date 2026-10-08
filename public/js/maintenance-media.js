function mediaBag(root) {
    if (!root._maintenanceFiles) {
        root._maintenanceFiles = [];
    }
    return root._maintenanceFiles;
}

function syncLibrary(root) {
    const library = root.querySelector('[data-media-library]');
    if (!library) {
        return;
    }
    const dt = new DataTransfer();
    mediaBag(root).forEach((file) => dt.items.add(file));
    root._syncingLibrary = true;
    library.files = dt.files;
    root._syncingLibrary = false;
    renderPreview(root);
}

function appendFiles(root, list) {
    const bag = mediaBag(root);
    Array.from(list || []).forEach((file) => {
        if (!(file instanceof File) || file.size <= 0) {
            return;
        }
        bag.push(file);
    });
    syncLibrary(root);
}

function renderPreview(root) {
    const mount = root.querySelector('[data-media-preview]');
    if (!mount) {
        return;
    }
    const bag = mediaBag(root);
    mount.innerHTML = '';
    bag.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-800/80';
        const isVideo = file.type.startsWith('video/');
        const thumb = document.createElement(isVideo ? 'video' : 'img');
        thumb.className = 'h-10 w-10 shrink-0 rounded object-cover bg-slate-200';
        thumb.src = URL.createObjectURL(file);
        if (isVideo) {
            thumb.muted = true;
            thumb.playsInline = true;
        }
        const label = document.createElement('span');
        label.className = 'min-w-0 flex-1 truncate text-slate-700 dark:text-slate-200';
        label.textContent = file.name || (isVideo ? 'Video' : 'Photo');
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'shrink-0 rounded px-1.5 py-0.5 font-semibold text-slate-500 hover:bg-white hover:text-rose-700';
        remove.textContent = 'Remove';
        remove.addEventListener('click', () => {
            URL.revokeObjectURL(thumb.src);
            bag.splice(index, 1);
            syncLibrary(root);
        });
        item.append(thumb, label, remove);
        mount.appendChild(item);
    });
}

function stopStream(stream) {
    (stream && stream.getTracks ? stream.getTracks() : []).forEach((track) => track.stop());
}

function livePanel(root) {
    return root.querySelector('[data-media-live-panel]');
}

async function openLive(root) {
    const panel = livePanel(root);
    const video = panel ? panel.querySelector('[data-media-live-video]') : null;
    if (!panel || !video || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        return false;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: true,
        });
        video.srcObject = stream;
        await video.play().catch(function () {});
        panel.classList.remove('hidden');
        root._liveStream = stream;
        root._liveRecorder = null;
        const recLabel = panel.querySelector('[data-media-rec-label]');
        if (recLabel) {
            recLabel.textContent = 'Start recording';
        }
        return true;
    } catch (e) {
        return false;
    }
}

function closeLive(root) {
    const panel = livePanel(root);
    stopStream(root._liveStream);
    root._liveStream = null;
    if (root._liveRecorder && root._liveRecorder.state !== 'inactive') {
        root._liveRecorder.stop();
    }
    root._liveRecorder = null;
    const video = panel ? panel.querySelector('[data-media-live-video]') : null;
    if (video) {
        video.srcObject = null;
    }
    if (panel) {
        panel.classList.add('hidden');
    }
}

function capturePhoto(root) {
    const panel = livePanel(root);
    const video = panel ? panel.querySelector('[data-media-live-video]') : null;
    if (!video || video.videoWidth < 2) {
        return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }
    ctx.drawImage(video, 0, 0);
    canvas.toBlob(function (blob) {
        if (!blob) {
            return;
        }
        const stamp = new Date().toISOString().replace(/[:.]/g, '-');
        appendFiles(root, [new File([blob], 'field-photo-' + stamp + '.jpg', { type: blob.type || 'image/jpeg' })]);
        closeLive(root);
    }, 'image/jpeg', 0.9);
}

function toggleRecording(root) {
    const panel = livePanel(root);
    if (root._liveRecorder && root._liveRecorder.state === 'recording') {
        root._liveRecorder.stop();
        return;
    }
    const stream = root._liveStream;
    if (!stream || typeof MediaRecorder === 'undefined') {
        return;
    }
    const mime = MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus')
        ? 'video/webm;codecs=vp8,opus'
        : (MediaRecorder.isTypeSupported('video/mp4') ? 'video/mp4' : '');
    const chunks = [];
    const recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
    recorder.addEventListener('dataavailable', function (event) {
        if (event.data && event.data.size > 0) {
            chunks.push(event.data);
        }
    });
    recorder.addEventListener('stop', function () {
        const type = recorder.mimeType || 'video/webm';
        const ext = type.indexOf('mp4') >= 0 ? 'mp4' : 'webm';
        const stamp = new Date().toISOString().replace(/[:.]/g, '-');
        appendFiles(root, [new File(chunks, 'field-video-' + stamp + '.' + ext, { type: type })]);
        closeLive(root);
    });
    recorder.start();
    root._liveRecorder = recorder;
    const recLabel = panel ? panel.querySelector('[data-media-rec-label]') : null;
    if (recLabel) {
        recLabel.textContent = 'Stop recording';
    }
}

function bindMaintenanceMedia() {
    if (document.documentElement.dataset.maintenanceMediaBound === '1') {
        return;
    }
    document.documentElement.dataset.maintenanceMediaBound = '1';

    document.addEventListener('click', function (event) {
        const open = event.target.closest('[data-media-open]');
        const root = event.target.closest('[data-maintenance-media]');
        if (open && root) {
            event.preventDefault();
            openLive(root);
            return;
        }
        if (!root) {
            return;
        }
        if (event.target.closest('[data-media-close]')) {
            event.preventDefault();
            closeLive(root);
            return;
        }
        if (event.target.closest('[data-media-shot]')) {
            event.preventDefault();
            capturePhoto(root);
            return;
        }
        if (event.target.closest('[data-media-rec]')) {
            event.preventDefault();
            toggleRecording(root);
        }
    });

    document.addEventListener('change', function (event) {
        const capture = event.target.closest('[data-media-capture]');
        const library = event.target.closest('[data-media-library]');
        const root = event.target.closest('[data-maintenance-media]');
        if (!root) {
            return;
        }
        if (capture) {
            appendFiles(root, capture.files);
            capture.value = '';
            return;
        }
        if (library && !root._syncingLibrary) {
            appendFiles(root, library.files);
        }
    });
}

bindMaintenanceMedia();
