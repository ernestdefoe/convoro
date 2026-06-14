import '../css/app.css';
import './echo';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { bootExtensions } from './lib/convoro-ext';
import { initEmbeds } from './lib/embeds';

const appName = import.meta.env.VITE_APP_NAME || 'Convoro';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Expose window.Convoro + load enabled extensions' prebuilt bundles
        // (shared as the `extAssets` Inertia prop) before mounting.
        const shared = (props.initialPage.props as Record<string, unknown>) ?? {};
        bootExtensions((shared.extAssets as { id: string; url: string }[]) ?? []);
        initEmbeds();

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#5b5bd6',
    },
});

// Register the PWA service worker (installability + push). Safe no-op if unsupported.
if ('serviceWorker' in navigator) {
    // When an UPDATED worker takes control, reload once so a client stuck on a
    // stale worker/shell rescues itself (guarded against loops). Crucially we
    // only do this when a controller ALREADY existed at startup — on a first
    // visit the initial install also fires `controllerchange`, and reloading
    // there forces every new visitor (and Lighthouse/PSI) through a full second
    // page load, destroying first-load performance.
    let refreshing = false;
    const hadController = !!navigator.serviceWorker.controller;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshing || !hadController) return;
        refreshing = true;
        window.location.reload();
    });

    window.addEventListener('load', () => {
        // updateViaCache:'none' forces the sw.js script to bypass the HTTP cache on
        // every update check, so new workers are picked up promptly.
        navigator.serviceWorker
            .register('/sw.js', { updateViaCache: 'none' })
            .then((reg) => {
                reg.update().catch(() => {});
                // Re-check for a new worker whenever the tab regains focus.
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') reg.update().catch(() => {});
                });
            })
            .catch(() => {});
    });
}
