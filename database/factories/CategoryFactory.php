<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'type' => CategoryType::Expense,
            'name' => fake()->unique()->word(),
        ];
    }

    public function income(): static
    {
        return $this->state(['type' => CategoryType::Income]);
    }

    public function expense(): static
    {
        return $this->state(['type' => CategoryType::Expense]);
    }

    public function childOf(Category $parent): static
    {
        return $this->state([
            'family_id' => $parent->family_id,
            'parent_id' => $parent->id,
            'type' => $parent->type,
        ]);
    }
}
