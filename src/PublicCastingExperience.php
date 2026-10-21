<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/CastingCommunicationService.php';

final class PublicCastingExperience
{
    public static function handles(string $route):bool{return $route==='/public/casting';}

    public static function render(string $basePath):never
    {
        $db=Database::connect(dirname(__DIR__));
        $slug=trim((string)($_GET['slug']??''));
        $production=CastingCommunicationService::publicProductionBySlug($db,$slug);
        if(!$production){http_response_code(404);self::page(null,null,false);}

        $cast=CastingCommunicationService::publishedCastForProduction($db,(int)$production['id']);
        $callbacks=$cast?null:CastingCommunicationService::publishedCallbacksForProduction($db,(int)$production['id']);
        $publication=$cast?:$callbacks;
        self::page($production,$publication,(bool)$cast);
    }

    private static function page(?array $production,?array $publication,bool $showingCast):never
    {
        header('Content-Type:text/html; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title><?= $production?$e((string)$production['title']).' casting announcement':'Casting announcement' ?> · CTSMD</title><style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#231b1e;background:#fff}
        *{box-sizing:border-box}body{margin:0;background:#fff}.embed{max-width:1000px;margin:0 auto;padding:24px}.eyebrow{font-size:.75rem;letter-spacing:.12em;font-weight:800;color:#9e1733}.head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;border-bottom:1px solid #e9e1e4;padding-bottom:16px;margin-bottom:18px}.head h1{margin:.2rem 0 0;font-size:clamp(1.6rem,4vw,2.5rem)}.head p{margin:.35rem 0 0;color:#70656a}.badge{font-size:.72rem;font-weight:800;padding:6px 9px;border-radius:999px;background:#f4edef;color:#7b2436;white-space:nowrap}.note{background:#faf7f8;border:1px solid #eadfe2;border-radius:12px;padding:14px 16px;margin-bottom:16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}.person{border:1px solid #e8e0e3;border-radius:12px;padding:14px;background:#fff}.person small{display:block;font-size:.7rem;letter-spacing:.1em;color:#8b7e83;font-weight:800}.person h2{font-size:1.05rem;margin:.3rem 0}.person p{margin:0;color:#70656a}.empty{padding:28px 0;color:#70656a}.footer{margin-top:18px;padding-top:14px;border-top:1px solid #eee7e9;font-size:.75rem;color:#8b7e83}@media(max-width:600px){.embed{padding:16px}.head{display:block}.badge{display:inline-block;margin-top:10px}}
        </style></head><body><main class="embed">
        <?php if(!$production):?><section class="empty"><h1>Announcement unavailable</h1><p>This casting announcement is not currently public.</p></section>
        <?php elseif(!$publication):?><header class="head"><div><span class="eyebrow">CTSMD CASTING</span><h1><?=$e((string)$production['title'])?></h1><p>No casting announcement is currently published.</p></div></header>
        <?php else:?><header class="head"><div><span class="eyebrow"><?=$showingCast?'CAST ANNOUNCEMENT':'CALLBACK ANNOUNCEMENT'?></span><h1><?=$e((string)$production['title'])?></h1><p><?=$e((string)($publication['headline']?:($showingCast?'Meet the cast':'Callbacks')))?></p></div><span class="badge">Published <?=date('M j, Y',strtotime((string)$publication['published_at']))?></span></header>
        <?php if(!empty($publication['member_note'])):?><div class="note"><?=nl2br($e((string)$publication['member_note']))?></div><?php endif;?>
        <section class="grid"><?php foreach($publication['cast'] as $member):$publicName=self::publicName((string)$member['name']);?><article class="person"><?php if($showingCast):?><small><?=$e((string)($member['participation_track']?:'CAST'))?></small><h2><?=$e((string)($member['role']?:'Cast'))?></h2><p><?=$e($publicName)?></p><?php else:?><small>CALLBACK</small><h2><?=$e($publicName)?></h2><?php if(!empty($member['participation_track'])):?><p><?=$e((string)$member['participation_track'])?></p><?php endif;?><?php endif;?></article><?php endforeach;?></section>
        <footer class="footer">Children’s Theatre of Southern Maryland · Public names are shown as first name + last initial.</footer>
        <?php endif;?></main></body></html><?php exit;
    }

    private static function publicName(string $fullName):string
    {
        $parts=preg_split('/\s+/',trim($fullName))?:[];
        if(!$parts)return '';
        $first=array_shift($parts);
        $last=$parts?end($parts):'';
        return $last!==''?$first.' '.mb_strtoupper(mb_substr((string)$last,0,1)).'.':$first;
    }
}
