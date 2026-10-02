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
    copied = true;
    break;
}

if (!copied) {
    console.log('No public_html beside this app. Live copy skipped.');
}
