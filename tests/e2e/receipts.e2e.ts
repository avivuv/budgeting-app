import { dbOne, dbSelect } from './support/db';
import { expect, test } from './support/fixtures';

// 1×1 PNG; the fake parser "reads" a minimarket receipt from any image.
const PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

test('scan a receipt, correct it and split it per item category', async ({
    page,
    browser,
}) => {
    await page.goto('/receipts');

    await page.getByLabel('Pilih file struk').setInputFiles({
        name: 'struk.png',
        mimeType: 'image/png',
        buffer: PNG,
    });

    // The draft is read and shown for review; nothing is booked yet (BR-H08).
    await expect(page).toHaveURL(/\/receipts\/\d+$/);
    await expect(page.getByLabel('Total dibayar')).not.toHaveValue('');
    const id = Number(page.url().split('/').pop());

    expect(
        dbOne(
            'SELECT status, merchant, (SELECT COUNT(*) FROM transactions WHERE receipt_id = ?) AS booked FROM receipts WHERE id = ?',
            [id, id],
        ),
    ).toEqual({ status: 'parsed', merchant: 'Indomaret', booked: 0 });

    // Correct the merchant before saving (BR-H09).
    await page.getByLabel('Toko / penerima').fill('Indomaret Kemang');
    await page.getByRole('button', { name: 'Pecah per item' }).click();

    await expect(page).toHaveURL(/\/transactions/);
    await expect(
        page.getByText(/Struk tersimpan sebagai \d+ transaksi\./),
    ).toBeVisible();

    // INV-09: the transactions add up to the receipt total, one per category.
    const receipt = dbOne(
        'SELECT status, merchant, total FROM receipts WHERE id = ?',
        [id],
    );
    expect(receipt).toMatchObject({
        status: 'confirmed',
        merchant: 'Indomaret Kemang',
    });

    const rows = dbSelect(
        'SELECT category_id, amount, source, note FROM transactions WHERE receipt_id = ? AND deleted_at IS NULL',
        [id],
    );
    expect(rows.length).toBe(2);
    expect(rows.reduce((sum, row) => sum + Number(row.amount), 0)).toBe(
        Number(receipt?.total),
    );
    expect(new Set(rows.map((row) => row.source))).toEqual(
        new Set(['receipt']),
    );
    expect(new Set(rows.map((row) => row.note))).toEqual(
        new Set(['Indomaret Kemang']),
    );

    // Back on the receipt: it is closed and cannot be saved twice (BR-H07).
    await page.goto(`/receipts/${id}`);
    await expect(page.getByText('Tersimpan sebagai 2 transaksi')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Simpan' })).toHaveCount(0);

    // The image is private: members only, never without a login (docs/06 §7).
    const image = await page.request.get(`/receipts/${id}/image`);
    expect(image.status()).toBe(200);
    expect(image.headers()['content-type']).toBe('image/jpeg');

    const guest = await browser.newContext({
        storageState: { cookies: [], origins: [] },
    });
    const anonymous = await guest.request.get(
        new URL(`/receipts/${id}/image`, page.url()).toString(),
        { maxRedirects: 0 },
    );
    expect(anonymous.status()).toBe(302);
    await guest.close();
});
