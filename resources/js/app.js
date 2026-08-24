import { createApp, h } from 'vue';
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3';
import AppSelect from './Components/AppSelect.vue';
import ToastHost from './Components/ToastHost.vue';
import TenantLayout from './Layouts/TenantLayout.vue';
import PlatformLayout from './Layouts/PlatformLayout.vue';
import PortalLayout from './Layouts/PortalLayout.vue';
import { applyTheme } from './theme';

createInertiaApp({
    title: (title) => (title ? `${title} · CoopOS` : 'CoopOS'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: false });
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page();
    },
    layout: (name) => {
        if (name.startsWith('Tenant/')) {
            return TenantLayout;
        }
        if (name.startsWith('Platform/')) {
            return PlatformLayout;
        }
        if (name.startsWith('Portal/')) {
            return PortalLayout;
        }

        return null;
    },
    setup({ el, App, props, plugin }) {
        applyTheme(props.initialPage?.props?.tenant?.theme);
        router.on('success', (event) => applyTheme(event.detail.page.props.tenant?.theme));

        const app = createApp({
            render: () => h('div', [h(App, props), h(ToastHost)]),
        });
        app.use(plugin);
        app.component('Link', Link);
        app.component('Head', Head);
        app.component('AppSelect', AppSelect);
        app.mount(el);
    },
    progress: { color: 'var(--coop-brand, #0f766e)' },
});
