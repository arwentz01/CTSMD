<?php

declare(strict_types=1);

final class CTSMDPDO extends PDO
{
    private static function rewriteLegacyIdentity(string $sql): string
    {
        if(session_status()!==PHP_SESSION_ACTIVE)return $sql;
        $userId=(int)($_SESSION['auth_user_id']??0);
        if($userId<1)return $sql;
        return preg_replace_callback('/(?:(\b[a-zA-Z_][a-zA-Z0-9_]*)\.)?is_demo_current_user\s*=\s*1/i',static function(array $m)use($userId):string{
            $prefix=!empty($m[1])?$m[1].'.':'';
            return $prefix.'id = '.$userId;
        },$sql)??$sql;
    }

    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false
    {
        $query=self::rewriteLegacyIdentity($query);
        return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);
    }

    public function prepare(string $query,array $options=[]):PDOStatement|false
    {
        return parent::prepare(self::rewriteLegacyIdentity($query),$options);
    }
}

final class Database
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    public static function connect(string $projectRoot): PDO
    {
        self::loadEnv($projectRoot . '/.env');

        $allowLocalDefaults = self::localDefaultsAllowed();
        $host = self::env('DB_HOST', $allowLocalDefaults ? '127.0.0.1' : null);
        $port = self::env('DB_PORT', $allowLocalDefaults ? '3306' : null);
        $database = self::env('DB_DATABASE', $allowLocalDefaults ? 'ctsmd' : null);
        $username = self::env('DB_USERNAME', $allowLocalDefaults ? 'andrew' : null);
        $password = self::env('DB_PASSWORD', $allowLocalDefaults ? 'password' : null);
        $charset = self::env('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        $connectionKey = hash('sha256', $dsn . "\0" . $username);
        if (isset(self::$connections[$connectionKey])) {
            return self::$connections[$connectionKey];
        }

        $connection = new CTSMDPDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::$connections[$connectionKey] = $connection;
        return $connection;
    }

    private static function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    private static function env(string $key, ?string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value !== false && $value !== null && $value !== '') {
            return (string)$value;
        }
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException($key . ' must be configured outside local development.');
    }

    private static function localDefaultsAllowed(): bool
    {
        $environment = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: ''));
        if ($environment === 'local') {
            return true;
        }
        if (PHP_SAPI === 'cli') {
            return true;
        }
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;
        return in_array($remote, ['127.0.0.1', '::1'], true) && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
