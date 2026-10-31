<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum WalletType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Ewallet = 'ewallet';
    case CreditCard = 'credit_card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Bank => 'Bank',
            self::Ewallet => 'E-wallet',
            self::CreditCard => 'Kartu kredit',
            self::Other => 'Lainnya',
        };
    }
}
