<?php

namespace Database\Factories;

use App\Enums\WalletType;
use App\Models\Family;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'name' => fake()->randomElement(['Tunai', 'BCA', 'Mandiri', 'GoPay', 'OVO']),
            'type' => WalletType::Cash,
            'initial_balance' => 0,
        ];
    }

    public function balance(int $amount): static
    {
        return $this->state(['initial_balance' => $amount]);
    }

    public function ofType(WalletType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function archived(): static
    {
        return $this->state(['is_archived' => true]);
    }
}
