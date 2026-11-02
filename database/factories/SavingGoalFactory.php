<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\SavingGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingGoal>
 */
class SavingGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Dana darurat', 'Liburan', 'Laptop baru']),
            'target_amount' => 10_000_000,
            'saved_amount' => 0,
        ];
    }
}
