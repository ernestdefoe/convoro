import '../css/app.css';
import './echo';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { bootExtensions } from './lib/convoro-ext';

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
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
