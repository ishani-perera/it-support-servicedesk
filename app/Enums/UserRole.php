<?php

namespace App\Enums;

/**
 * Centralised definition of user roles.
 *
 * This is the ONLY place role strings are defined. Migrations, models,
 * seeders, policies and views must reference these cases rather than
 * hardcoding 'employee' / 'support' / 'admin'.
 */
enum UserRole: string
{
    case Employee = 'employee';
    case Support = 'support';
    case Admin = 'admin';

    /**
     * Human readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Support => 'IT Support',
            self::Admin => 'Administrator',
        };
    }

    public function isEmployee(): bool
    {
        return $this === self::Employee;
    }

    public function isSupport(): bool
    {
        return $this === self::Support;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Whether this role is IT staff (support or admin).
     */
    public function isStaff(): bool
    {
        return $this->isSupport() || $this->isAdmin();
    }

    /**
     * The staff roles (support + admin) — handy for whereIn() queries.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role->isStaff()));
    }

    /**
     * All backing values, e.g. for DB enum columns and validation rules.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
