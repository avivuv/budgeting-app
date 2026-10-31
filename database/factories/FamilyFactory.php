<?php

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Family>
 */
class FamilyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Keluarga '.fake()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'period_start_day' => 1,
        ];
    }

    public function startingOn(int $day): static
    {
        return $this->state(['period_start_day' => $day]);
    }
}
