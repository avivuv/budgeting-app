<?php

declare(strict_types=1);

namespace App\DTOs\Savings;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for creating/updating a savings goal (web and API).
 */
#[MapInputName(SnakeCaseMapper::class)]
class SavingGoalData extends Data
{
    public function __construct(
        public string $name,
        public int $targetAmount,
        public ?int $walletId = null,
        public ?string $deadline = null,
        public ?string $icon = null,
        public ?string $color = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toModelArray(): array
    {
        return [
            'name' => trim($this->name),
            'target_amount' => $this->targetAmount,
            'wallet_id' => $this->walletId,
            'deadline' => $this->deadline,
            'icon' => $this->icon,
            'color' => $this->color,
        ];
    }
}
