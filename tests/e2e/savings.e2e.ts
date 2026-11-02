import { dbOne } from './support/db';
import { expect, test } from './support/fixtures';

test('create a goal, put money in and see the progress', async ({ page }) => {
    await page.goto('/savings');

    await page.getByRole('button', { name: 'Tujuan baru' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nama').fill('Liburan E2E');
    await dialog.getByLabel('Target').fill('2jt');
    await dialog.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Tujuan Liburan E2E dibuat.')).toBeVisible();

    await page
        .getByRole('button', { name: 'Setor atau ambil Liburan E2E' })
        .click();
    await page.getByRole('dialog').getByLabel('Nominal').fill('500rb');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Simpan' })
        .click();

    await expect(page.getByText('Tabungan ditambahkan.')).toBeVisible();
    await expect(
        page.getByText('Rp 500.000 / Rp 2.000.000 · 25,0%'),
    ).toBeVisible();

    // BR-K01/K02: stored as integer rupiah on the goal itself.
    expect(
        dbOne(
            'SELECT target_amount, saved_amount, wallet_id, achieved_at FROM saving_goals WHERE name = ?',
            ['Liburan E2E'],
        ),
    ).toEqual({
        target_amount: 2000000,
        saved_amount: 500000,
        wallet_id: null,
        achieved_at: null,
    });

    await page.goto('/dashboard');
    await expect(
        page.getByRole('progressbar', { name: 'Tujuan Liburan E2E' }),
    ).toBeVisible();
});
