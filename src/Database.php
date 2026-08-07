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
    public static function connect(string $projectRoot): PDO
    {
        self::loadEnv($projectRoot . '/.env');

        $host = self::env('DB_HOST', '127.0.0.1');
        $port = self::env('DB_PORT', '3306');
        $database = self::env('DB_DATABASE', 'ctsmd');
        $username = self::env('DB_USERNAME', 'andrew');
        $password = self::env('DB_PASSWORD', 'password');
        $charset = self::env('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new CTSMDPDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
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

    private static function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return ($value === false || $value === null || $value === '') ? $default : (string)$value;
    }
}
