<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use App\Models\Concerns\BelongsToFamily;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $family_id
 * @property int|null $parent_id
 * @property CategoryType $type
 * @property string $name
 * @property string|null $icon
 * @property string|null $color
 * @property list<string>|null $keywords
 * @property bool $is_default
 * @property bool $is_archived
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['parent_id', 'type', 'name', 'icon', 'color', 'keywords', 'is_default', 'is_archived', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToFamily, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_archived' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'keywords' => 'array',
            'is_default' => 'boolean',
            'is_archived' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
