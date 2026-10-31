<?php

declare(strict_types=1);

namespace App\Exceptions\Category;

use App\Exceptions\BusinessRuleException;

final class CategoryRuleException extends BusinessRuleException
{
    public static function nameTaken(): self
    {
        return new self('BR-E02', 'Nama kategori sudah dipakai.', 'name');
    }

    public static function parentTooDeep(): self
    {
        return new self('BR-E01', 'Sub-kategori tidak bisa memiliki sub-kategori lagi.', 'parent_id');
    }

    public static function parentTypeMismatch(): self
    {
        return new self('BR-E01', 'Sub-kategori harus memiliki jenis yang sama dengan induknya.', 'parent_id');
    }

    public static function inUse(): self
    {
        return new self('BR-E03', 'Kategori sudah dipakai transaksi. Arsipkan atau gabungkan ke kategori lain.');
    }

    public static function hasChildren(): self
    {
        return new self('BR-E01', 'Hapus atau pindahkan sub-kategorinya terlebih dahulu.');
    }

    public static function mergeTypeMismatch(): self
    {
        return new self('BR-E03', 'Kategori hanya bisa digabung dengan kategori berjenis sama.', 'target_id');
    }

    public static function mergeIntoItself(): self
    {
        return new self('BR-E03', 'Pilih kategori tujuan yang berbeda.', 'target_id');
    }
}
