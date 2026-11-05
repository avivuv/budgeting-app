import { describe, expect, it } from 'vite-plus/test';
import { ApiError, ApiValidationError } from './errors';
import { useApiForm } from './useApiForm';

describe('useApiForm', () => {
    it('passes the values to the call and returns its answer', async () => {
        const form = useApiForm({ name: 'Tabungan Agus', amount: '1,5jt' });

        const result = await form.submit((values) =>
            Promise.resolve({ sent: { ...values } }),
        );

        expect(result).toEqual({
            sent: { name: 'Tabungan Agus', amount: '1,5jt' },
        });
        expect(form.processing.value).toBe(false);
        expect(form.errors.value).toEqual({});
    });

    it('shows the messages of a 422 per field and resolves to undefined', async () => {
        const form = useApiForm({ amount: '' });

        const result = await form.submit(() =>
            Promise.reject(
                new ApiValidationError({
                    message: 'Nominal wajib diisi.',
                    errors: { amount: ['Nominal wajib diisi.'] },
                }),
            ),
        );

        expect(result).toBeUndefined();
        expect(form.errors.value).toEqual({ amount: 'Nominal wajib diisi.' });
    });

    it('puts a 422 without fields under "general"', async () => {
        const form = useApiForm({ code: '' });

        await form.submit(() =>
            Promise.reject(
                new ApiValidationError({
                    message: 'Sesi login sudah kedaluwarsa.',
                }),
            ),
        );

        expect(form.errors.value).toEqual({
            general: 'Sesi login sudah kedaluwarsa.',
        });
    });

    it('lets other errors through and clears old messages on the next try', async () => {
        const form = useApiForm({ amount: '' });
        await form.submit(() =>
            Promise.reject(
                new ApiValidationError({ errors: { amount: ['x'] } }),
            ),
        );

        await expect(
            form.submit(() => Promise.reject(new ApiError(500))),
        ).rejects.toBeInstanceOf(ApiError);
        expect(form.errors.value).toEqual({});
    });

    it('ignores a second submit while the first is running (double tap)', async () => {
        const form = useApiForm({});
        let calls = 0;
        let finish = () => {};
        const call = () => {
            calls++;

            return new Promise<void>((resolve) => {
                finish = resolve;
            });
        };

        const first = form.submit(call);
        const second = await form.submit(call);
        finish();
        await first;

        expect(calls).toBe(1);
        expect(second).toBeUndefined();
    });

    it('resets values to their start', () => {
        const form = useApiForm({ name: 'awal', tags: ['a'] });
        form.values.name = 'ubah';
        form.values.tags.push('b');

        form.reset();

        expect(form.values).toEqual({ name: 'awal', tags: ['a'] });
    });
});
