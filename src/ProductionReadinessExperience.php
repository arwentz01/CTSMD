<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/ProductionReadinessService.php';

final class ProductionReadinessExperience
{
    private const ROUTE='/production/readiness';
    public static function handles(string $route):bool{return $route===self::ROUTE;}

    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user||!AccessPolicy::canManageProduction($user))self::forbidden();$_SESSION['production_readiness_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$user,$basePath);
        $data=ProductionReadinessService::build($db,$user);self::page($basePath,$user,$data);
    }

    private static function handlePost(PDO $db,array $user,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['production_readiness_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session expired.');self::redirect($basePath.self::ROUTE);}
        try{$action=(string)($_POST['action']??'');$productionId=(int)($_POST['production_id']??0);if($action==='save'){ProductionReadinessService::saveChecklistItem($db,$user,$productionId,$_POST);self::flash('success','Checklist item saved.');}elseif($action==='toggle_done'){ProductionReadinessService::toggleDone($db,$user,$productionId,(int)($_POST['item_id']??0));self::flash('success','Checklist item updated.');}else throw new RuntimeException('Choose a valid readiness action.');}catch(RuntimeException $e){self::flash('error',$e->getMessage());}
        self::redirect($basePath.self::ROUTE);
    }

    private static function page(string $basePath,array $user,array $data):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$flash=$_SESSION['production_readiness_flash']??null;unset($_SESSION['production_readiness_flash']);$production=$data['production']??null;header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Production readiness · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/production-readiness.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar(self::ROUTE,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Readiness',$basePath,[['label'=>'Workspace','href'=>'/production','active'=>false],['label'=>'Readiness','href'=>self::ROUTE,'active'=>true],['label'=>'Schedule','href'=>'/schedule','active'=>false],['label'=>'Attendance','href'=>'/attendance','active'=>false],['label'=>'Playbill','href'=>'/playbills','active'=>false]]);?><div class="pr-page"><?php if($flash):?><div class="pr-flash <?=$e($flash['type'])?>"><?=$e($flash['message'])?></div><?php endif;?>
        <?php if(!$production):?><section class="pr-empty"><h2>Select an active Working Production first.</h2><p>Readiness is intentionally scoped to one production at a time.</p><a href="<?=$u('/production')?>">Choose production</a></section><?php else:$s=$data['summary'];?><section class="pr-hero"><div><small>WORKING PRODUCTION</small><h2><?=$e((string)$production['title'])?></h2><p><?=$e((string)($production['season']?:'Season not set'))?> · Automated signals plus your production checklist.</p></div><div class="pr-score"><b><?=$s['readiness_percent']===null?'—':(int)$s['readiness_percent'].'%'?></b><span><?=$s['readiness_percent']===null?'No checklist items yet':'checklist complete'?></span></div></section>
        <section class="pr-summary"><article><b><?=(int)$s['automated_attention']?></b><span>system signals need attention</span></article><article><b><?=(int)$s['checklist_open']?></b><span>checklist items open</span></article><article><b><?=(int)$s['overdue']?></b><span>checklist items overdue</span></article><article><b><?=(int)$s['checklist_done']?></b><span>checklist items done</span></article></section>
        <section class="pr-section"><header><div><small>AUTOMATED READINESS</small><h3>What Connect can see</h3></div></header><div class="pr-signals"><?php foreach($data['signals'] as $signal):?><a class="pr-signal <?=$e($signal['status'])?>" href="<?=$u($signal['href'])?>"><span class="dot"></span><div><b><?=$e($signal['label'])?></b><p><?=$e($signal['detail'])?></p></div><?php if($signal['status']==='attention'):?><strong><?=(int)$signal['count']?></strong><?php else:?><strong>✓</strong><?php endif;?></a><?php endforeach;?></div></section>
        <section class="pr-section"><header><div><small>PRODUCTION CHECKLIST</small><h3>What your team still needs to do</h3></div></header><div class="pr-layout"><div class="pr-list"><?php if(!$data['checklist']):?><div class="pr-empty-card">No checklist items yet. Add the practical production work Connect cannot infer on its own.</div><?php endif;?><?php foreach($data['checklist'] as $item):$overdue=$item['status']!=='done'&&!empty($item['due_at'])&&strtotime($item['due_at'])<time();?><article class="pr-item <?=$e($item['status'])?><?=$overdue?' overdue':''?>"><form method="post" class="pr-check"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['production_readiness_csrf'])?>"><input type="hidden" name="production_id" value="<?=(int)$production['id']?>"><input type="hidden" name="item_id" value="<?=(int)$item['id']?>"><input type="hidden" name="action" value="toggle_done"><button type="submit" aria-label="<?=$item['status']==='done'?'Reopen':'Complete'?> checklist item"><?=$item['status']==='done'?'✓':'○'?></button></form><div><small><?=$e(strtoupper((string)$item['category']))?><?=$overdue?' · OVERDUE':''?></small><h4><?=$e((string)$item['title'])?></h4><?php if($item['notes']):?><p><?=$e((string)$item['notes'])?></p><?php endif;?><span><?=$item['assignee']?'Owner: '.$e((string)$item['assignee']):'Unassigned'?><?=$item['due_at']?' · Due '.$e(date('M j, g:i A',strtotime($item['due_at']))):''?> · <?=ucwords(str_replace('_',' ',$e((string)$item['status'])))?></span></div></article><?php endforeach;?></div><aside class="pr-composer"><small>ADD CHECKLIST ITEM</small><h3>Track a production task</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['production_readiness_csrf'])?>"><input type="hidden" name="production_id" value="<?=(int)$production['id']?>"><input type="hidden" name="action" value="save"><label>Task<input name="title" maxlength="190" required placeholder="Confirm photographer"></label><label>Category<input name="category" maxlength="100" required value="General"></label><label>Owner<select name="assigned_to_user_id"><option value="">Unassigned</option><?php foreach($data['staff'] as $staff):?><option value="<?=(int)$staff['id']?>"><?=$e((string)$staff['name'])?></option><?php endforeach;?></select></label><label>Due<input type="datetime-local" name="due_at"></label><label>Status<select name="status"><option value="open">Open</option><option value="in_progress">In progress</option><option value="blocked">Blocked</option><option value="done">Done</option></select></label><label>Notes<textarea name="notes" maxlength="1000" rows="4"></textarea></label><button type="submit">Add checklist item</button></form></aside></div></section><?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
    private static function flash(string $type,string $message):void{$_SESSION['production_readiness_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden():never{http_response_code(403);exit('Restricted');}
}
