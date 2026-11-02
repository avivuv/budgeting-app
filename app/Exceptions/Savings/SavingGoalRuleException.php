<?php

declare(strict_types=1);

namespace App\Exceptions\Savings;

use App\Exceptions\BusinessRuleException;

final class SavingGoalRuleException extends BusinessRuleException
{
    public static function targetOutOfRange(): self
    {
        return new self('BR-K01', 'Target harus lebih dari 0 dan maksimal Rp 999.999.999.999.', 'target_amount');
    }

    public static function linkedToWallet(): self
    {
        return new self('BR-K02', 'Tujuan ini mengikuti saldo dompet, jadi tidak bisa diisi manual. Catat transfer ke dompet tersebut.', 'amount');
    }

    public static function belowZero(): self
    {
        return new self('BR-K02', 'Jumlah yang diambil melebihi tabungan yang terkumpul.', 'amount');
    }
}
