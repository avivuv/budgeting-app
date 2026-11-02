<?php

declare(strict_types=1);

namespace App\Exceptions\Receipt;

use App\Exceptions\BusinessRuleException;

/**
 * BR-H05: the family used its monthly receipt scans (docs/13: HTTP 429).
 */
final class ReceiptQuotaExceededException extends BusinessRuleException
{
    public function __construct(int $quota)
    {
        parent::__construct('BR-H05', "Kuota scan struk bulan ini ({$quota}) sudah habis. Catat transaksi secara manual.", 'file');
    }

    public function status(): int
    {
        return 429;
    }
}
