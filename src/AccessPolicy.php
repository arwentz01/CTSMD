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
        $environment = strtolower((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production')));
        if ($environment === 'local') {
            return true;
        }

        $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\\d+$/', '', $host) ?: $host;

        $loopbackRequest = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
        $localHost = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);

        return $loopbackRequest && $localHost;
    }
}
