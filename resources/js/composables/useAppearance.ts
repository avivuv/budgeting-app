import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { update as updateAppearanceSetting } from '@/actions/App/Http/Controllers/Settings/AppearanceController';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const DEFAULT_APPEARANCE: Appearance = 'system';

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = (): Appearance | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return localStorage.getItem('appearance') as Appearance | null;
    } catch {
        return null;
    }
};

const storeAppearance = (value: Appearance) => {
    try {
        localStorage.setItem('appearance', value);
    } catch {
        // Storage may be unavailable (private mode); the cookie still persists it.
    }

    setCookie('appearance', value);
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    updateTheme(getStoredAppearance() || DEFAULT_APPEARANCE);
};

/**
 * The root Blade view writes the server-resolved preference (user account,
 * otherwise cookie, otherwise "system") to localStorage before this runs.
 */
export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    updateTheme(getStoredAppearance() || DEFAULT_APPEARANCE);

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>(DEFAULT_APPEARANCE);

export function useAppearance(): UseAppearanceReturn {
    const page = usePage();

    onMounted(() => {
        appearance.value =
            page.props.appearance ||
            getStoredAppearance() ||
            DEFAULT_APPEARANCE;
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        storeAppearance(value);
        updateTheme(value);

        // Signed-in users keep the preference on their account (all devices).
        if (page.props.auth?.user) {
            router.patch(
                updateAppearanceSetting.url(),
                { appearance: value },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['appearance'],
                },
            );
        }
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
