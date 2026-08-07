<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class SchemaGuard
{
    public static function requireCurrentSchema(string $projectRoot, string $basePath): void
    {
        try {
            $db = Database::connect($projectRoot);
            $productionColumns = self::columns($db, 'productions');
            $channelColumns = self::columns($db, 'channels');
            $formColumns = self::columns($db, 'forms');
            $assignmentColumns = self::columns($db, 'form_assignments');
            $playbillColumns = self::columns($db, 'playbills');
            $tables = self::tables($db);

            $missing006 = [];
            foreach (['is_active', 'activated_at', 'deactivated_at'] as $column) {
                if (!isset($productionColumns[$column])) $missing006[] = 'productions.' . $column;
            }
            foreach (['read_audiences_json', 'post_audiences_json'] as $column) {
                if (!isset($channelColumns[$column])) $missing006[] = 'channels.' . $column;
            }

            $missing007 = [];
            foreach (['production_id', 'created_by_user_id', 'created_at', 'updated_at'] as $column) {
                if (!isset($formColumns[$column])) $missing007[] = 'forms.' . $column;
            }
            foreach (['production_id', 'assigned_by_user_id', 'assigned_at'] as $column) {
                if (!isset($assignmentColumns[$column])) $missing007[] = 'form_assignments.' . $column;
            }

            $missing008 = [];
            foreach (['display_title','subtitle','cover_note','created_by_user_id','published_at','created_at','updated_at'] as $column) {
                if (!isset($playbillColumns[$column])) $missing008[] = 'playbills.' . $column;
            }
            if (!isset($tables['playbill_sections'])) $missing008[] = 'table playbill_sections';

            $missing009 = [];
            if (!isset($tables['production_resources'])) $missing009[] = 'table production_resources';

            $missing010 = [];
            if (!isset($channelColumns['access_mode'])) $missing010[] = 'channels.access_mode';
            foreach (['teams','team_members','channel_members','channel_teams'] as $table) {
                if (!isset($tables[$table])) $missing010[] = 'table ' . $table;
            }

            if (!$missing006 && !$missing007 && !$missing008 && !$missing009 && !$missing010) return;
            self::render($missing006, $missing007, $missing008, $missing009, $missing010);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), "doesn't exist")) return;
            throw $e;
        }
    }

    public static function requireConcurrentProductionSchema(string $projectRoot, string $basePath): void
    {
        self::requireCurrentSchema($projectRoot, $basePath);
    }

    private static function columns(PDO $db, string $table): array
    {
        $stmt = $db->query('SHOW COLUMNS FROM `' . $table . '`');
        $columns = [];
        foreach ($stmt->fetchAll() as $row) $columns[(string)$row['Field']] = true;
        return $columns;
    }

    private static function tables(PDO $db): array
    {
        $tables=[];
        foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) $tables[(string)$row[0]]=true;
        return $tables;
    }

    private static function render(array $missing006, array $missing007, array $missing008, array $missing009, array $missing010): never
    {
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $steps = [];
        if ($missing006) $steps[] = ['file' => 'database/migrations/006_concurrent_productions_and_audiences.sql', 'missing' => $missing006];
        if ($missing007) $steps[] = ['file' => 'database/migrations/007_form_management_and_context.sql', 'missing' => $missing007];
        if ($missing008) $steps[] = ['file' => 'database/migrations/008_playbill_management.sql', 'missing' => $missing008];
        if ($missing009) $steps[] = ['file' => 'database/migrations/009_production_resources.sql', 'missing' => $missing009];
        if ($missing010) $steps[] = ['file' => 'database/migrations/010_teams_and_private_channels.sql', 'missing' => $missing010];
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database update required · CTSMD Connect</title><style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f1ef;color:#241b1e;margin:0;padding:32px}.card{max-width:780px;margin:8vh auto;background:#fff;border:1px solid #ded5d8;border-radius:18px;padding:32px;box-shadow:0 18px 50px rgba(38,18,25,.08)}small{font-weight:800;letter-spacing:.12em;color:#a6192e}h1{font-family:Georgia,serif;font-size:34px;margin:8px 0 12px}p{line-height:1.6;color:#65575c}code{display:block;background:#191519;color:#fff;padding:14px 16px;border-radius:10px;margin:10px 0;font-size:14px}.missing{font-size:12px;color:#786a6f;margin-bottom:20px}</style></head><body><main class="card"><small>LOCAL DATABASE UPDATE REQUIRED</small><h1>CTSMD Connect needs a database migration.</h1><p>The application code is newer than this database. Run the following migration<?= count($steps)===1?'':'s' ?> against the <b>ctsmd</b> database in this order, then refresh.</p><?php foreach($steps as $step):?><code><?= $esc($step['file']) ?></code><p class="missing">Missing: <?= $esc(implode(', ',$step['missing'])) ?></p><?php endforeach;?><p>No demo seed reset is required.</p></main></body></html><?php exit;
    }
}
