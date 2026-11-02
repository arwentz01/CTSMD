<?php

declare(strict_types=1);

final class PushSchemaGuard
{
    public static function requireCurrent(PDO $db):void
    {
        $tables=[];foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row)$tables[(string)$row[0]]=true;
        $columns=[];foreach($db->query('SHOW COLUMNS FROM notification_preferences')->fetchAll() as $row)$columns[(string)$row['Field']]=true;
        $missing039=[];foreach(['push_enabled','push_schedule','push_forms','push_volunteer','push_community','push_messages'] as $column)if(!isset($columns[$column]))$missing039[]='notification_preferences.'.$column;foreach(['push_subscriptions','push_queue','push_delivery_log'] as $table)if(!isset($tables[$table]))$missing039[]='table '.$table;
        $missing040=[];if(!isset($tables['push_event_cursors']))$missing040[]='table push_event_cursors';
        if(!$missing039&&!$missing040)return;
        $esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');http_response_code(503);header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Push notification update required · CTSMD Connect</title><style>body{font-family:system-ui;background:#f5f1ef;color:#241b1e;padding:32px}.card{max-width:760px;margin:8vh auto;background:#fff;border:1px solid #ded5d8;border-radius:18px;padding:32px}code{display:block;background:#191519;color:#fff;padding:14px;border-radius:10px;margin:10px 0}.missing{font-size:12px;color:#786a6f}</style></head><body><main class="card"><small>DATABASE UPDATE REQUIRED</small><h1>Mobile notifications need two migrations.</h1><p>Run the missing migrations against <b>ctsmd</b> in order, then refresh.</p><?php if($missing039):?><code>database/migrations/039_web_push_notifications.sql</code><p class="missing">Missing: <?=$esc(implode(', ',$missing039))?></p><?php endif;?><?php if($missing040):?><code>database/migrations/040_push_event_cursors.sql</code><p class="missing">Missing: <?=$esc(implode(', ',$missing040))?></p><?php endif;?><p>No seed reset is required.</p></main></body></html><?php exit;
    }
}
