import { expect, test as base } from '@playwright/test';

/*
 * Every E2E test fails on uncaught page errors or console errors, so a
 * broken component cannot hide behind a passing assertion.
 */
export const test = base.extend<{ consoleErrors: string[] }>({
    consoleErrors: [
        async ({ page }, use) => {
            const errors: string[] = [];
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('console', (message) => {
                if (message.type() === 'error') {
                    errors.push(message.text());
                }
            });

            await use(errors);

            expect(errors, 'browser errors').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };
