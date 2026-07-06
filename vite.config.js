import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    ssr: {
        // Inline all deps into bootstrap/ssr/ssr.js so the Node sidecar is
        // self-contained — prod has no node_modules (assets are built locally
        // and only the bundle is shipped).
        noExternal: true,
    },
    build: {
        // Wipe public/build (and bootstrap/ssr) before each build so stale,
        // manifest-orphaned hashed assets never pile up across builds — the
        // release zip was accumulating ~16k dead files this way.
        emptyOutDir: true,
    },
});
