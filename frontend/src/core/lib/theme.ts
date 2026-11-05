/*
 * Reads the design tokens (docs/14-ui-design-system.md) at runtime so charts
 * follow light/dark mode without hardcoded colours.
 */

export function cssVar(name: string): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return getComputedStyle(document.documentElement)
        .getPropertyValue(`--${name}`)
        .trim();
}

/** Category palette: --chart-1 … --chart-10. */
export function chartPalette(): string[] {
    return Array.from({ length: 10 }, (_, i) => cssVar(`chart-${i + 1}`));
}

/** Calls back whenever the theme switches between light and dark. */
export function onThemeChange(callback: () => void): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const observer = new MutationObserver(callback);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });

    return () => observer.disconnect();
}
