<?php

declare(strict_types=1);

namespace App\Exceptions\Family;

use App\Exceptions\BusinessRuleException;

final class MembershipRuleException extends BusinessRuleException
{
    public static function ownerIsFixed(): self
    {
        return new self('BR-I01', 'Pemilik keluarga tidak bisa diubah perannya atau dikeluarkan.');
    }

    public static function onlyOwnerManagesAdmins(): self
    {
        return new self('BR-I03', 'Hanya pemilik yang bisa mengatur admin.');
    }

    public static function ownerCannotLeave(): self
    {
        return new self('BR-I01', 'Pemilik tidak bisa keluar dari keluarga. Pindahkan kepemilikan terlebih dahulu.');
    }
}
