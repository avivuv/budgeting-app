import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { api, setApiHandlers } from './client';
import { ApiError, ApiValidationError } from './errors';

/*
 * The client against a fake fetch: headers it sends (CSRF, family,
 * idempotency) and how error answers are turned into errors and reactions.
 */
type Call = { url: string; init: RequestInit };

let calls: Call[];
let answer: (url: string) => Response;

function json(status: number, body: unknown): Response {
    return new Response(status === 204 ? null : JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function headerOf(call: Call, name: string): string | null {
    return new Headers(call.init.headers).get(name);
}

const handlers = {
    familyId: vi.fn(() => 7 as number | null),
    onUnauthenticated: vi.fn(),
    onNoFamily: vi.fn(),
    onNotice: vi.fn(),
};

beforeEach(() => {
    calls = [];
    answer = () => json(200, { data: { ok: true } });
    document.cookie = 'XSRF-TOKEN=abc%3D%3D; path=/';
    vi.stubGlobal(
        'fetch',
        vi.fn((input: RequestInfo | URL, init: RequestInit = {}) => {
            const url = input instanceof Request ? input.url : String(input);
            calls.push({
                url,
                init:
                    input instanceof Request
                        ? {
                              ...init,
                              headers: input.headers,
                              method: input.method,
                          }
                        : init,
            });

            return Promise.resolve(answer(url));
        }),
    );
    Object.values(handlers).forEach((fn) => fn.mockClear());
    setApiHandlers(handlers);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('api client', () => {
    it('reads with JSON, the session cookie and the active family', async () => {
        const result = await api.get<{ data: { ok: boolean } }>('/wallets');

        expect(result.data.ok).toBe(true);
        expect(calls[0].url).toContain('/api/v1/wallets');
        expect(headerOf(calls[0], 'Accept')).toBe('application/json');
        expect(headerOf(calls[0], 'X-Family-Id')).toBe('7');
        expect(calls[0].init.credentials).toBe('include');
    });

    it('sends the CSRF token and a fresh Idempotency-Key on every POST', async () => {
        await api.post('/transactions', { amount: '12rb' });
        await api.post('/transactions', { amount: '12rb' });

        const [first, second] = calls;
        expect(headerOf(first, 'X-XSRF-TOKEN')).toBe('abc==');
        expect(headerOf(first, 'Idempotency-Key')).toMatch(/^[0-9a-f-]{36}$/);
        expect(headerOf(first, 'Idempotency-Key')).not.toBe(
            headerOf(second, 'Idempotency-Key'),
        );
    });

    it('keeps an Idempotency-Key given by the caller (retry of the same submit)', async () => {
        await api.post(
            '/transactions',
            {},
            { headers: { 'Idempotency-Key': 'same-submit' } },
        );

        expect(headerOf(calls[0], 'Idempotency-Key')).toBe('same-submit');
    });

    it('omits X-Family-Id before the user has a family', async () => {
        handlers.familyId.mockReturnValue(null);

        await api.get('/me');

        expect(headerOf(calls[0], 'X-Family-Id')).toBeNull();
    });

    it('turns a 422 into field messages, including a broken business rule', async () => {
        answer = () =>
            json(422, {
                message: 'Kategori ini sudah diarsipkan.',
                errors: {
                    category_id: ['Kategori ini sudah diarsipkan.'],
                    amount: ['Nominal wajib diisi.', 'lain'],
                },
                rule: 'BR-B05',
            });

        const error = await api
            .post('/transactions', {})
            .catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiValidationError);
        expect((error as ApiValidationError).fieldErrors).toEqual({
            category_id: 'Kategori ini sudah diarsipkan.',
            amount: 'Nominal wajib diisi.',
        });
        expect((error as ApiValidationError).rule).toBe('BR-B05');
        expect(handlers.onNotice).not.toHaveBeenCalled();
    });

    it('reacts to an ended session, a missing family and other refusals', async () => {
        answer = () => json(401, { message: 'Silakan login terlebih dahulu.' });
        await expect(api.get('/wallets')).rejects.toBeInstanceOf(ApiError);
        expect(handlers.onUnauthenticated).toHaveBeenCalledOnce();

        answer = () =>
            json(409, {
                message: 'Buat atau gabung ke keluarga terlebih dahulu.',
                code: 'no_family',
            });
        await expect(api.get('/wallets')).rejects.toMatchObject({
            status: 409,
            code: 'no_family',
        });
        expect(handlers.onNoFamily).toHaveBeenCalledOnce();

        answer = () =>
            json(403, { message: 'Kamu tidak punya akses untuk aksi ini.' });
        await expect(api.delete('/wallets/1')).rejects.toMatchObject({
            status: 403,
        });
        expect(handlers.onNotice).toHaveBeenCalledWith(
            'Kamu tidak punya akses untuk aksi ini.',
        );
    });

    it('stays quiet when asked (e.g. checking for a session on start)', async () => {
        answer = () => json(401, { message: 'Silakan login terlebih dahulu.' });

        await expect(api.get('/me', { quiet: true })).rejects.toMatchObject({
            status: 401,
        });
        expect(handlers.onUnauthenticated).not.toHaveBeenCalled();
    });
});
