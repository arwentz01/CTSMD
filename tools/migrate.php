<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Support\Environment;

$basePath = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($basePath): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = $basePath . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

Environment::load($basePath . '/.env');

$config = require $basePath . '/config/database.php';
$migrationPath = $basePath . '/database/migrations/001_foundation_schema.sql';
$sql = file_get_contents($migrationPath);

if ($sql === false) {
    fwrite(STDERR, "Unable to read migration: {$migrationPath}\n");
    exit(1);
}

$pdo = Connection::make($config);
$pdo->exec($sql);

echo "Applied foundation schema to {$config['database']}.\n";
