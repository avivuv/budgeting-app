<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Wallet;
use App\Support\Money\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
class WalletResource extends JsonResource
{
    /**
     * @param  array<int, int>  $balances  wallet id => balance, computed by WalletService
     */
    public function __construct(Wallet $wallet, private readonly array $balances = [])
    {
        parent::__construct($wallet);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $balance = $this->balances[$this->id] ?? null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'initial_balance' => $this->initial_balance,
            'balance' => $balance,
            'balance_formatted' => $balance !== null ? MoneyFormatter::format($balance) : null,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_archived' => $this->is_archived,
        ];
    }
}
