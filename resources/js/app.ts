import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { registerServiceWorker } from '@/lib/pwa';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Feature pages live in modules/<feature>/pages ("transactions/Index" =>
// modules/transactions/pages/Index.vue); starter-kit pages stay in pages/.
const modulePages = import.meta.glob<DefineComponent>(
    './modules/*/pages/**/*.vue',
);
const legacyPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const [feature, ...rest] = name.split('/');
        const page =
            modulePages[`./modules/${feature}/pages/${rest.join('/')}.vue`] ??
            legacyPages[`./pages/${name}.vue`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page();
    },
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/') ||
                name.startsWith('onboarding/') ||
                name === 'family/AcceptInvitation':
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#2563EB',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Installable PWA with an offline page (production builds only).
registerServiceWorker();
