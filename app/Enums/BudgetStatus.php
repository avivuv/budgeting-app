<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * BR-F04: safe < 80%, warning 80–<100%, over ≥ 100%.
 */
#[TypeScript]
enum BudgetStatus: string
{
    case Safe = 'safe';
    case Warning = 'warning';
    case Over = 'over';

    public function label(): string
    {
        return match ($this) {
            self::Safe => 'Aman',
            self::Warning => 'Waspada',
            self::Over => 'Lewat',
        };
    }
}
