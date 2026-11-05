import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api, ApiError, ensureCsrfCookie } from '@/core/api';
import type { Envelope, Me, MeFamily } from '@/core/api';

/*
 * The signed-in user, their families and permissions, from GET /me
 * (docs/15 §5). This is the only server data kept in Pinia; everything
 * else lives in TanStack Query.
 */
export type LoginResult = 'ok' | 'two-factor';

type LoginResponse = Partial<Envelope<Me>> & { two_factor?: boolean };

export const useSession = defineStore('session', () => {
    const user = ref<Me | null>(null);
    const loaded = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const families = computed<MeFamily[]>(() => user.value?.families ?? []);
    const currentFamilyId = computed(
        () => user.value?.current_family_id ?? null,
    );
    const currentFamily = computed<MeFamily | null>(
        () =>
            families.value.find(
                (family) => family.id === currentFamilyId.value,
            ) ?? null,
    );

    /** Permission of the active family, computed by the backend (BR-B09). */
    function can(ability: 'write' | 'manage'): boolean {
        return currentFamily.value?.can[ability] ?? false;
    }

    function setUser(me: Me | null): void {
        user.value = me;
        loaded.value = true;
    }

    /** GET /me; a missing session is not an error here. */
    async function load(): Promise<Me | null> {
        try {
            const response = await api.get<Envelope<Me>>('/me', {
                quiet: true,
            });
            setUser(response.data);
        } catch (error) {
            if (!(error instanceof ApiError) || error.status !== 401) {
                throw error;
            }
            setUser(null);
        }

        return user.value;
    }

    async function login(credentials: {
        email: string;
        password: string;
        remember: boolean;
    }): Promise<LoginResult> {
        await ensureCsrfCookie(true);
        const response = await api.post<LoginResponse>(
            '/auth/login',
            credentials,
            { quiet: true },
        );

        if (response.two_factor) {
            return 'two-factor';
        }

        setUser(response.data ?? null);

        return 'ok';
    }

    async function twoFactorChallenge(input: {
        code?: string;
        recovery_code?: string;
    }): Promise<void> {
        const response = await api.post<Envelope<Me>>(
            '/auth/two-factor-challenge',
            input,
            { quiet: true },
        );
        setUser(response.data);
    }

    async function register(input: {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
    }): Promise<void> {
        await ensureCsrfCookie(true);
        const response = await api.post<Envelope<Me>>('/auth/register', input, {
            quiet: true,
        });
        setUser(response.data);
    }

    async function logout(): Promise<void> {
        try {
            await api.post('/auth/logout', undefined, { quiet: true });
        } finally {
            setUser(null);
        }
    }

    async function switchFamily(familyId: number): Promise<void> {
        const response = await api.put<Envelope<Me>>(
            `/families/${familyId}/switch`,
        );
        setUser(response.data);
    }

    return {
        user,
        loaded,
        isAuthenticated,
        families,
        currentFamilyId,
        currentFamily,
        can,
        setUser,
        load,
        login,
        twoFactorChallenge,
        register,
        logout,
        switchFamily,
    };
});
