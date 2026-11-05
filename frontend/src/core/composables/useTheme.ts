import { computed, ref } from 'vue';
import { api } from '@/core/api';
import type { Appearance } from '@/core/api';

/*
 * Light / dark / system (docs/14). Applied from localStorage before the
 * first paint (index.html); signed-in users also keep it on their account
 * (PATCH /me) so every device follows.
 */
const STORAGE_KEY = 'appearance';

const appearance = ref<Appearance>(readStored());

function readStored(): Appearance {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
}

function prefersDark(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-color-scheme: dark)').matches
    );
}

export function applyTheme(value: Appearance): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle(
        'dark',
        value === 'dark' || (value === 'system' && prefersDark()),
    );
}

/** Follow the OS while the choice is "system". */
export function initializeTheme(): void {
    applyTheme(appearance.value);
    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => applyTheme(appearance.value));
}

export function useTheme() {
    function setLocal(value: Appearance): void {
        appearance.value = value;
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch {
            // Private mode: the account setting still keeps it.
        }
        applyTheme(value);
    }

    /** Change the theme; `save` also stores it on the account. */
    async function setAppearance(
        value: Appearance,
        save = true,
    ): Promise<void> {
        setLocal(value);

        if (save) {
            await api.patch('/me', { appearance: value });
        }
    }

    return {
        appearance,
        resolved: computed(() =>
            appearance.value === 'system'
                ? prefersDark()
                    ? 'dark'
                    : 'light'
                : appearance.value,
        ),
        setAppearance,
        /** Take the account's choice after login without saving it back. */
        adopt: (value: Appearance) => setLocal(value),
    };
}
