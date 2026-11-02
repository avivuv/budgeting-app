import { defineConfig, devices } from '@playwright/test';
import { e2eEnv, E2E_PORT, php } from './tests/e2e/support/env';

/*
 * End-to-end tests (docs/12-testing-strategy.md §2.5): the real app in a
 * browser, against its own database (budgeting_app_e2e) seeded by DemoSeeder.
 *
 *   pnpm run build && pnpm run test:e2e
 *
 * PHP 8.4 is taken from $PHP, otherwise "php" on PATH. Locally the installed
 * Chrome is used; CI uses Playwright's Chromium.
 */
const chrome = process.env.CI ? {} : { channel: 'chrome' as const };

export default defineConfig({
    testDir: './tests/e2e',
    testMatch: '**/*.e2e.ts',
    globalSetup: './tests/e2e/support/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 60_000,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${E2E_PORT}`,
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.ts/,
            use: { ...devices['Desktop Chrome'], ...chrome },
        },
        {
            name: 'desktop',
            dependencies: ['setup'],
            testIgnore: /\.phone\.e2e\.ts/,
            use: {
                ...devices['Desktop Chrome'],
                ...chrome,
                viewport: { width: 1366, height: 900 },
                storageState: 'tests/e2e/.auth/owner.json',
            },
        },
        {
            name: 'phone',
            dependencies: ['setup'],
            testMatch: /\.phone\.e2e\.ts/,
            use: {
                ...devices['Pixel 7'],
                ...chrome,
                storageState: 'tests/e2e/.auth/owner.json',
            },
        },
    ],
    webServer: {
        command: `${php} artisan serve --host=127.0.0.1 --port=${E2E_PORT}`,
        // A static file: the database is only created by the global setup.
        url: `http://127.0.0.1:${E2E_PORT}/robots.txt`,
        env: { ...e2eEnv, PHP_CLI_SERVER_WORKERS: '4' },
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
