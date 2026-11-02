import { expect, test as setup } from '@playwright/test';
import { DEMO } from './support/env';

/*
 * Logs in once as the demo owner; the other projects reuse the session.
 */
setup('log in as the demo owner', async ({ page }) => {
    await page.goto('/login');
    await page.locator('input[name=email]').fill(DEMO.owner.email);
    await page.locator('input[name=password]').fill(DEMO.owner.password);
    await page.locator('button[type=submit]').click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await page.context().storageState({ path: 'tests/e2e/.auth/owner.json' });
});
