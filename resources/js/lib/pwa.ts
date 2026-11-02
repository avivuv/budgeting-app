/*
 * Registers the service worker (public/sw.js) in production builds so the app
 * is installable and shows an offline page. Data is never cached (docs/15 §11).
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
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Not fatal: the app works the same without it.
        });
    });
}
