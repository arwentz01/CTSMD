<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/CastingCommunicationService.php';

final class MemberCastExperience
{
    public static function handles(string $route):bool{return $route==='/cast';}
    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $requestedId=filter_input(INPUT_GET,'production',FILTER_VALIDATE_INT)?:0;
        $available=ProductionContext::activeProductions($db,$user);
        $availableById=[];foreach($available as $p)$availableById[(int)$p['id']]=$p;
        if($requestedId>0&&!isset($availableById[$requestedId])){http_response_code(404);exit('That production is not available to your account.');}
        $production=$requestedId>0?ProductionContext::selected($db,$user,$requestedId):ProductionContext::selected($db,$user);
        $cast=$production?CastingCommunicationService::publishedCastForProduction($db,(int)$production['id']):null;
        $callbacks=(!$cast&&$production)?CastingCommunicationService::publishedCallbacksForProduction($db,(int)$production['id']):null;
        $publication=$cast?:$callbacks;$showingCast=(bool)$cast;
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Casting announcements · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/member-cast.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/cast',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Your theatre','Casting announcements',$basePath);?><div class="member-cast-page"><section class="member-cast-hero"><small><?=$showingCast?'CAST ANNOUNCEMENT':'CALLBACK ANNOUNCEMENT'?></small><h2><?=$production?$e((string)$production['title']):'Casting updates'?></h2><p>Only callback and cast information deliberately published by CTSMD appears here. Under review, offers, not-cast decisions, withdrawals, and private notes are never shown.</p></section><?php if(!$production):?><section class="member-cast-empty"><h3>No active production is available to your account.</h3></section><?php elseif(!$publication):?><section class="member-cast-empty"><h3>No casting announcement has been published for <?=$e((string)$production['title'])?> yet.</h3><p>When the production team publishes callbacks or the final cast, it will appear here automatically.</p></section><?php else:?><section class="member-cast-production"><header><div><small><?=$e((string)($publication['season']?:'CTSMD PRODUCTION'))?></small><h2><?=$e((string)$publication['title'])?></h2><p><?=$e((string)($publication['headline']?:($showingCast?'Cast announcement':'Callback announcement')))?></p></div><span>Published <?=date('M j · g:i A',strtotime((string)$publication['published_at']))?></span></header><?php if(!empty($publication['member_note'])):?><div class="member-cast-note"><?=nl2br($e((string)$publication['member_note']))?></div><?php endif;?><div class="member-cast-grid"><?php foreach($publication['cast'] as $member):?><article><?php if($showingCast):?><small><?=$e((string)($member['participation_track']?:'CAST'))?></small><h3><?=$e((string)($member['role']?:'Cast'))?></h3><p><?=$e((string)$member['name'])?></p><?php else:?><small>CALLBACK</small><h3><?=$e((string)$member['name'])?></h3><?php if(!empty($member['participation_track'])):?><p><?=$e((string)$member['participation_track'])?></p><?php endif;?><?php endif;?></article><?php endforeach;?></div></section><?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
}
