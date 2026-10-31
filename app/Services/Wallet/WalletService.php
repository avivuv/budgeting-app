<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\DTOs\Wallet\WalletData;
use App\Enums\WalletType;
use App\Exceptions\Wallet\WalletRuleException;
use App\Models\Wallet;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Support\Money\MoneyFormatter;
use Illuminate\Database\Eloquent\Collection;

class WalletService
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
    ) {}

    /**
     * @return Collection<int, Wallet>
     */
    public function list(bool $includeArchived = false): Collection
    {
        return $this->wallets->listForFamily($includeArchived);
    }

    /**
     * Current balance per wallet id (BR-C01).
     *
     * @param  iterable<Wallet>  $wallets
     * @return array<int, int>
     */
    public function balances(iterable $wallets, ?string $until = null): array
    {
        $ids = [];
        foreach ($wallets as $wallet) {
            $ids[] = $wallet->id;
        }

        return $this->wallets->balances($ids, $until);
    }

    /**
     * Sum of all non-archived wallets; credit cards count as negative (BR-C02).
     */
    public function totalBalance(): int
    {
        return array_sum($this->balances($this->wallets->listForFamily()));
    }

    /**
     * BR-C03: a transaction may push a non-credit wallet below zero — it is
     * saved anyway, but the user is warned. Credit cards are negative by nature.
     *
     * @param  list<int|null>  $walletIds
     * @return list<string>
     */
    public function negativeBalanceWarnings(array $walletIds): array
    {
        $ids = array_values(array_unique(array_filter($walletIds, fn (?int $id): bool => $id !== null)));
        $wallets = $this->wallets->listForFamily(includeArchived: true)
            ->filter(fn (Wallet $wallet): bool => in_array($wallet->id, $ids, true) && $wallet->type !== WalletType::CreditCard);
        $balances = $this->balances($wallets);

        $warnings = [];
        foreach ($wallets as $wallet) {
            $balance = $balances[$wallet->id] ?? 0;

            if ($balance < 0) {
                $warnings[] = "Saldo {$wallet->name} menjadi minus (".MoneyFormatter::format($balance).').';
            }
        }

        return $warnings;
    }

    public function find(int $id): Wallet
    {
        return $this->wallets->findOrFail($id);
    }

    public function create(WalletData $data): Wallet
    {
        return $this->wallets->create($data->toModelArray());
    }

    public function update(Wallet $wallet, WalletData $data): Wallet
    {
        return $this->wallets->update($wallet, $data->toModelArray());
    }

    public function setArchived(Wallet $wallet, bool $archived): Wallet
    {
        return $this->wallets->update($wallet, ['is_archived' => $archived]);
    }

    /**
     * Only wallets that were never used can be deleted; others are archived (BR-C05).
     */
    public function delete(Wallet $wallet): void
    {
        if ($this->wallets->hasTransactions($wallet)) {
            throw WalletRuleException::hasTransactions();
        }

        $this->wallets->delete($wallet);
    }
}
