import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { setupI18n } from './i18n';

// Initialize theme immediately
import { useTheme } from './Composables/useTheme';
useTheme();

// Keep window.__page updated for composables that need access to current props
router.on('navigate', (event) => {
    window.__page = event.detail.page;
});


const appName = 'NetSendo';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Set initial page props for composables
        window.__page = props.initialPage;

        // Get initial locale from Inertia shared props
        const initialLocale = props.initialPage.props.locale?.current || 'en';
        const i18n = setupI18n(initialLocale);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
