import type { DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { PiniaColada } from '@pinia/colada';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';

type PageModule = { default: DefineComponent };

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob<PageModule>('./Pages/**/*.vue');
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page().then((module) => module.default);
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(PiniaColada)
            .use(PrimeVue, {
                unstyled: true,
                ripple: false,
                license: import.meta.env.VITE_PRIMEUI_LICENSE,
            })
            .use(ToastService)
            .use(ConfirmationService)
            .directive('tooltip', Tooltip)
            .mount(el);
    },
    progress: {
        // Token, not a literal: the progress bar inherits --p-primary-500 from :root,
        // so it follows the palette and the .dark overrides automatically.
        color: 'var(--p-primary-500)',
    },
});
