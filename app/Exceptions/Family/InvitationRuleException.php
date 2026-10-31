<?php

declare(strict_types=1);

namespace App\Exceptions\Family;

use App\Exceptions\BusinessRuleException;

final class InvitationRuleException extends BusinessRuleException
{
    public static function expired(): self
    {
        return new self('BR-I02', 'Undangan sudah kedaluwarsa. Minta undangan baru ke pengundang.');
    }

    public static function alreadyAccepted(): self
    {
        return new self('BR-I02', 'Undangan ini sudah dipakai.');
    }

    public static function emailMismatch(): self
    {
        return new self('BR-I02', 'Undangan ini untuk alamat email lain. Masuk dengan email yang diundang.');
    }

    public static function alreadyMember(): self
    {
        return new self('BR-I02', 'Orang ini sudah menjadi anggota keluarga.', 'email');
    }

    public static function invalidRole(): self
    {
        return new self('BR-I03', 'Peran ini tidak bisa diberikan lewat undangan.', 'role');
    }
}
