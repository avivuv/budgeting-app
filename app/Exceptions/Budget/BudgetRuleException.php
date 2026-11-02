<?php

declare(strict_types=1);

namespace App\Exceptions\Budget;

use App\Exceptions\BusinessRuleException;

final class BudgetRuleException extends BusinessRuleException
{
    public static function expenseCategoryOnly(): self
    {
        return new self('BR-F01', 'Budget hanya bisa dibuat untuk kategori pengeluaran.', 'category_id');
    }

    public static function amountOutOfRange(): self
    {
        return new self('BR-A02', 'Budget harus lebih dari 0 dan maksimal Rp 999.999.999.999.', 'amount');
    }
}
