import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(appRoot, 'public', 'build');

const destinations = [
    process.env.PUBLIC_HTML_BUILD,
    path.resolve(appRoot, '..', 'public_html', 'build'),
].filter((value) => typeof value === 'string' && value.trim() !== '');

if (!fs.existsSync(source)) {
    console.error('public/build is missing. Run npm run build first.');
    process.exit(1);
}

let copied = false;

for (const dest of destinations) {
    const parent = path.dirname(dest);
    if (!fs.existsSync(parent)) {
        continue;
    }

    fs.rmSync(dest, { recursive: true, force: true });
    fs.cpSync(source, dest, { recursive: true });
    console.log('Copied Vite build to ' + dest);

    const webRoot = parent;
    const pwaSource = path.join(appRoot, 'public', 'pwa');
    if (fs.existsSync(pwaSource)) {
        fs.cpSync(pwaSource, path.join(webRoot, 'pwa'), { recursive: true });
        console.log('Copied meter icons to ' + path.join(webRoot, 'pwa'));
    }
    const readingsSource = path.join(appRoot, 'public', 'js', 'field-readings.js');
    if (fs.existsSync(readingsSource)) {
        fs.mkdirSync(path.join(webRoot, 'js'), { recursive: true });
        fs.copyFileSync(readingsSource, path.join(webRoot, 'js', 'field-readings.js'));
        console.log('Copied field-readings.js into the live js folder');
    }
    const swSource = path.join(appRoot, 'public', 'sw.js');
    if (fs.existsSync(swSource)) {
        fs.copyFileSync(swSource, path.join(webRoot, 'sw.js'));
        console.log('Copied sw.js into the live web root');
    }
    copied = true;
    break;
}

if (!copied) {
    console.log('No public_html beside this app. Live copy skipped.');
}
