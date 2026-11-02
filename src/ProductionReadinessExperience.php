<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/WorkspaceUI.php';
require_once __DIR__.'/ProductionReadinessService.php';
require_once __DIR__.'/ProductionChecklistAssignmentService.php';
require_once __DIR__.'/ScheduleAudience.php';

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
        $production=$data['production']??null;$db=Database::connect(dirname(__DIR__));$taskGroups=$production?ScheduleAudience::groups($db,(int)$production['id']):[];$groupMembers=[];foreach($taskGroups as $taskGroup){$members=$db->prepare("SELECT DISTINCT pm.user_id FROM production_group_members pgm JOIN production_memberships pm ON pm.id=pgm.production_membership_id AND pm.status='active' JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status='active' WHERE pgm.group_id=:group_id AND pgm.status='active'");$members->execute(['group_id'=>(int)$taskGroup['id']]);$groupMembers[(int)$taskGroup['id']]=array_map('intval',$members->fetchAll(PDO::FETCH_COLUMN));}$u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$flash=$_SESSION['production_readiness_flash']??null;unset($_SESSION['production_readiness_flash']);$production=$data['production']??null;header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Production readiness · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/production-readiness.css?v=20261025-compact-tasks')?>"><?php WorkspaceUI::operationsStyles($basePath); ?></head><body class="app-body ux-operations ux-workspace ux-operational ux-tasks"><a class="ux-skip" href="#ux-main">Skip to main content</a><div class="unified-shell"><?php AppNavigation::renderSidebar(self::ROUTE,$basePath,$user);?><main class="unified-main" id="ux-main" tabindex="-1"><?php AppNavigation::renderHeader('Production','Readiness',$basePath,AppNavigation::productionSubnav(self::ROUTE,$user));?><div class="pr-page ops-page"><?php if($flash):?><div class="pr-flash <?=$e($flash['type'])?>"><?=$e($flash['message'])?></div><?php endif;?>
        <?php if(!$production):?><section class="pr-empty"><h2>Select an active Working Production first.</h2><p>Readiness is intentionally scoped to one production at a time.</p><a href="<?=$u('/production')?>">Choose production</a></section><?php else:$s=$data['summary'];?><section class="pr-hero"><div><small>WORKING PRODUCTION</small><h2><?=$e((string)$production['title'])?></h2><p><?=$e((string)($production['season']?:'Season not set'))?> · Automated signals plus your production checklist.</p></div><div class="pr-score"><b><?=$s['readiness_percent']===null?'—':(int)$s['readiness_percent'].'%'?></b><span><?=$s['readiness_percent']===null?'No checklist items yet':'checklist complete'?></span></div></section>
        <section class="pr-summary"><article><b><?=(int)$s['automated_attention']?></b><span>system signals need attention</span></article><article><b><?=(int)$s['checklist_open']?></b><span>checklist items open</span></article><article><b><?=(int)$s['overdue']?></b><span>checklist items overdue</span></article><article><b><?=(int)$s['checklist_done']?></b><span>checklist items done</span></article></section>
        <section class="pr-section"><header><div><small>AUTOMATED READINESS</small><h3>What Connect can see</h3></div></header><div class="pr-signals"><?php foreach($data['signals'] as $signal):?><a class="pr-signal <?=$e($signal['status'])?>" href="<?=$u($signal['href'])?>"><span class="dot"></span><div><b><?=$e($signal['label'])?></b><p><?=$e($signal['detail'])?></p></div><?php if($signal['status']==='attention'):?><strong><?=(int)$signal['count']?></strong><?php else:?><strong>✓</strong><?php endif;?></a><?php endforeach;?></div></section>
        <section class="pr-section"><header><div><small>PRODUCTION TASKS</small><h3>Task completion</h3></div><a class="button" href="<?=$u('/production/tasks')?>">Open Tasks →</a></header><p>Manage assignments, owners, due dates and individual completion records in the dedicated Tasks workspace.</p></section>
        <?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
    private static function flash(string $type,string $message):void{$_SESSION['production_readiness_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden():never{http_response_code(403);exit('Restricted');}
}
