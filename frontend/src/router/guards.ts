import type { RouteLocationRaw, RouteMeta } from 'vue-router';

/*
 * Where a navigation may go (docs/15 §2: guards for auth & family). Pure,
 * so it is tested without a router.
 *
 * Route meta:
 *  - public: open to everyone (e.g. reset password link)
 *  - guest: only for signed-out visitors (login, register)
 *  - verified: false → allowed before the email is verified
 *  - family: false → allowed without an active family (onboarding)
 */
export type GuardState = {
    authenticated: boolean;
    emailVerified: boolean;
    hasFamily: boolean;
};

export function decide(
    meta: RouteMeta,
    state: GuardState,
    fullPath: string,
): true | RouteLocationRaw {
    if (meta.public) {
        return true;
    }

    if (meta.guest) {
        return state.authenticated ? { name: 'home' } : true;
    }

    if (!state.authenticated) {
        return fullPath === '/'
            ? { name: 'login' }
            : { name: 'login', query: { redirect: fullPath } };
    }

    if (meta.verified !== false && !state.emailVerified) {
        return { name: 'verify-email' };
    }

    if (meta.family !== false && !state.hasFamily) {
        return { name: 'onboarding' };
    }

    return true;
}
