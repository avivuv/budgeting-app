import { dbOne } from './support/db';
import { expect, test } from './support/fixtures';

test('a new wallet keeps the chosen type and opening balance', async ({
    page,
}) => {
    await page.goto('/wallets');

    await page
        .getByRole('button', { name: /Tambah/ })
        .first()
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nama').fill('Mandiri E2E');
    await dialog.getByLabel('Jenis').selectOption({ label: 'Bank' });
    await dialog.getByLabel('Saldo awal').fill('1,5jt');
    await dialog.getByRole('button', { name: 'Simpan' }).click();

    await expect(page.getByText('Mandiri E2E').first()).toBeVisible();
    expect(
        dbOne('SELECT type, initial_balance FROM wallets WHERE name = ?', [
            'Mandiri E2E',
        ]),
    ).toEqual({ type: 'bank', initial_balance: 1500000 });
});

test('an invitation is sent with the selected role', async ({ page }) => {
    await page.goto('/members');

    await page.locator('#invite-email').fill('tamu-e2e@budgeting.test');
    await page.locator('#invite-role').selectOption({ index: 0 });
    const role = await page.locator('#invite-role').inputValue();
    await page
        .getByRole('button', { name: /Undang|Kirim/ })
        .first()
        .click();

    await expect(
        page.getByText('tamu-e2e@budgeting.test').first(),
    ).toBeVisible();
    expect(
        dbOne('SELECT role FROM invitations WHERE email = ?', [
            'tamu-e2e@budgeting.test',
        ]),
    ).toEqual({ role });
});
