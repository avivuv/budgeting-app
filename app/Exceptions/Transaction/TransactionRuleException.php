<?php

declare(strict_types=1);

namespace App\Exceptions\Transaction;

use App\Exceptions\BusinessRuleException;

final class TransactionRuleException extends BusinessRuleException
{
    public static function categoryRequired(): self
    {
        return new self('BR-B02', 'Pilih kategori untuk transaksi ini.', 'category_id');
    }

    public static function categoryTypeMismatch(): self
    {
        return new self('BR-B02', 'Jenis kategori tidak sesuai dengan jenis transaksi.', 'category_id');
    }

    public static function archivedCategory(): self
    {
        return new self('BR-B05', 'Kategori ini sudah diarsipkan dan tidak bisa dipakai untuk transaksi baru.', 'category_id');
    }

    public static function transferHasNoCategory(): self
    {
        return new self('BR-B03', 'Transfer tidak memakai kategori. Hapus transfer dari pilihan.', 'category_id');
    }

    public static function transferTargetRequired(): self
    {
        return new self('BR-B03', 'Pilih dompet tujuan transfer.', 'to_wallet_id');
    }

    public static function transferToSameWallet(): self
    {
        return new self('BR-B03', 'Dompet asal dan tujuan transfer tidak boleh sama.', 'to_wallet_id');
    }

    public static function archivedWallet(string $field = 'wallet_id'): self
    {
        return new self('BR-B05', 'Dompet ini sudah diarsipkan dan tidak bisa dipakai untuk transaksi baru.', $field);
    }

    public static function dateInFuture(): self
    {
        return new self('BR-B06', 'Tanggal transaksi tidak boleh melewati hari ini.', 'date');
    }

    public static function dateTooOld(): self
    {
        return new self('BR-B06', 'Tanggal transaksi tidak boleh lebih dari 5 tahun yang lalu.', 'date');
    }

    public static function amountOutOfRange(): self
    {
        return new self('BR-A02', 'Nominal harus lebih dari 0 dan maksimal Rp 999.999.999.999.', 'amount');
    }
}
