import type { CapacitorConfig } from '@capacitor/cli';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

function loadCapacitorEnv(): Record<string, string> {
    const path = resolve(process.cwd(), 'capacitor.env');
    if (!existsSync(path)) {
        return {};
    }

    const vars: Record<string, string> = {};
    for (const line of readFileSync(path, 'utf8').split('\n')) {
        const trimmed = line.trim();
        if (trimmed === '' || trimmed.startsWith('#')) {
            continue;
        }
        const eq = trimmed.indexOf('=');
        if (eq <= 0) {
            continue;
        }
        const key = trimmed.slice(0, eq).trim();
        let value = trimmed.slice(eq + 1).trim();
        if (
            (value.startsWith('"') && value.endsWith('"'))
            || (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }
        vars[key] = value;
    }

    return vars;
}

const env = {
    ...loadCapacitorEnv(),
    ...process.env,
};

const serverUrl = (env.CAPACITOR_SERVER_URL ?? '').trim().replace(/\/$/, '');
const allowCleartext = (env.CAPACITOR_ALLOW_CLEARTEXT ?? '').toLowerCase() === 'true';

const config: CapacitorConfig = {
    appId: 'ke.co.gaithoproperties.portal',
    appName: 'Gaitho Property Agency',
    webDir: 'mobile/www',
    android: {
        allowMixedContent: allowCleartext,
    },
};

if (serverUrl !== '') {
    config.server = {
        url: serverUrl,
        cleartext: allowCleartext,
        androidScheme: allowCleartext ? 'http' : 'https',
    };
}

export default config;
