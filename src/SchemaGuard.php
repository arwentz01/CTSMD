<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class SchemaGuard
{
    public static function requireConcurrentProductionSchema(string $projectRoot, string $basePath): void
    {
        try {
            $db = Database::connect($projectRoot);
            $productionColumns = self::columns($db, 'productions');
            $channelColumns = self::columns($db, 'channels');

            $missing = [];
            foreach (['is_active', 'activated_at', 'deactivated_at'] as $column) {
                if (!isset($productionColumns[$column])) $missing[] = 'productions.' . $column;
            }
            foreach (['read_audiences_json', 'post_audiences_json'] as $column) {
                if (!isset($channelColumns[$column])) $missing[] = 'channels.' . $column;
            }

            if (!$missing) return;

            self::render($basePath, $missing);
        } catch (PDOException $e) {
            // Let the normal database connection/error handling surface genuine connection problems.
            // Only intercept the known missing-schema case.
            if (str_contains($e->getMessage(), "doesn't exist")) {
                return;
            }
            throw $e;
        }
    }

    private static function columns(PDO $db, string $table): array
    {
        $stmt = $db->query('SHOW COLUMNS FROM `' . $table . '`');
        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            $columns[(string)$row['Field']] = true;
        }
        return $columns;
    }

    private static function render(string $basePath, array $missing): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database update required · CTSMD Connect</title>
<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f1ef;color:#241b1e;margin:0;padding:32px}.card{max-width:760px;margin:8vh auto;background:#fff;border:1px solid #ded5d8;border-radius:18px;padding:32px;box-shadow:0 18px 50px rgba(38,18,25,.08)}small{font-weight:800;letter-spacing:.12em;color:#a6192e}h1{font-family:Georgia,serif;font-size:34px;margin:8px 0 12px}p{line-height:1.6;color:#65575c}code{display:block;background:#191519;color:#fff;padding:14px 16px;border-radius:10px;margin:18px 0;font-size:14px}.missing{font-size:12px;color:#786a6f}.actions{margin-top:22px}.actions a{color:#a6192e;font-weight:700;text-decoration:none}</style></head><body><main class="card"><small>LOCAL DATABASE UPDATE REQUIRED</small><h1>CTSMD Connect needs migration 006.</h1><p>The application code is newer than this database. Run the migration below against the <b>ctsmd</b> database, then refresh this page.</p><code>database/migrations/006_concurrent_productions_and_audiences.sql</code><p class="missing">Missing: <?= $esc(implode(', ', $missing)) ?></p><p>This migration adds concurrent-production activity state and Community audience fields. It does not require a demo seed reset.</p><div class="actions"><a href="<?= $url('/dev/identity') ?>">Development identity →</a></div></main></body></html><?php
        exit;
    }
}
