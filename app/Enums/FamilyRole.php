<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Role of a user inside a family (docs/03-multi-tenancy.md §4).
 */
#[TypeScript]
enum FamilyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /** Can add transactions (web, API, Telegram, receipts). */
    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }

    /** Can manage wallets, categories, budgets and members. */
    public function canManage(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Admin => 'Admin',
            self::Member => 'Anggota',
            self::Viewer => 'Hanya lihat',
        };
    }
}
