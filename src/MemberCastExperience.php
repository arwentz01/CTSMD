<?php
declare(strict_types=1);
require_once __DIR__.'/MemberUI.php';
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/CastingCommunicationService.php';
require_once __DIR__.'/MemberParticipationService.php';
require_once __DIR__.'/ProductionTeamPublicationService.php';

final class MemberCastExperience
{
    public static function handles(string $route):bool{return $route==='/cast';}
    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $subjects=MemberParticipationService::subjects($db,(int)$user['id']);
        $available=array_values(array_filter(MemberParticipationService::productions($db,$user,$subjects),static fn(array $p):bool=>$p['can_schedule']));
        $requestedId=filter_input(INPUT_GET,'production',FILTER_VALIDATE_INT)?:0;$availableById=[];foreach($available as $p)$availableById[(int)$p['id']]=$p;
        if($requestedId>0&&!isset($availableById[$requestedId])){http_response_code(404);exit('That production is not available in your personal or household context.');}
        $target=$requestedId?[$availableById[$requestedId]]:$available;
        $items=[];
        foreach($target as $production){
            $pid=(int)$production['id'];
            $cast=CastingCommunicationService::publishedCastForProduction($db,$pid);
            $callbacks=$cast?null:CastingCommunicationService::publishedCallbacksForProduction($db,$pid);
            $publication=$cast?:$callbacks;
            $pb=$db->prepare("SELECT id FROM playbills WHERE production_id=? AND public_enabled=1 AND editorial_status IN ('published','archived') ORDER BY id DESC LIMIT 1");$pb->execute([$pid]);$pbid=(int)$pb->fetchColumn();$team=$pbid?ProductionTeamPublicationService::snapshot($db,$pbid,true):[];$teamByPerson=[];
            foreach($team as $index=>$credit){$key=!empty($credit['user_id'])?'user-'.$credit['user_id']:'legacy-'.$index;$teamByPerson[$key]??=['name'=>$credit['name'],'titles'=>[]];$teamByPerson[$key]['titles'][$credit['participation_role']]=true;}
            $items[]=['production'=>$production,'publication'=>$publication,'cast'=>(bool)$cast,'team'=>array_values($teamByPerson)];
        }
        $u=static fn(string $p):string=>($basePath?:'').$p;
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        header('Content-Type:text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Casting announcements · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/member-cast.css')?>"><?php MemberUI::styles($basePath); ?></head><body class="app-body<?=MemberUI::bodyClass()?>"><div class="unified-shell"><?php AppNavigation::renderSidebar('/cast',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Your theatre','Casting announcements',$basePath); MemberUI::wayfinding($basePath);?><div class="member-cast-page">
        <section class="member-cast-hero"><h2>Cast &amp; company</h2><p>Published casting announcements and reviewed team credits for your productions. Private decisions, offers and participant details stay in their own workflows.</p><a href="<?=$u('/notifications#casting-offers')?>">My casting offers</a></section>
        <?php if(!$items):?><section class="member-cast-empty"><h3>No current rostered productions are available to your account.</h3></section><?php endif;?>
        <?php foreach($items as $entry):$production=$entry['production'];$publication=$entry['publication'];$showingCast=$entry['cast'];?>
        <?php if(!$publication):?><section class="member-cast-empty"><h3><?=$e($production['title'])?></h3><p>No casting announcement has been published for this production yet.</p></section>
        <?php else:?><section class="member-cast-production"><header><div><small><?=$e($publication['season']?:'CTSMD PRODUCTION')?></small><h2><?=$e($publication['title'])?></h2><p><?=$e($publication['headline']?:($showingCast?'Cast announcement':'Callback announcement'))?></p></div><span>Published <?=date('M j · g:i A',strtotime((string)$publication['published_at']))?></span></header><?php if(!empty($publication['member_note'])):?><div class="member-cast-note"><?=nl2br($e($publication['member_note']))?></div><?php endif;?><div class="member-cast-grid"><?php foreach($publication['cast'] as $member):?><article><?php if($showingCast):?><small><?=$e($member['participation_track']?:'CAST')?></small><h3><?=$e($member['role']?:'Cast')?></h3><p><?=$e($member['name'])?></p><?php else:?><small>CALLBACK</small><h3><?=$e($member['name'])?></h3><?php if(!empty($member['participation_track'])):?><p><?=$e($member['participation_track'])?></p><?php endif;?><?php endif;?></article><?php endforeach;?></div></section><?php endif;?>
        <?php if($entry['team']):?><section class="member-company-team"><h3>Production team · <?=$e($production['title'])?></h3><dl><?php foreach($entry['team'] as $credit):?><div><dt><?=$e($credit['name'])?></dt><dd><?=$e(implode(' · ',array_keys($credit['titles'])))?></dd></div><?php endforeach;?></dl></section><?php endif;?>
        <?php endforeach;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
}
