import type { RouteRecordRaw } from 'vue-router';

/*
 * Signing in and out (docs/api-contracts/auth.md). `guest` pages are only
 * for signed-out visitors; `public` pages work either way.
 */
export const authRoutes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('./pages/Login.vue'),
        meta: {
            layout: 'auth',
            guest: true,
            title: 'Masuk ke akunmu',
            description: 'Masukkan email dan password kamu.',
        },
    },
    {
        path: '/two-factor-challenge',
        name: 'two-factor',
        component: () => import('./pages/TwoFactorChallenge.vue'),
        meta: {
            layout: 'auth',
            guest: true,
            title: 'Kode autentikasi',
            description: 'Masukkan kode dari aplikasi authenticator kamu.',
        },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('./pages/Register.vue'),
        meta: {
            layout: 'auth',
            guest: true,
            title: 'Buat akun',
            description: 'Isi data di bawah untuk mendaftar.',
        },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('./pages/ForgotPassword.vue'),
        meta: {
            layout: 'auth',
            guest: true,
            title: 'Lupa password',
            description: 'Kami kirim link untuk membuat password baru.',
        },
    },
    {
        path: '/reset-password/:token',
        name: 'reset-password',
        component: () => import('./pages/ResetPassword.vue'),
        meta: { layout: 'auth', public: true, title: 'Buat password baru' },
    },
    {
        path: '/verify-email',
        name: 'verify-email',
        component: () => import('./pages/VerifyEmail.vue'),
        meta: {
            layout: 'auth',
            verified: false,
            family: false,
            title: 'Verifikasi email',
        },
    },
];
