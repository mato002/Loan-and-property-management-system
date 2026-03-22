import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    const port = parseInt(env.VITE_PORT || '5173', 10);
    const bindAll = env.VITE_DEV_BIND === 'true' || env.VITE_DEV_BIND === '1';
    const hmrHost = env.VITE_DEV_HOST?.trim() || null;

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
        server: {
            host: bindAll ? '0.0.0.0' : true,
            port,
            strictPort: true,
            ...(hmrHost
                ? {
                      hmr: {
                          host: hmrHost,
                          clientPort: port,
                      },
                  }
                : {}),
        },
    };
});
