<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'category_id' => Category::factory(),
            'period' => now()->startOfMonth()->toDateString(),
            'amount' => 1_000_000,
        ];
    }

    public function forCategory(Category $category, string $period, int $amount): static
    {
        return $this->state([
            'family_id' => $category->family_id,
            'category_id' => $category->id,
            'period' => $period,
            'amount' => $amount,
        ]);
    }
}
