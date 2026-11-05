import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { e2eEnv, php } from './env';

/*
 * Fresh E2E databases with demo data before every run.
 */
export default function globalSetup(): void {
    if (!existsSync('public/build/manifest.json')) {
        throw new Error('Run "pnpm run build" before the E2E tests.');
    }

    if (!existsSync('public/spa/index.html')) {
        throw new Error(
            'Run "pnpm --dir frontend build" before the E2E tests.',
        );
    }

    const env = { ...process.env, ...e2eEnv };
    const run = (args: string[]) =>
        execFileSync(php, args, { env, stdio: 'inherit' });

    // The databases may not exist yet (new machine or CI).
    run([
        '-r',
        `$pdo = new PDO('mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306'), getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '');
        foreach (['${e2eEnv.DB_DATABASE}', '${e2eEnv.ACTIVITY_DB_DATABASE}'] as $db) {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }`,
    ]);

    run(['artisan', 'migrate:fresh', '--force', '--no-interaction']);
    run([
        'artisan',
        'db:seed',
        '--class=DemoSeeder',
        '--force',
        '--no-interaction',
    ]);
}
