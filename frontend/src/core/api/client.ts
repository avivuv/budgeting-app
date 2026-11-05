import { ofetch } from 'ofetch';
import type { FetchOptions } from 'ofetch';
import { ApiError, toApiError } from './errors';

/*
 * The only way the SPA talks to the backend (docs/15 §5.1): /api/v1 with the
 * Sanctum session cookie and CSRF header, the active family (X-Family-Id)
 * and an Idempotency-Key on every POST (BR-B13).
 *
 * Reactions that need the router or the session (401 → login, 409 →
 * onboarding, toasts) are registered from main.ts, so core stays free of
 * modules and is easy to test.
 */

export type ApiHandlers = {
    /** Active family id sent as X-Family-Id. */
    familyId: () => number | null;
    /** 401: the session ended. */
    onUnauthenticated: () => void;
    /** 409 with code "no_family": the user must create or join a family. */
    onNoFamily: () => void;
    /** 403, 429 and server errors: show a short message. */
    onNotice: (message: string) => void;
};

const handlers: ApiHandlers = {
    familyId: () => null,
    onUnauthenticated: () => {},
    onNoFamily: () => {},
    onNotice: () => {},
};

export function setApiHandlers(next: Partial<ApiHandlers>): void {
    Object.assign(handlers, next);
}

export function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie
        .split('; ')
        .find((part) => part.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

/** Sanctum sets the XSRF-TOKEN cookie; needed before the first POST. */
export async function ensureCsrfCookie(force = false): Promise<void> {
    if (force || readCookie('XSRF-TOKEN') === null) {
        await ofetch('/sanctum/csrf-cookie', { credentials: 'include' });
    }
}

export function newIdempotencyKey(): string {
    return crypto.randomUUID();
}

const SILENT = new Set([401, 404, 409, 422]);

export type RequestOptions = Omit<FetchOptions<'json'>, 'method' | 'body'> & {
    body?: unknown;
    /** Do not run the global 401/409/notice reactions (e.g. the first GET /me). */
    quiet?: boolean;
};

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

async function request<T>(
    method: Method,
    path: string,
    options: RequestOptions = {},
): Promise<T> {
    const { quiet, headers: extraHeaders, body, ...rest } = options;
    const headers = new Headers(extraHeaders as HeadersInit | undefined);

    headers.set('Accept', 'application/json');

    if (method !== 'GET') {
        await ensureCsrfCookie();
        const xsrf = readCookie('XSRF-TOKEN');
        if (xsrf) {
            headers.set('X-XSRF-TOKEN', xsrf);
        }
    }

    if (method === 'POST' && !headers.has('Idempotency-Key')) {
        headers.set('Idempotency-Key', newIdempotencyKey());
    }

    const family = handlers.familyId();
    if (family !== null && !headers.has('X-Family-Id')) {
        headers.set('X-Family-Id', String(family));
    }

    try {
        return await ofetch<T>(path, {
            ...rest,
            baseURL: '/api/v1',
            method,
            body: body as FetchOptions['body'],
            headers,
            credentials: 'include',
        });
    } catch (error) {
        const response = (
            error as { response?: Response & { _data?: unknown } }
        ).response;

        if (!response) {
            throw new ApiError(0, {
                message:
                    'Tidak dapat terhubung ke server. Periksa koneksi internet.',
            });
        }

        const apiError = toApiError(response.status, response._data);

        if (!quiet) {
            react(apiError);
        }

        throw apiError;
    }
}

function react(error: ApiError): void {
    if (error.status === 401) {
        handlers.onUnauthenticated();
    } else if (error.status === 409 && error.code === 'no_family') {
        handlers.onNoFamily();
    } else if (!SILENT.has(error.status)) {
        handlers.onNotice(error.message);
    }
}

export const api = {
    get: <T>(path: string, options?: RequestOptions) =>
        request<T>('GET', path, options),
    post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
        request<T>('POST', path, { ...options, body }),
    put: <T>(path: string, body?: unknown, options?: RequestOptions) =>
        request<T>('PUT', path, { ...options, body }),
    patch: <T>(path: string, body?: unknown, options?: RequestOptions) =>
        request<T>('PATCH', path, { ...options, body }),
    delete: <T>(path: string, body?: unknown, options?: RequestOptions) =>
        request<T>('DELETE', path, { ...options, body }),
};
