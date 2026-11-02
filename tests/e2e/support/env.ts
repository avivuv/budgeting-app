/*
 * Environment of the E2E server and setup commands. Real environment
 * variables win over .env in Laravel, so this never touches the
 * development or test databases.
 */
export const E2E_PORT = 8124;

export const php = process.env.PHP ?? 'php';

export const e2eEnv: Record<string, string> = {
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    APP_URL: `http://127.0.0.1:${E2E_PORT}`,
    DB_DATABASE: 'budgeting_app_e2e',
    ACTIVITY_DB_DATABASE: 'budgeting_app_log_e2e',
    CACHE_STORE: 'database',
    SESSION_DRIVER: 'database',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    TELESCOPE_ENABLED: 'false',
    RECEIPT_PARSER: 'fake',
    UPLOADS_DISK: 'local',
};

export const DEMO = {
    owner: { email: 'demo@budgeting.test', password: 'password' },
    member: { email: 'ibu@budgeting.test', password: 'password' },
};
