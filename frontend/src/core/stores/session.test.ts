import { createPinia, setActivePinia } from 'pinia';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { Me } from '@/core/api';
import { useSession } from './session';

/* The session store against a fake API: login steps, permissions, family switch. */
const me = (overrides: Partial<Me> = {}): Me => ({
    id: 1,
    name: 'Ayah',
    email: 'ayah@budgeting.test',
    email_verified: true,
    two_factor_enabled: false,
    appearance: 'system',
    current_family_id: 1,
    families: [
        {
            id: 1,
            name: 'Keluarga Golden',
            role: 'owner',
            role_label: 'Pemilik',
            can: { write: true, manage: true },
        },
        {
            id: 2,
            name: 'Keluarga Kakek',
            role: 'viewer',
            role_label: 'Hanya lihat',
            can: { write: false, manage: false },
        },
    ],
    unread_notifications: 0,
    ...overrides,
});

let routes: Record<string, () => Response>;

function json(status: number, body: unknown): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

beforeEach(() => {
    setActivePinia(createPinia());
    document.cookie = 'XSRF-TOKEN=t; path=/';
    routes = {};
    vi.stubGlobal(
        'fetch',
        vi.fn((input: RequestInfo | URL, init: RequestInit = {}) => {
            const url = input instanceof Request ? input.url : String(input);
            const method =
                (input instanceof Request ? input.method : init.method) ??
                'GET';
            const key = `${method} ${new URL(url, 'http://spa.test').pathname}`;

            return Promise.resolve(routes[key]?.() ?? json(204, {}));
        }),
    );
});

afterEach(() => vi.unstubAllGlobals());

describe('session store', () => {
    it('starts signed out when there is no session', async () => {
        routes['GET /api/v1/me'] = () =>
            json(401, { message: 'Silakan login terlebih dahulu.' });
        const session = useSession();

        await session.load();

        expect(session.loaded).toBe(true);
        expect(session.isAuthenticated).toBe(false);
        expect(session.can('write')).toBe(false);
    });

    it('signs in and exposes the active family with its permissions', async () => {
        routes['POST /api/v1/auth/login'] = () => json(200, { data: me() });
        const session = useSession();

        expect(
            await session.login({
                email: 'ayah@budgeting.test',
                password: 'password',
                remember: false,
            }),
        ).toBe('ok');

        expect(session.currentFamily?.name).toBe('Keluarga Golden');
        expect(session.can('write')).toBe(true);
        expect(session.can('manage')).toBe(true);
    });

    it('stops at the two-factor step without a session', async () => {
        routes['POST /api/v1/auth/login'] = () =>
            json(202, {
                message: 'Masukkan kode autentikasi dua faktor.',
                two_factor: true,
            });
        const session = useSession();

        expect(
            await session.login({
                email: 'ayah@budgeting.test',
                password: 'password',
                remember: false,
            }),
        ).toBe('two-factor');
        expect(session.isAuthenticated).toBe(false);
    });

    it('follows the permissions of the family switched to', async () => {
        routes['PUT /api/v1/families/2/switch'] = () =>
            json(200, { data: me({ current_family_id: 2 }) });
        const session = useSession();
        session.setUser(me());

        await session.switchFamily(2);

        expect(session.currentFamily?.name).toBe('Keluarga Kakek');
        expect(session.can('write')).toBe(false);
    });

    it('forgets the user on logout', async () => {
        const session = useSession();
        session.setUser(me());

        await session.logout();

        expect(session.user).toBeNull();
    });
});
