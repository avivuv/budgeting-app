<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\DTOs\Savings\SavingGoalData;
use App\DTOs\Savings\SavingGoalItemData;
use App\Exceptions\Savings\SavingGoalRuleException;
use App\Models\SavingGoal;
use App\Repositories\Contracts\SavingGoalRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Report\PeriodService;
use App\Support\Money\AmountParser;
use App\Support\Savings\SavingGoalCalculator;

/**
 * Savings goals (docs/11 §K): progress from a linked wallet or manual
 * contributions, and the monthly amount needed to reach the target.
 */
class SavingGoalService
{
    public function __construct(
        private readonly SavingGoalRepositoryInterface $goals,
        private readonly WalletRepositoryInterface $wallets,
        private readonly PeriodService $periods,
    ) {}

    /**
     * @return list<SavingGoalItemData>
     */
    public function overview(): array
    {
        $goals = $this->goals->listForFamily();
        $progress = $this->progressOf($goals->all());
        $current = $this->periods->current();

        $items = [];
        foreach ($goals as $goal) {
            $deadline = $goal->deadline !== null ? $this->periods->containing($goal->deadline) : null;
            $value = $progress[$goal->id];

            $items[] = SavingGoalItemData::fromGoal(
                $goal,
                $value,
                SavingGoalCalculator::monthlyNeeded($goal->target_amount, $value, $current, $deadline),
            );
        }

        return $items;
    }

    public function find(int $id): SavingGoal
    {
        return $this->goals->findOrFail($id);
    }

    public function create(SavingGoalData $data): SavingGoal
    {
        $this->assertValid($data);

        $goal = $this->goals->create($data->toModelArray());
        $this->syncAchieved($goal);

        return $goal;
    }

    public function update(SavingGoal $goal, SavingGoalData $data): SavingGoal
    {
        $this->assertValid($data);

        $goal = $this->goals->update($goal, $data->toModelArray());
        $this->syncAchieved($goal);

        return $goal;
    }

    /**
     * BR-K02: add (positive) or take out (negative) money on a goal without wallet.
     */
    public function contribute(SavingGoal $goal, int $amount): SavingGoal
    {
        if ($goal->wallet_id !== null) {
            throw SavingGoalRuleException::linkedToWallet();
        }

        if ($goal->saved_amount + $amount < 0) {
            throw SavingGoalRuleException::belowZero();
        }

        $goal = $this->goals->update($goal, ['saved_amount' => $goal->saved_amount + $amount]);
        $this->syncAchieved($goal);

        return $goal;
    }

    public function delete(SavingGoal $goal): void
    {
        $this->goals->delete($goal);
    }

    /**
     * BR-K03: set achieved_at the first time a goal is reached, clear it when
     * progress drops again (e.g. money moved out of the linked wallet).
     */
    public function syncAll(): void
    {
        foreach ($this->goals->listForFamily() as $goal) {
            $this->syncAchieved($goal);
        }
    }

    private function syncAchieved(SavingGoal $goal): void
    {
        $achieved = SavingGoalCalculator::achieved($goal->target_amount, $this->progressOf([$goal])[$goal->id]);

        if ($achieved && $goal->achieved_at === null) {
            $this->goals->update($goal, ['achieved_at' => now()]);
        } elseif (! $achieved && $goal->achieved_at !== null) {
            $this->goals->update($goal, ['achieved_at' => null]);
        }
    }

    /**
     * BR-K01: linked wallet balance as of today, otherwise saved_amount.
     *
     * @param  array<int, SavingGoal>  $goals
     * @return array<int, int>
     */
    private function progressOf(array $goals): array
    {
        $walletIds = [];
        foreach ($goals as $goal) {
            if ($goal->wallet_id !== null) {
                $walletIds[] = $goal->wallet_id;
            }
        }

        $balances = $this->wallets->balances(array_values(array_unique($walletIds)), $this->periods->today()->toDateString());

        $progress = [];
        foreach ($goals as $goal) {
            $progress[$goal->id] = $goal->wallet_id !== null ? ($balances[$goal->wallet_id] ?? 0) : $goal->saved_amount;
        }

        return $progress;
    }

    private function assertValid(SavingGoalData $data): void
    {
        if ($data->targetAmount <= 0 || $data->targetAmount > AmountParser::MAX) {
            throw SavingGoalRuleException::targetOutOfRange();
        }

        if ($data->walletId !== null) {
            // Tenant-scoped: a wallet of another family is "not found" (BR-I06).
            $this->wallets->findOrFail($data->walletId);
        }
    }
}
