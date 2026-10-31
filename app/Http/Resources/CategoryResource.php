<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'icon' => $this->icon,
            'color' => $this->color,
            'keywords' => $this->keywords ?? [],
            'is_archived' => $this->is_archived,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
