<?php

declare(strict_types=1);

namespace App\DTOs\Category;

use App\Enums\CategoryType;
use App\Models\Category;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CategoryItemData extends Data
{
    /**
     * @param  array<int, string>  $keywords
     * @param  array<int, CategoryItemData>  $children
     */
    public function __construct(
        public int $id,
        public CategoryType $type,
        public string $name,
        public ?int $parentId,
        public ?string $icon,
        public ?string $color,
        public array $keywords,
        public bool $isDefault,
        public bool $isArchived,
        public array $children = [],
    ) {}

    public static function fromCategory(Category $category, bool $withChildren = true): self
    {
        return new self(
            id: $category->id,
            type: $category->type,
            name: $category->name,
            parentId: $category->parent_id,
            icon: $category->icon,
            color: $category->color,
            keywords: $category->keywords ?? [],
            isDefault: $category->is_default,
            isArchived: $category->is_archived,
            children: $withChildren && $category->relationLoaded('children')
                ? $category->children->map(fn (Category $child): self => self::fromCategory($child, false))->values()->all()
                : [],
        );
    }
}
