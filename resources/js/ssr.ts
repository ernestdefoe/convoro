import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Convoro';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
            ),
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) }).use(plugin);

            // Ziggy is only needed by a handful of Breeze auth/profile pages.
            // Register it with the server-shared config when present so route()
            // resolves during render; home/topic pages never call route(), so a
            // missing config simply means those pages still render fine.
            const ziggy = (page.props as Record<string, any>).ziggy;
            if (ziggy) {
                app.use(ZiggyVue, { ...ziggy, location: new URL(ziggy.location) });
            } else {
                app.use(ZiggyVue);
            }

            return app;
        },
    }),
    Number((globalThis as any).process?.env?.SSR_PORT) || 13714,
);
