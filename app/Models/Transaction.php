<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToFamily;
use App\Models\Concerns\LogsFamilyActivity;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Amounts are always positive whole rupiah; direction comes from `type` (BR-A02).
 *
 * @property int $id
 * @property int $family_id
 * @property TransactionType $type
 * @property int $amount
 * @property CarbonImmutable $date
 * @property int $wallet_id
 * @property int|null $to_wallet_id
 * @property int|null $category_id
 * @property string|null $note
 * @property string|null $merchant
 * @property TransactionSource $source
 * @property int|null $receipt_id
 * @property int|null $recurring_rule_id
 * @property int|null $created_by
 * @property int|null $telegram_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'type', 'amount', 'date', 'wallet_id', 'to_wallet_id', 'category_id', 'note', 'merchant',
    'source', 'receipt_id', 'recurring_rule_id', 'created_by', 'telegram_message_id',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToFamily, HasFactory, LogsFamilyActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
            'amount' => 'integer',
            // Explicit format: a date-only value must never be serialized as a UTC datetime
            // (e.g. in the activity log), which would shift it a day back in Asia/Jakarta.
            'date' => 'immutable_date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'transaction_tag');
    }
}
