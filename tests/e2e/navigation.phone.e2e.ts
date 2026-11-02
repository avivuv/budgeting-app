import { expect, test } from './support/fixtures';

test('the bottom navigation and the + button work on a phone', async ({
    page,
}) => {
    await page.goto('/dashboard');
    const nav = page.getByRole('navigation', { name: 'Navigasi utama' });

    await expect(nav).toBeVisible();
    await nav.getByRole('link', { name: 'Transaksi', exact: true }).click();
    await expect(page).toHaveURL(/\/transactions$/);

    await nav.getByRole('link', { name: 'Catat transaksi' }).click();
    await expect(page).toHaveURL(/\/transactions\/create$/);

    await nav.getByRole('button', { name: 'Lainnya' }).click();
    await expect(page.getByRole('link', { name: 'Kategori' })).toBeVisible();
});

test('the transaction list loads more rows while scrolling', async ({
    page,
}) => {
    await page.goto('/transactions');
    const rows = page.locator('main a[href*="/edit"]');

    await expect(rows.first()).toBeVisible();
    const first = await rows.count();

    await page.mouse.wheel(0, 30_000);
    await expect.poll(async () => rows.count()).toBeGreaterThan(first);
});

test('filters open in a bottom sheet', async ({ page }) => {
    await page.goto('/transactions');

    await page.getByRole('button', { name: 'Filter' }).click();
    const sheet = page.getByRole('dialog', { name: 'Filter transaksi' });

    await expect(sheet).toBeVisible();
    await sheet.getByLabel('Nominal min.').fill('100rb');
    await sheet.getByRole('button', { name: 'Terapkan' }).click();

    await expect(page).toHaveURL(/min=100rb|min=100000/);
});
