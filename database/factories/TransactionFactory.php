<?php

namespace Database\Factories;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Callers pass family-owned wallet/category (e.g. ->for($wallet)) so ids stay in one family.
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TransactionType::Expense,
            'amount' => fake()->numberBetween(1, 500) * 1_000,
            'date' => now()->toDateString(),
            'source' => TransactionSource::Web,
        ];
    }

    public function expense(int $amount, Wallet $wallet, Category $category): static
    {
        return $this->state([
            'family_id' => $wallet->family_id,
            'type' => TransactionType::Expense,
            'amount' => $amount,
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
        ]);
    }

    public function income(int $amount, Wallet $wallet, Category $category): static
    {
        return $this->state([
            'family_id' => $wallet->family_id,
            'type' => TransactionType::Income,
            'amount' => $amount,
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
        ]);
    }

    public function transfer(int $amount, Wallet $from, Wallet $to): static
    {
        return $this->state([
            'family_id' => $from->family_id,
            'type' => TransactionType::Transfer,
            'amount' => $amount,
            'wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'category_id' => null,
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(['date' => $date]);
    }
}
