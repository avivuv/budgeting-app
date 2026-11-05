import type { RouteRecordRaw } from 'vue-router';

export const onboardingRoutes: RouteRecordRaw[] = [
    {
        path: '/onboarding',
        name: 'onboarding',
        component: () => import('./pages/CreateFamily.vue'),
        meta: {
            layout: 'auth',
            family: false,
            title: 'Buat keluarga',
            description:
                'Satu keluarga = satu buku keuangan bersama. Anggota bisa diundang nanti.',
        },
    },
];
