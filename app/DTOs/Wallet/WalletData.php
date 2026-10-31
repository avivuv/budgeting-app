<?php

declare(strict_types=1);

namespace App\DTOs\Wallet;

use App\Enums\WalletType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class WalletData extends Data
{
    public function __construct(
        public string $name,
        public WalletType $type,
        public int $initialBalance = 0,
        public ?string $color = null,
        public ?string $icon = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toModelArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'initial_balance' => $this->initialBalance,
            'color' => $this->color,
            'icon' => $this->icon,
        ];
    }
}
