<?php

declare(strict_types=1);

final class AccessPolicy
{
    public static function isStaff(array|string $userOrRole): bool
    {
        $role = strtolower(is_array($userOrRole) ? (string)($userOrRole['role'] ?? '') : $userOrRole);
        return str_contains($role, 'staff')
            || str_contains($role, 'manager')
            || str_contains($role, 'admin')
            || str_contains($role, 'director');
    }

    public static function isStudent(array|string $userOrRole): bool
    {
        $role = strtolower(is_array($userOrRole) ? (string)($userOrRole['role'] ?? '') : $userOrRole);
        return str_contains($role, 'student');
    }

    public static function canManagePeople(array $user): bool
    {
        return self::isStaff($user);
    }

    public static function canManageSafeguarding(array $user): bool
    {
        return self::isStaff($user);
    }

    public static function canManageProduction(array $user): bool
    {
        return self::isStaff($user);
    }

    public static function localIdentitySwitchEnabled(): bool
    {
        return strtolower((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production'))) === 'local';
    }
}
