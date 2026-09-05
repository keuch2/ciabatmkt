<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::User => 'Usuario',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
