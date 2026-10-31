<?php

declare(strict_types=1);

namespace App\DTOs\Wallet;

use App\Enums\WalletType;
use App\Models\Wallet;
use App\Support\Money\MoneyFormatter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WalletItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public WalletType $type,
        public string $typeLabel,
        public int $initialBalance,
        public int $balance,
        public string $balanceFormatted,
        public ?string $color,
        public ?string $icon,
        public bool $isArchived,
    ) {}

    public static function fromWallet(Wallet $wallet, int $balance): self
    {
        return new self(
            id: $wallet->id,
            name: $wallet->name,
            type: $wallet->type,
            typeLabel: $wallet->type->label(),
            initialBalance: $wallet->initial_balance,
            balance: $balance,
            balanceFormatted: MoneyFormatter::format($balance),
            color: $wallet->color,
            icon: $wallet->icon,
            isArchived: $wallet->is_archived,
        );
    }
}
