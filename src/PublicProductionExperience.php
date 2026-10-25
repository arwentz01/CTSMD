<?php

declare(strict_types=1);
require_once __DIR__.'/Database.php';

/** Public, deliberately approved production basics. Never joins cast or schedules. */
final class PublicProductionExperience
{
    public static function handles(string $route):bool{return in_array($route,['/productions','/productions/view'],true);}

    public static function render(string $route,string $basePath):never
    {
        $db=Database::connect(dirname(__DIR__));
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
        $sql="SELECT id,title,season,lifecycle_stage,audition_starts_at,audition_ends_at,performance_starts_at,performance_ends_at
            FROM productions WHERE announcement_published_at IS NOT NULL
              AND lifecycle_stage IN ('upcoming','planning','in_production','performing','completed','archived')";
        if($route==='/productions/view'){
            $q=$db->prepare($sql.' AND id=:id LIMIT 1');$q->execute(['id'=>$id]);$rows=$q->fetchAll();
            if(!$rows)http_response_code(404);
        }else $rows=$db->query($sql.' ORDER BY COALESCE(performance_starts_at,audition_starts_at) DESC,id DESC LIMIT 100')->fetchAll();
        $e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $u=static fn(string $path):string=>$e(($basePath?:'').$path);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CTSMD Productions</title><style>
        body{margin:0;background:#f9f5ee;color:#26202a;font:16px system-ui,sans-serif}main{max-width:940px;margin:auto;padding:24px}
        header{display:flex;justify-content:space-between;align-items:center;gap:16px}a{color:#6c1735}h1,h2{font-family:Georgia,serif}
        h1{color:#53152c}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px}
        article{background:white;border:1px solid #e9d9d1;border-bottom:3px solid #b68b43;border-radius:13px;padding:20px}
        small{font-weight:700;letter-spacing:.06em;color:#7a4356;text-transform:uppercase}
        dl{display:grid;grid-template-columns:110px 1fr;gap:9px}dt{font-weight:700}dd{margin:0}
        .note{background:#f4e9d8;border-radius:10px;padding:12px;font-size:14px}
        </style></head><body><main><header><a href="<?= $u('/') ?>">CTSMD Connect</a><a href="<?= $u('/register') ?>">Auditions &amp; Signups</a></header><h1><?= $route==='/productions'?'Our Productions':($rows?$e($rows[0]['title']):'Production unavailable') ?></h1>
        <?php if(!$rows):?><p>No published production information is available.</p><?php endif;?>
        <div class="grid"><?php foreach($rows as $p):?><article><small><?= $e(str_replace('_',' ',(string)$p['lifecycle_stage'])) ?></small><?php if($route==='/productions'):?><h2><a href="<?= $u('/productions/view?id='.(int)$p['id']) ?>"><?= $e($p['title']) ?></a></h2><?php else:?><h2><?= $e($p['title']) ?></h2><?php endif;?>
        <p><?= $e($p['season']) ?></p><dl>
        <?php foreach(['audition_starts_at'=>'Auditions begin','audition_ends_at'=>'Auditions through','performance_starts_at'=>'Performances begin','performance_ends_at'=>'Performances through'] as $field=>$label):if(!$p[$field])continue;?><dt><?= $e($label) ?></dt><dd><?= $e(date('M j, Y',strtotime((string)$p[$field]))) ?></dd><?php endforeach;?></dl>
        <?php if($route==='/productions/view'):?><p class="note">Additional casting and rehearsal details are available only through approved CTSMD member access or separately published announcements.</p><a href="<?= $u('/register') ?>">Explore auditions &amp; signups</a><?php endif;?></article><?php endforeach;?></div>
        </main></body></html><?php exit;
    }
}
