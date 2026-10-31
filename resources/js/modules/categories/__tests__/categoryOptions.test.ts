import { describe, expect, it } from 'vitest';
import { categoryOptions } from '../index';

type Category = App.DTOs.Category.CategoryItemData;

const category = (overrides: Partial<Category>): Category => ({
    id: 1,
    type: 'expense',
    name: 'Kategori',
    parentId: null,
    icon: null,
    color: null,
    keywords: [],
    isDefault: false,
    isArchived: false,
    children: [],
    ...overrides,
});

const tree: Category[] = [
    category({
        id: 1,
        name: 'Makanan & Minuman',
        keywords: ['makan', 'kopi'],
        children: [
            category({
                id: 11,
                name: 'Restoran',
                parentId: 1,
                keywords: ['resto'],
            }),
            category({ id: 12, name: 'Lama', parentId: 1, isArchived: true }),
        ],
    }),
    category({ id: 2, name: 'Transportasi', keywords: ['bensin'] }),
    category({ id: 3, name: 'Diarsipkan', isArchived: true }),
];

describe('categoryOptions', () => {
    it('lists parents followed by indented children', () => {
        expect(
            categoryOptions(tree).map((o) => [o.value, o.label, !!o.indent]),
        ).toEqual([
            [1, 'Makanan & Minuman', false],
            [11, 'Restoran', true],
            [2, 'Transportasi', false],
        ]);
    });

    it('makes keywords and the parent name searchable', () => {
        const restoran = categoryOptions(tree).find((o) => o.value === 11);
        const transport = categoryOptions(tree).find((o) => o.value === 2);

        expect(restoran?.keywords).toEqual(['Makanan & Minuman', 'resto']);
        expect(transport?.keywords).toEqual(['bensin']);
    });

    it('hides archived categories unless asked', () => {
        expect(categoryOptions(tree).some((o) => o.value === 3)).toBe(false);
        expect(
            categoryOptions(tree, { includeArchived: true }).map(
                (o) => o.value,
            ),
        ).toContain(12);
    });

    it('can exclude one category (e.g. the merge source)', () => {
        expect(
            categoryOptions(tree, { excludeId: 2 }).map((o) => o.value),
        ).not.toContain(2);
    });
});
