<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The reports of docs/07 §5.
 */
#[TypeScript]
enum ReportTab: string
{
    case Cashflow = 'cashflow';
    case Categories = 'categories';
    case Members = 'members';
    case Wallets = 'wallets';
    case Budget = 'budget';
    case Yearly = 'yearly';
    case Merchants = 'merchants';
    case Tags = 'tags';

    public function label(): string
    {
        return match ($this) {
            self::Cashflow => 'Arus kas',
            self::Categories => 'Kategori',
            self::Members => 'Anggota',
            self::Wallets => 'Dompet',
            self::Budget => 'Budget',
            self::Yearly => 'Tahunan',
            self::Merchants => 'Merchant',
            self::Tags => 'Tag',
        };
    }
}
