<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * pending → processing → parsed (draft, waiting for the user) → confirmed |
 * rejected | expired; failed when it could not be read (docs/06 §2).
 */
#[TypeScript]
enum ReceiptStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Parsed = 'parsed';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Antre',
            self::Processing => 'Sedang dibaca',
            self::Parsed => 'Menunggu konfirmasi',
            self::Confirmed => 'Tersimpan',
            self::Failed => 'Gagal dibaca',
            self::Rejected => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    /** Still being read by the parser. */
    public function isReading(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }

    /**
     * Statuses whose file still counts for the duplicate-file check (BR-H04);
     * a rejected, failed or expired upload may be sent again.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Processing, self::Parsed, self::Confirmed];
    }
}
