<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/CastingCommunicationService.php';

final class MemberCastExperience
{
    public static function handles(string $route):bool{return $route==='/cast';}
    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $staff=AccessPolicy::canManageProduction($user);
        $available=ProductionContext::activeProductions($db,$user);
        $requestedId=filter_input(INPUT_GET,'production',FILTER_VALIDATE_INT)?:0;
        $availableById=[];
        foreach($available as $p)$availableById[(int)$p['id']]=$p;
        if($requestedId>0&&!isset($availableById[$requestedId])){http_response_code(404);exit('That production is not available to your account.');}
        // Staff may focus one selected show; student/family view is always
        // consolidated across every show they currently have roster access to.
        $target=$staff?(($requestedId>0)?[$availableById[$requestedId]]:array_values(array_filter($available,
            static fn(array $p):bool=>(int)$p['id']===(int)(ProductionContext::selected($db,$user)['id']??0)))):$available;
        $items=[];
        foreach($target as $production){
            $pid=(int)$production['id'];
            $cast=CastingCommunicationService::publishedCastForProduction($db,$pid);
            $callbacks=$cast?null:CastingCommunicationService::publishedCallbacksForProduction($db,$pid);
            $publication=$cast?:$callbacks;
            $items[]=['production'=>$production,'publication'=>$publication,'cast'=>(bool)$cast];
        }
        $u=static fn(string $p):string=>($basePath?:'').$p;
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        header('Content-Type:text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Casting announcements · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/member-cast.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/cast',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Your theatre','Casting announcements',$basePath);?><div class="member-cast-page">
        <section class="member-cast-hero"><small>YOUR PRODUCTIONS</small><h2>Published casting announcements</h2><p>Announcements for all productions on your current roster. Private casting decisions, unaccepted offers, and staff notes are not shown.</p></section>
        <?php if(!$items):?><section class="member-cast-empty"><h3>No current rostered productions are available to your account.</h3></section><?php endif;?>
        <?php foreach($items as $entry):$production=$entry['production'];$publication=$entry['publication'];$showingCast=$entry['cast'];?>
        <?php if(!$publication):?><section class="member-cast-empty"><h3><?=$e($production['title'])?></h3><p>No casting announcement has been published for this production yet.</p></section>
        <?php else:?><section class="member-cast-production"><header><div><small><?=$e($publication['season']?:'CTSMD PRODUCTION')?></small><h2><?=$e($publication['title'])?></h2><p><?=$e($publication['headline']?:($showingCast?'Cast announcement':'Callback announcement'))?></p></div><span>Published <?=date('M j · g:i A',strtotime((string)$publication['published_at']))?></span></header><?php if(!empty($publication['member_note'])):?><div class="member-cast-note"><?=nl2br($e($publication['member_note']))?></div><?php endif;?><div class="member-cast-grid"><?php foreach($publication['cast'] as $member):?><article><?php if($showingCast):?><small><?=$e($member['participation_track']?:'CAST')?></small><h3><?=$e($member['role']?:'Cast')?></h3><p><?=$e($member['name'])?></p><?php else:?><small>CALLBACK</small><h3><?=$e($member['name'])?></h3><?php if(!empty($member['participation_track'])):?><p><?=$e($member['participation_track'])?></p><?php endif;?><?php endif;?></article><?php endforeach;?></div></section><?php endif;?>
        <?php endforeach;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
}
