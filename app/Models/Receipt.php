<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReceiptDocumentType;
use App\Enums\ReceiptStatus;
use App\Models\Concerns\BelongsToFamily;
use App\Models\Concerns\LogsFamilyActivity;
use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An uploaded receipt / proof of payment. While `parsed` it is only a draft:
 * no transaction exists until the user confirms (BR-H08).
 *
 * @property int $id
 * @property int $family_id
 * @property int|null $user_id
 * @property string|null $file_path
 * @property string $file_hash
 * @property string $mime_type
 * @property ReceiptStatus $status
 * @property ReceiptDocumentType|null $document_type
 * @property string|null $merchant
 * @property CarbonImmutable|null $receipt_date
 * @property int|null $total
 * @property int $discount
 * @property int $tax
 * @property string|null $payment_method
 * @property int|null $suggested_category_id
 * @property int|null $suggested_wallet_id
 * @property float|null $confidence
 * @property list<string>|null $flags
 * @property array<string, mixed>|null $raw_response
 * @property string|null $model
 * @property string|null $error
 * @property Carbon|null $expires_at
 * @property Carbon|null $confirmed_at
 * @property int|null $telegram_chat_id
 * @property int|null $telegram_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ReceiptItem> $items
 * @property-read Collection<int, Transaction> $transactions
 * @property-read User|null $user
 */
#[Fillable([
    'user_id', 'file_path', 'file_hash', 'mime_type', 'status', 'document_type', 'merchant',
    'receipt_date', 'total', 'discount', 'tax', 'payment_method', 'suggested_category_id',
    'suggested_wallet_id', 'confidence', 'flags', 'raw_response', 'model', 'error', 'expires_at',
    'confirmed_at', 'telegram_chat_id', 'telegram_message_id',
])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use BelongsToFamily, HasFactory, LogsFamilyActivity;

    public const string FLAG_TOTAL_MISMATCH = 'total_mismatch';

    public const string FLAG_DATE_REPLACED = 'date_replaced';

    public const string FLAG_POSSIBLE_DUPLICATE = 'possible_duplicate';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'discount' => 0,
        'tax' => 0,
    ];

    /**
     * The raw AI output is large and kept on the receipt itself; leave it out of the log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['raw_response'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'document_type' => ReceiptDocumentType::class,
            'receipt_date' => 'immutable_date:Y-m-d',
            'total' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'confidence' => 'float',
            'flags' => 'array',
            'raw_response' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags ?? [], true);
    }

    /**
     * @return HasMany<ReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
