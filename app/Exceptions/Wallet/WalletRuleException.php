<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

use App\Exceptions\BusinessRuleException;

final class WalletRuleException extends BusinessRuleException
{
    public static function hasTransactions(): self
    {
        return new self('BR-C05', 'Dompet sudah memiliki transaksi sehingga tidak bisa dihapus. Arsipkan saja.');
    }
}
