/*
 * Regenerate src/core/api/schema.d.ts from the backend's OpenAPI document
 * (Scramble, docs/15 §6). PHP 8.4 is taken from $PHP, otherwise "php".
 *
 *   pnpm run api:types
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const frontend = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const root = path.dirname(frontend);
const spec = path.join(frontend, 'openapi.json');

execFileSync(
    process.env.PHP ?? 'php',
    ['artisan', 'scramble:export', `--path=${spec}`],
    { cwd: root, stdio: 'inherit' },
);

execFileSync(
    process.execPath,
    [
        path.join(frontend, 'node_modules/openapi-typescript/bin/cli.js'),
        spec,
        '-o',
        path.join(frontend, 'src/core/api/schema.d.ts'),
    ],
    { cwd: frontend, stdio: 'inherit' },
);

// Generated code is not linted (the redundant `unknown` unions come from Scramble).
const schema = path.join(frontend, 'src/core/api/schema.d.ts');
writeFileSync(schema, `/* eslint-disable */\n${readFileSync(schema, 'utf8')}`);
