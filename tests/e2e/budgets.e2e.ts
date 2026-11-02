import { dbOne } from './support/db';
import { expect, test } from './support/fixtures';

test('a budget that is already used up raises an alert in the bell', async ({
    page,
}) => {
    await page.goto('/budgets');

    await page.getByRole('button', { name: 'Tambah', exact: true }).click();
    await page.locator('#budget-category').click();
    await page.keyboard.type('Belanja Harian');
    await page.keyboard.press('Enter');
    await page.locator('#budget-amount').fill('10rb');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Simpan' })
        .click();

    await expect(page.getByText('Budget disimpan.')).toBeVisible();
    const row = page.getByRole('progressbar', {
        name: /Belanja Harian: Lewat/,
    });
    await expect(row).toBeVisible();

    // Stored as integer rupiah for the current label month, both alert flags set (BR-F05).
    expect(
        dbOne(
            `SELECT b.amount, b.alert_80_sent_at IS NOT NULL AS alert80, b.alert_100_sent_at IS NOT NULL AS alert100
             FROM budgets b JOIN categories c ON c.id = b.category_id
             WHERE c.name = ? ORDER BY b.period DESC LIMIT 1`,
            ['Belanja Harian'],
        ),
    ).toEqual({ amount: 10000, alert80: 1, alert100: 1 });

    // One database notification per member, only the 100% one.
    expect(
        dbOne(
            "SELECT COUNT(*) AS total, SUM(JSON_EXTRACT(data, '$.threshold') = 100) AS at100 FROM notifications",
        ),
    ).toEqual({ total: 2, at100: '2' });

    // BR-F05: the 100% alert reached the notification bell.
    await expect(
        page.getByRole('link', { name: /Notifikasi, \d+ belum dibaca/ }),
    ).toBeVisible();

    await page.getByRole('link', { name: /Notifikasi/ }).click();
    await expect(
        page.getByText('Budget Belanja Harian terlampaui').first(),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Tandai semua dibaca' }).click();
    await expect(
        page.getByRole('link', { name: 'Notifikasi', exact: true }),
    ).toBeVisible();
});

test('budgets can be copied to the next period', async ({ page }) => {
    await page.goto('/budgets');
    await page.getByRole('link', { name: 'Periode berikutnya' }).click();
    await expect(page.getByText(/Belum ada budget untuk/)).toBeVisible();

    await page
        .getByRole('button', { name: 'Salin periode lalu' })
        .first()
        .click();

    await expect(
        page.getByText(/budget disalin dari periode lalu/),
    ).toBeVisible();
    await expect(
        page.getByRole('progressbar', { name: /Belanja Harian/ }),
    ).toBeVisible();
});

test('the dashboard shows the budget and the daily allowance insight', async ({
    page,
}) => {
    await page.goto('/dashboard');

    await expect(
        page.getByRole('progressbar', { name: /Belanja Harian/ }),
    ).toBeVisible();
    await expect(
        page.getByText(/Budget terlampaui: .*Belanja Harian/),
    ).toBeVisible();
});
