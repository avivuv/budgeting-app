import { execFileSync } from 'node:child_process';
import { e2eEnv, php } from './env';

type Row = Record<string, string | number | null>;

/*
 * Reads the E2E database directly, so a flow is verified down to the stored
 * rows and not only by what the page shows. Read-only by convention.
 */
export function dbSelect(
    sql: string,
    bindings: (string | number)[] = [],
): Row[] {
    const env = {
        ...process.env,
        ...e2eEnv,
        E2E_SQL: sql,
        E2E_BINDINGS: JSON.stringify(bindings),
    };

    const output = execFileSync(
        php,
        [
            '-r',
            `$pdo = new PDO('mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '');
            $statement = $pdo->prepare(getenv('E2E_SQL'));
            $statement->execute(json_decode(getenv('E2E_BINDINGS'), true));
            echo json_encode($statement->fetchAll(PDO::FETCH_ASSOC));`,
        ],
        { env },
    );

    return JSON.parse(output.toString()) as Row[];
}

export function dbOne(
    sql: string,
    bindings: (string | number)[] = [],
): Row | undefined {
    return dbSelect(sql, bindings)[0];
}
