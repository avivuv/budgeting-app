<?php

declare(strict_types=1);

namespace App\Exceptions\Receipt;

use App\Exceptions\BusinessRuleException;

final class ReceiptRuleException extends BusinessRuleException
{
    public static function unsupportedType(): self
    {
        return new self('BR-H11', 'Format file tidak didukung. Kirim foto (JPG, PNG, WEBP, HEIC) atau PDF.', 'file');
    }

    public static function tooLarge(int $maxMb): self
    {
        return new self('BR-H11', "Ukuran file maksimal {$maxMb} MB.", 'file');
    }

    public static function tooManyPages(int $maxPages): self
    {
        return new self('BR-H11', "PDF maksimal {$maxPages} halaman.", 'file');
    }

    public static function duplicateFile(): self
    {
        return new self('BR-H04', 'File struk yang sama sudah pernah diunggah.', 'file');
    }

    public static function notConfirmable(): self
    {
        return new self('BR-H07', 'Struk ini tidak sedang menunggu konfirmasi.', 'status');
    }

    public static function notEditable(): self
    {
        return new self('BR-H09', 'Hanya hasil baca yang menunggu konfirmasi yang bisa diubah.', 'status');
    }

    public static function walletRequired(): self
    {
        return new self('BR-H08', 'Pilih dompet sebelum menyimpan.', 'wallet_id');
    }

    public static function categoryRequired(): self
    {
        return new self('BR-H08', 'Pilih kategori sebelum menyimpan.', 'category_id');
    }

    public static function totalRequired(): self
    {
        return new self('BR-H01', 'Total struk harus lebih dari 0.', 'total');
    }

    public static function nothingToSplit(): self
    {
        return new self('BR-H02', 'Struk ini tidak punya item untuk dipecah.', 'mode');
    }
}
