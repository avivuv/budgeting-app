<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletType;
use App\Models\Concerns\BelongsToFamily;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property WalletType $type
 * @property int $initial_balance
 * @property string|null $color
 * @property string|null $icon
 * @property bool $is_archived
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'type', 'initial_balance', 'color', 'icon', 'is_archived', 'sort_order'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use BelongsToFamily, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'initial_balance' => 0,
        'is_archived' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletType::class,
            'initial_balance' => 'integer',
            'is_archived' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
