import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { route as ziggyRoute } from 'ziggy-js';

createInertiaApp({
    title: (title) => (title ? `${title} — Relay` : 'Relay'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        app.config.globalProperties.$route = ziggyRoute;

        app.mount(el);
    },
    progress: {
        color: '#6741D9',
    },
});
