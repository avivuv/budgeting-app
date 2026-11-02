import { dbOne } from './support/db';
import { expect, test } from './support/fixtures';

test('quick entry fills the form and the transaction shows up in the list', async ({
    page,
}) => {
    await page.goto('/transactions/create');

    await page.locator('#quick-entry').fill('jajan cilok 12rb @gopay #e2e');
    await page.getByRole('button', { name: 'Isi form' }).click();

    await expect(page.locator('#amount')).toHaveValue('12000');
    await expect(page.locator('#note')).toHaveValue('jajan cilok');
    await expect(page.locator('#tags')).toHaveValue('e2e');
    await expect(page.getByText('Form sudah terisi')).toBeVisible();

    await page.getByRole('button', { name: 'Simpan', exact: true }).click();

    await expect(page).toHaveURL(/\/transactions$/);
    await expect(page.getByText('Transaksi tersimpan.')).toBeVisible();
    await expect(page.getByText('jajan cilok').first()).toBeVisible();

    // Stored exactly as typed: integer rupiah, web source, author, wallet, tag (BR-A01, BR-B07).
    const row = dbOne(
        `SELECT t.amount, t.type, t.source, t.note, w.name AS wallet, c.name AS category, u.email AS author,
                (SELECT GROUP_CONCAT(g.name) FROM transaction_tag tt JOIN tags g ON g.id = tt.tag_id WHERE tt.transaction_id = t.id) AS tags
         FROM transactions t
         JOIN wallets w ON w.id = t.wallet_id
         JOIN categories c ON c.id = t.category_id
         JOIN users u ON u.id = t.created_by
         WHERE t.note = ? AND t.deleted_at IS NULL`,
        ['jajan cilok'],
    );
    expect(row).toEqual({
        amount: 12000,
        type: 'expense',
        source: 'web',
        note: 'jajan cilok',
        wallet: 'GoPay',
        category: 'Makanan & Minuman',
        author: 'demo@budgeting.test',
        tags: 'e2e',
    });
});

test('saving with empty optional fields works', async ({ page }) => {
    await page.goto('/transactions/create');

    await page.locator('#amount').fill('7rb');
    await page.locator('#category_id').click();
    await page.keyboard.type('Transport');
    await page.keyboard.press('Enter');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();

    await expect(page.getByText('Transaksi tersimpan.')).toBeVisible();
});

test('search and type filters narrow the list and stay in the url', async ({
    page,
}) => {
    await page.goto('/transactions');

    await page.getByLabel('Cari transaksi').fill('bensin');
    await expect(page).toHaveURL(/q=bensin/);
    await expect(page.locator('tbody tr').first()).toContainText('bensin');

    await page.getByLabel('Jenis transaksi').selectOption('income');
    await expect(page).toHaveURL(/type=income/);
    await expect(
        page.getByText('Tidak ada transaksi yang cocok'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Hapus semua filter' }).click();
    await expect(page).toHaveURL(/\/transactions$/);
});

test('sorting by amount puts the largest transaction first', async ({
    page,
}) => {
    await page.goto('/transactions');

    await page.getByRole('button', { name: 'Nominal' }).click();
    await expect(page).toHaveURL(/sort=-amount/);
    await expect(page.locator('tbody tr').first()).toContainText('9.500.000');
});

test('bulk delete removes the selected rows', async ({ page }) => {
    await page.goto('/transactions?q=jajan%20cilok');
    await expect(page.locator('tbody tr')).toHaveCount(1);

    await page.locator('tbody [role=checkbox]').first().click();
    await expect(page.getByText('1 dipilih')).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('region', { name: 'Aksi untuk transaksi terpilih' })
        .getByRole('button', { name: 'Hapus' })
        .click();

    await expect(page.getByText('1 transaksi dihapus.')).toBeVisible();
    await expect(
        page.getByText('Tidak ada transaksi yang cocok'),
    ).toBeVisible();

    // Soft delete: the row stays for the audit trail but leaves every total (BR-B08).
    expect(
        dbOne(
            'SELECT deleted_at IS NOT NULL AS deleted FROM transactions WHERE note = ?',
            ['jajan cilok'],
        ),
    ).toEqual({ deleted: 1 });
});
