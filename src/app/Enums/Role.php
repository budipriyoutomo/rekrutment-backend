<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case USER = 'user';

    /**
     * Helper: semua role kecuali super admin
     */
    public static function nonSuper(): array
    {
        return [
            self::ADMIN->value,
            self::USER->value,
        ];
    }

    public static function only(array $roles): string
    {
        return implode(',', $roles);
    }
}