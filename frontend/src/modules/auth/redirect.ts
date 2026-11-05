import type { RouteLocationNormalizedLoaded, Router } from 'vue-router';

/**
 * After signing in, go back to the page that asked for it (?redirect=),
 * only when it is a path inside the SPA.
 */
export async function redirectAfterLogin(
    router: Router,
    route: RouteLocationNormalizedLoaded,
): Promise<void> {
    const target = route.query.redirect;

    if (
        typeof target === 'string' &&
        target.startsWith('/') &&
        !target.startsWith('//')
    ) {
        await router.push(target);

        return;
    }

    await router.push({ name: 'home' });
}
