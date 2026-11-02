import { expect, test } from './support/fixtures';

test('every report tab renders without errors', async ({ page }) => {
    await page.goto('/reports');
    const tabs = page.getByRole('navigation', { name: 'Jenis laporan' });

    for (const name of [
        'Kategori',
        'Anggota',
        'Dompet',
        'Budget',
        'Tahunan',
        'Merchant',
        'Tag',
        'Arus kas',
    ]) {
        await tabs.getByRole('button', { name, exact: true }).click();
        await expect(
            tabs.getByRole('button', { name, exact: true }),
        ).toHaveAttribute('aria-current', 'page');
    }

    await expect(
        page.getByRole('table', { name: 'Arus kas per periode' }),
    ).toBeVisible();
});

test('a preset range updates the url and the totals', async ({ page }) => {
    await page.goto('/reports?tab=categories');

    await page.getByRole('button', { name: 'Periode lalu' }).click();
    await expect(page).toHaveURL(
        /from=\d{4}-\d{2}-\d{2}.*to=\d{4}-\d{2}-\d{2}/,
    );
    await expect(
        page.getByRole('table', { name: 'Total per kategori' }),
    ).toBeVisible();
});

test('exports Excel and PDF of the shown report', async ({ page }) => {
    await page.goto('/reports?tab=wallets');

    const [excel] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'Excel' }).click(),
    ]);
    expect(excel.suggestedFilename()).toMatch(/^laporan-dompet-.*\.xlsx$/);

    const [pdf] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'PDF' }).click(),
    ]);
    expect(pdf.suggestedFilename()).toMatch(/^laporan-dompet-.*\.pdf$/);
});
