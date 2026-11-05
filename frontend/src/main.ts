import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import { toast } from 'vue-sonner';
import '@/assets/app.css';
import { setApiHandlers } from '@/core/api';
import { initializeTheme, useTheme } from '@/core/composables/useTheme';
import { registerServiceWorker } from '@/core/lib/pwa';
import { useSession } from '@/core/stores/session';
import App from './App.vue';
import { router } from './router';

const app = createApp(App);
const pinia = createPinia();
const queryClient = new QueryClient({
    defaultOptions: {
        queries: { staleTime: 30_000, retry: 1, refetchOnWindowFocus: false },
    },
});

app.use(pinia);
app.use(router);
app.use(VueQueryPlugin, { queryClient });

const session = useSession(pinia);

// Global reactions to API errors (docs/15 §5.1).
setApiHandlers({
    familyId: () => session.currentFamilyId,
    onUnauthenticated: () => {
        session.setUser(null);
        queryClient.clear();
        const current = router.currentRoute.value;
        if (current.meta.guest || current.meta.public) {
            return;
        }
        void router.push({
            name: 'login',
            query: { redirect: router.currentRoute.value.fullPath },
        });
    },
    onNoFamily: () => void router.push({ name: 'onboarding' }),
    onNotice: (message) => toast.error(message),
});

// The account's theme wins once the user is known.
session.$subscribe((_mutation, state) => {
    if (state.user) {
        useTheme().adopt(state.user.appearance);
    }
});

initializeTheme();
registerServiceWorker();

app.mount('#app');
