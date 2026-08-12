<?php

declare(strict_types=1);

require_once __DIR__ . '/SchemaGuard.php';

final class RuntimeSchemaGuard
{
    private const EXPECTED_SCHEMA_VERSION = 60;
    private const APCU_TTL_SECONDS = 300;

    public static function requireCurrentSchema(string $projectRoot, string $basePath): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionKey = 'schema_guard_ok_v' . self::EXPECTED_SCHEMA_VERSION;
        if (!empty($_SESSION[$sessionKey])) {
            return;
        }

        $cacheKey = 'ctsmd_schema_guard:' . sha1($projectRoot) . ':v' . self::EXPECTED_SCHEMA_VERSION;
        if (function_exists('apcu_fetch')) {
            $success = false;
            $cached = apcu_fetch($cacheKey, $success);
            if ($success && $cached === true) {
                $_SESSION[$sessionKey] = true;
                return;
            }
        }

        SchemaGuard::requireCurrentSchema($projectRoot, $basePath);
        $_SESSION[$sessionKey] = true;

        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, true, self::APCU_TTL_SECONDS);
        }
    }
}
