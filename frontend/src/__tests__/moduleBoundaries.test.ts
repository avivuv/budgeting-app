import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';

/*
 * Import rules of docs/15-frontend-architecture.md §11:
 * - core never imports a module (nor the app wiring: router, navigation);
 * - a module uses another module only through its index.ts.
 */
const src = join(process.cwd(), 'src');

function files(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            return entry === '__tests__' ? [] : files(path);
        }

        return /\.(ts|vue)$/.test(entry) && !entry.endsWith('.d.ts')
            ? [path]
            : [];
    });
}

function imports(path: string): string[] {
    return [
        ...readFileSync(path, 'utf8').matchAll(/from\s+['"]([^'"]+)['"]/g),
    ].map((match) => match[1]);
}

const rel = (path: string) => relative(src, path).split(sep).join('/');

describe('frontend module boundaries', () => {
    it('keeps core free of modules and app wiring', () => {
        const violations = files(join(src, 'core')).flatMap((file) =>
            imports(file)
                .filter((spec) =>
                    /^@\/(modules|router|navigation|App)/.test(spec),
                )
                .map((spec) => `${rel(file)} -> ${spec}`),
        );

        expect(violations).toEqual([]);
    });

    it('lets modules use each other only through index.ts', () => {
        const violations = files(join(src, 'modules')).flatMap((file) => {
            const own = rel(file).split('/')[1];

            return imports(file)
                .filter((spec) => {
                    const match = spec.match(/^@\/modules\/([^/]+)(\/.*)?$/);

                    return (
                        match !== null &&
                        match[1] !== own &&
                        match[2] !== undefined &&
                        match[2] !== '/index'
                    );
                })
                .map((spec) => `${rel(file)} -> ${spec}`);
        });

        expect(violations).toEqual([]);
    });
});
