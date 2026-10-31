<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    /** Category type a transaction of this type must use (BR-B02), null for transfers (BR-B03). */
    public function categoryType(): ?CategoryType
    {
        return match ($this) {
            self::Income => CategoryType::Income,
            self::Expense => CategoryType::Expense,
            self::Transfer => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
            self::Transfer => 'Transfer',
        };
    }
}
