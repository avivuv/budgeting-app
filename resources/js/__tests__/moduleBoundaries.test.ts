import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Import boundaries from docs/15-frontend-architecture.md §9:
 * - shared code (components, composables, layouts, lib, types, pages) never imports a feature module;
 * - a module imports another module only through its public index.ts.
 */

const root = join(process.cwd(), 'resources', 'js');

function files(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            return entry === '__tests__' ? [] : files(path);
        }

        return /\.(ts|vue)$/.test(entry) ? [path] : [];
    });
}

function imports(path: string): string[] {
    const source = readFileSync(path, 'utf8');

    return [...source.matchAll(/from\s+['"]([^'"]+)['"]/g)].map((m) => m[1]);
}

const rel = (path: string) => relative(root, path).split(sep).join('/');

describe('frontend module boundaries', () => {
    it('keeps shared code independent of feature modules', () => {
        const shared = [
            'components',
            'composables',
            'layouts',
            'lib',
            'types',
            'pages',
        ];
        const violations = shared
            .flatMap((dir) => files(join(root, dir)))
            .flatMap((file) =>
                imports(file)
                    .filter((spec) => spec.startsWith('@/modules'))
                    .map((spec) => `${rel(file)} -> ${spec}`),
            );

        expect(violations).toEqual([]);
    });

    it('lets modules use each other only through index.ts', () => {
        const violations = files(join(root, 'modules')).flatMap((file) => {
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
