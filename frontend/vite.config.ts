import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite-plus';

/*
 * Web SPA (docs/15-frontend-architecture.md). Pages live under /app/ during
 * the migration (docs/18 §4, moved to / in M5); the built files go to
 * public/spa/. The two differ on purpose: a real public/app folder would make
 * PHP's built-in server treat /app as the script base and route /app/register
 * to the old /register page. In development Vite proxies the API and Sanctum
 * to the Laragon site, so the session cookie stays on one origin.
 */
const backend = process.env.BACKEND_URL ?? 'http://budgeting-app.test';

export default defineConfig(({ command }) => ({
    // Built files are served from /spa/; the dev server serves the pages at /app/.
    base: command === 'build' ? '/spa/' : '/app/',
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        port: 5173,
        proxy: {
            '/api': { target: backend, changeOrigin: true },
            '/sanctum': { target: backend, changeOrigin: true },
            '/user/passkeys': { target: backend, changeOrigin: true },
        },
    },
    build: {
        outDir: '../public/spa',
        emptyOutDir: true,
    },
    test: {
        environment: 'happy-dom',
        include: ['src/**/*.test.ts'],
    },
    lint: {
        ignorePatterns: [
            'node_modules/**',
            'src/core/components/ui/**',
            'src/core/api/schema.d.ts',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            'src/core/components/ui/**',
            'src/core/api/schema.d.ts',
            'openapi.json',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'src/assets/app.css',
        },
    },
}));
