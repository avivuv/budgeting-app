import { APP_BASE } from './paths';

/*
 * Registers the SPA service worker (served as /app/sw.js, scope /app/) in production
 * builds: installable app and an offline page. Data is never cached (docs/15 §13).
 */
export function registerServiceWorker(): void {
    if (
        !import.meta.env.PROD ||
        typeof window === 'undefined' ||
        !('serviceWorker' in navigator)
    ) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register(`${APP_BASE}sw.js`, { scope: APP_BASE })
            .catch(() => {
                // Not fatal: the app works the same without it.
            });
    });
}
