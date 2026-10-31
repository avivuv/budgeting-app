// Public API of the categories module (other modules import only from here).
import type { SearchableOption } from '@/components/SearchableSelect.vue';

type Category = App.DTOs.Category.CategoryItemData;

/**
 * Parent categories followed by their sub-categories (indented). Keywords are
 * searchable, so typing "bensin" finds "Transportasi".
 */
export function categoryOptions(
    tree: Category[],
    options: { includeArchived?: boolean; excludeId?: number } = {},
): SearchableOption[] {
    const visible = (category: Category) =>
        (options.includeArchived || !category.isArchived) &&
        category.id !== options.excludeId;

    return tree.filter(visible).flatMap((parent) => [
        { value: parent.id, label: parent.name, keywords: parent.keywords },
        ...parent.children.filter(visible).map((child) => ({
            value: child.id,
            label: child.name,
            indent: true,
            keywords: [parent.name, ...child.keywords],
        })),
    ]);
}
