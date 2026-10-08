import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(appRoot, 'public', 'build');
const appPublic = path.resolve(path.join(appRoot, 'public'));

loadDotEnv(path.join(appRoot, '.env'));

function loadDotEnv(file) {
    if (!fs.existsSync(file)) {
        return;
    }
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
        const trimmed = line.trim();
        if (trimmed === '' || trimmed.startsWith('#')) {
            continue;
        }
        const eq = trimmed.indexOf('=');
        if (eq < 1) {
            continue;
        }
        const key = trimmed.slice(0, eq).trim();
        if (process.env[key]) {
            continue;
        }
        let value = trimmed.slice(eq + 1).trim();
        if (
            (value.startsWith('"') && value.endsWith('"'))
            || (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }
        process.env[key] = value;
    }
}

if (!fs.existsSync(source)) {
    console.error('public/build is missing. Run npm run build first.');
    process.exit(1);
}

function envPath(name) {
    const value = process.env[name];
    return typeof value === 'string' && value.trim() !== '' ? path.resolve(value.trim()) : null;
}

function discoverWebRoots() {
    const roots = [];
    const fromHtml = envPath('PUBLIC_HTML') || envPath('PUBLIC_HTML_PATH');
    if (fromHtml) {
        roots.push(fromHtml);
    }
    const fromBuild = envPath('PUBLIC_HTML_BUILD');
    if (fromBuild) {
        roots.push(path.dirname(fromBuild));
    }

    let dir = appRoot;
    for (let i = 0; i < 6; i++) {
        for (const name of ['public_html', 'www', 'httpdocs']) {
            roots.push(path.join(dir, name));
        }
        const parent = path.dirname(dir);
        if (parent === dir) {
            break;
        }
        dir = parent;
    }

    const home = os.homedir();
    if (home) {
        roots.push(path.join(home, 'public_html'));
    }

    const unique = [];
    const seen = new Set();
    for (const root of roots) {
        const resolved = path.resolve(root);
        if (seen.has(resolved)) {
            continue;
        }
        seen.add(resolved);
        unique.push(resolved);
    }

    return unique;
}

function isUsableWebRoot(webRoot) {
    if (!fs.existsSync(webRoot) || !fs.statSync(webRoot).isDirectory()) {
        return false;
    }

    const resolved = path.resolve(webRoot);
    if (resolved === appPublic || resolved === path.resolve(appRoot)) {
        return false;
    }

    return true;
}

function copyLiveAssets(webRoot) {
    const dest = path.join(webRoot, 'build');
    fs.rmSync(dest, { recursive: true, force: true });
    fs.cpSync(source, dest, { recursive: true });
    console.log('Copied Vite build to ' + dest);

    const pwaSource = path.join(appRoot, 'public', 'pwa');
    if (fs.existsSync(pwaSource)) {
        fs.cpSync(pwaSource, path.join(webRoot, 'pwa'), { recursive: true });
        console.log('Copied meter icons to ' + path.join(webRoot, 'pwa'));
    }

    const readingsSource = path.join(appRoot, 'public', 'js', 'field-readings.js');
    fs.mkdirSync(path.join(webRoot, 'js'), { recursive: true });
    if (fs.existsSync(readingsSource)) {
        fs.copyFileSync(readingsSource, path.join(webRoot, 'js', 'field-readings.js'));
        console.log('Copied field-readings.js into the live js folder');
    }
    const mediaSource = path.join(appRoot, 'public', 'js', 'maintenance-media.js');
    if (fs.existsSync(mediaSource)) {
        fs.copyFileSync(mediaSource, path.join(webRoot, 'js', 'maintenance-media.js'));
        console.log('Copied maintenance-media.js into the live js folder');
    }

    const swSource = path.join(appRoot, 'public', 'sw.js');
    if (fs.existsSync(swSource)) {
        fs.copyFileSync(swSource, path.join(webRoot, 'sw.js'));
        console.log('Copied sw.js into the live web root');
    }

    const hot = path.join(webRoot, 'hot');
    if (fs.existsSync(hot)) {
        fs.rmSync(hot, { force: true });
    }
}

let copied = false;
for (const webRoot of discoverWebRoots()) {
    if (!isUsableWebRoot(webRoot)) {
        continue;
    }
    copyLiveAssets(webRoot);
    copied = true;
    break;
}

if (!copied) {
    console.log('No public_html beside this app. Live copy skipped. Set PUBLIC_HTML to the cPanel web root if the site folder is not next to the property app.');
}
