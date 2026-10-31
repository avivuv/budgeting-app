<?php

declare(strict_types=1);

namespace App\DTOs\Category;

use App\Enums\CategoryType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class CategoryData extends Data
{
    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public CategoryType $type,
        public string $name,
        public ?int $parentId = null,
        public ?string $icon = null,
        public ?string $color = null,
        public array $keywords = [],
    ) {}

    /**
     * Keywords trimmed, lower-cased and de-duplicated (matched as whole words, BR-E05).
     *
     * @return array<string, mixed>
     */
    public function toModelArray(): array
    {
        $keywords = array_values(array_unique(array_filter(
            array_map(fn (string $keyword): string => mb_strtolower(trim($keyword)), $this->keywords),
            fn (string $keyword): bool => $keyword !== '',
        )));

        return [
            'type' => $this->type,
            'name' => trim($this->name),
            'parent_id' => $this->parentId,
            'icon' => $this->icon,
            'color' => $this->color,
            'keywords' => $keywords,
        ];
    }
}
