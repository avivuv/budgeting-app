import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { DEMO } from './support/env';

/*
 * The web SPA (frontend/, served under /app during the migration, docs/18):
 * signing in and out, the app shell, onboarding a new family and switching
 * between families — against the real API and the demo database.
 */

async function signIn(
    page: Page,
    email: string,
    password: string,
): Promise<void> {
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk' }).click();
}

test('a visitor is sent to login, and a wrong password is explained', async ({
    page,
}) => {
    await page.goto('/app/');
    await expect(page).toHaveURL(/\/app\/login$/);
    await expect(
        page.getByRole('heading', { name: 'Masuk ke akunmu' }),
    ).toBeVisible();

    await signIn(page, DEMO.owner.email, 'salah-sekali');

    await expect(page.getByText('Email atau password salah.')).toBeVisible();
    await expect(page).toHaveURL(/\/app\/login$/);
});

test('the owner signs in, sees the period summary from the API and signs out', async ({
    page,
}) => {
    await page.goto('/app/?period=2026-10');
    await expect(page).toHaveURL(/\/app\/login\?redirect=/);

    await signIn(page, DEMO.owner.email, DEMO.owner.password);

    // Back where they were going, inside the family.
    await expect(page).toHaveURL(/\/app\/\?period=2026-10$/);
    await expect(page.getByRole('heading', { name: 'Beranda' })).toBeVisible();
    await expect(page.getByTestId('period-label')).toHaveText('Oktober 2026');
    await expect(page.getByTestId('summary-amount')).toHaveCount(4);
    await expect(page.getByTestId('summary-amount').first()).toContainText(
        'Rp',
    );
    await expect(page.getByTestId('family-switcher')).toContainText(
        'Keluarga Demo',
    );

    // Menu items not yet moved to the SPA open the old pages.
    await expect(page.getByRole('link', { name: 'Dompet' })).toHaveAttribute(
        'href',
        '/wallets',
    );

    await page.getByTestId('user-menu').click();
    await page.getByTestId('logout-button').click();
    await expect(page).toHaveURL(/\/app\/login$/);

    await page.goto('/app/');
    await expect(page).toHaveURL(/\/app\/login$/);
});

test('the owner creates a second family, switches back and forth', async ({
    page,
}) => {
    await page.goto('/app/login');
    await signIn(page, DEMO.owner.email, DEMO.owner.password);
    await expect(page.getByTestId('family-switcher')).toContainText(
        'Keluarga Demo',
    );
    const demoBalance = await page
        .getByTestId('summary-amount')
        .first()
        .textContent();

    // Onboarding a new family from the switcher.
    await page.getByTestId('family-switcher').click();
    await page.getByRole('menuitem', { name: 'Buat keluarga baru' }).click();
    await expect(page).toHaveURL(/\/app\/onboarding$/);
    await page.getByLabel('Nama keluarga').fill('Usaha Warung');
    await page.getByRole('button', { name: 'Buat keluarga' }).click();

    // The new family is active and starts empty (only a cash wallet at 0).
    await expect(page).toHaveURL(/\/app\/$/);
    await expect(page.getByTestId('family-switcher')).toContainText(
        'Usaha Warung',
    );
    await expect(page.getByTestId('summary-amount').first()).toHaveText('Rp 0');

    // Back to the demo family: its numbers again (X-Family-Id follows).
    await page.getByTestId('family-switcher').click();
    await page.getByRole('menuitem', { name: 'Keluarga Demo' }).click();
    await expect(page.getByTestId('family-switcher')).toContainText(
        'Keluarga Demo',
    );
    await expect(page.getByTestId('summary-amount').first()).toHaveText(
        demoBalance ?? '',
    );
});

test('a new account must verify its email first', async ({ page }) => {
    await page.goto('/app/register');
    await page.getByLabel('Nama').fill('Budi Santoso');
    await page.getByLabel('Email').fill(`budi.${Date.now()}@contoh.com`);
    await page.getByLabel('Password', { exact: true }).fill('rahasia-123');
    await page.getByLabel('Ulangi password').fill('rahasia-123');
    await page.getByRole('button', { name: 'Daftar' }).click();

    // Hashing the password and sending the email take a while on the test server.
    await expect(page).toHaveURL(/\/app\/verify-email$/, { timeout: 20_000 });
    await expect(
        page.getByText('Kami sudah mengirim link verifikasi'),
    ).toBeVisible();
});

test.describe('on a phone', () => {
    test.use({
        viewport: { width: 412, height: 915 },
        isMobile: true,
        hasTouch: true,
    });

    test('the member gets the bottom bar with the add button', async ({
        page,
    }) => {
        await page.goto('/app/login');
        await signIn(page, DEMO.member.email, DEMO.member.password);

        const bar = page.getByRole('navigation', { name: 'Navigasi utama' });
        await expect(bar).toBeVisible();
        await expect(
            bar.getByRole('link', { name: 'Beranda' }),
        ).toHaveAttribute('aria-current', 'page');
        await expect(
            bar.getByRole('link', { name: 'Catat transaksi' }),
        ).toBeVisible();

        await bar.getByRole('button', { name: 'Lainnya' }).click();
        await expect(
            page.getByRole('link', { name: 'Tabungan' }),
        ).toBeVisible();
    });
});
