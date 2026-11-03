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

    public static function archivedCategory(): self
    {
        return new self('BR-F09', 'Kategori ini sudah diarsipkan dan tidak bisa diberi budget baru.', 'category_id');
    }

    public static function periodTooFar(): self
    {
        return new self('BR-F08', 'Budget hanya bisa diatur sampai satu periode ke depan.', 'period');
    }

    /**
     * The same rule, reported on one row of a list field (e.g. budgets.1.category_id).
     */
    public function forRow(string $list, int $index): self
    {
        return new self($this->rule, $this->userMessage(), $list.'.'.$index.'.'.($this->field() ?? 'category_id'));
    }
}
