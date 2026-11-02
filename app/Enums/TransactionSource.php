<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Channel a transaction was recorded through (BR-B07).
 */
#[TypeScript]
enum TransactionSource: string
{
    case Web = 'web';
    case Api = 'api';
    case Telegram = 'telegram';
    case Receipt = 'receipt';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Api => 'API',
            self::Telegram => 'Telegram',
            self::Receipt => 'Struk',
            self::Recurring => 'Berulang',
        };
    }
}
