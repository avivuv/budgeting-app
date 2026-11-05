import { createRouter, createWebHistory } from 'vue-router';
import type { RouteRecordRaw } from 'vue-router';
import { APP_BASE } from '@/core/lib/paths';
import { useSession } from '@/core/stores/session';
import { authRoutes } from '@/modules/auth';
import { dashboardRoutes } from '@/modules/dashboard';
import { onboardingRoutes } from '@/modules/onboarding';
import { decide } from './guards';

declare module 'vue-router' {
    interface RouteMeta {
        layout?: 'app' | 'auth';
        title?: string;
        description?: string;
        public?: boolean;
        guest?: boolean;
        verified?: boolean;
        family?: boolean;
    }
}

const routes: RouteRecordRaw[] = [
    ...authRoutes,
    ...onboardingRoutes,
    ...dashboardRoutes,
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('./NotFound.vue'),
        meta: {
            public: true,
            layout: 'auth',
            title: 'Halaman tidak ditemukan',
        },
    },
];

export const router = createRouter({
    history: createWebHistory(APP_BASE),
    routes,
    scrollBehavior: (_to, _from, saved) => saved ?? { top: 0 },
});

router.beforeEach(async (to) => {
    const session = useSession();

    if (!session.loaded) {
        await session.load();
    }

    return decide(
        to.meta,
        {
            authenticated: session.isAuthenticated,
            emailVerified: session.user?.email_verified ?? false,
            hasFamily: session.currentFamilyId !== null,
        },
        to.fullPath,
    );
});

router.afterEach((to) => {
    document.title = to.meta.title
        ? `${to.meta.title} - Budgeting`
        : 'Budgeting';
});
