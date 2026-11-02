import { expect, test } from './support/fixtures';

test('the root url opens the dashboard with all widgets', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByText('Total saldo')).toBeVisible();

    // Deferred widgets arrive after the summary.
    for (const title of [
        'Arus kas',
        'Pengeluaran per kategori',
        'Tren pengeluaran harian',
        'Pengeluaran per anggota',
        'Budget',
        'Saldo dompet',
        'Transaksi terbaru',
    ]) {
        await expect(
            page.getByText(title, { exact: true }).first(),
        ).toBeVisible();
    }

    await expect(page.locator('.apexcharts-canvas').first()).toBeVisible();
    await expect(page.getByText('Rata-rata pengeluaran harian')).toBeVisible();
});

test('the period picker moves back and returns to the current period', async ({
    page,
}) => {
    await page.goto('/dashboard');
    const current = await page
        .locator('main p.font-semibold')
        .first()
        .innerText();

    await page.getByRole('button', { name: 'Periode sebelumnya' }).click();
    await expect(page).toHaveURL(/period=\d{4}-\d{2}/);
    await expect(
        page.getByRole('button', { name: 'Periode ini' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Periode ini' }).click();
    await expect(page.locator('main p.font-semibold').first()).toHaveText(
        current,
    );
});

test('a category in the donut opens its transactions', async ({ page }) => {
    await page.goto('/dashboard');

    await page
        .getByRole('link', { name: /Belanja Harian/ })
        .first()
        .click();

    await expect(page).toHaveURL(/\/transactions\?.*category_id/);
    await expect(page.getByText('sesuai filter')).toBeVisible();
});
