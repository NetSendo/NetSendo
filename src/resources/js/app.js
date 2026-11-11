import '../css/app.css';
// Fonts are bundled with the app (no request to a font CDN): Figtree for the panel,
// Inter for the dashboard activity chart. Each weight file covers its subsets with
// unicode-range, so browsers download only the ones a page needs.
import '@fontsource/figtree/400.css';
import '@fontsource/figtree/500.css';
import '@fontsource/figtree/600.css';
import '@fontsource/figtree/700.css';
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { setupI18n } from './i18n';

// Initialize theme immediately
import { useTheme } from './Composables/useTheme';
useTheme();

// Keep window.__page updated for composables that need access to current props, and the
// interface language in step with what the server answered with
router.on('navigate', (event) => {
    window.__page = event.detail.page;

    const current = event.detail.page.props.locale?.current;
    const i18n = window.__VUE_I18N__?.global;
    if (current && i18n && i18n.locale.value !== current) {
        i18n.locale.value = current;
    }
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

        // Date and number helpers outside components read the language from here. Without it
        // they fell back to the props of the previous page, so dates stayed in the old
        // language after a switch until the next full load.
        window.__VUE_I18N__ = i18n;

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
