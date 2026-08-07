<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/TheatreHistoryService.php';

final class TheatreHistoryExperience
{
    private const ROUTE='/theatre-history';
    public static function handles(string $route):bool{return $route===self::ROUTE;}

    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$viewer=Auth::currentUser($db);if(!$viewer)self::redirect($basePath.'/login');
        $subjects=TheatreHistoryService::subjectsForViewer($db,$viewer);$requested=filter_input(INPUT_GET,'student',FILTER_VALIDATE_INT)?:0;$subject=null;
        if($requested>0&&TheatreHistoryService::canViewerSeeSubject($db,(int)$viewer['id'],(int)$requested)){foreach($subjects as $candidate)if((int)$candidate['id']===(int)$requested){$subject=$candidate;break;}}
        if(!$subject&&$subjects)$subject=$subjects[0];$credits=$subject?TheatreHistoryService::creditsForSubject($db,(int)$subject['id']):[];
        self::page($basePath,$viewer,$subjects,$subject,$credits);
    }

    private static function page(string $basePath,array $viewer,array $subjects,?array $subject,array $credits):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Theatre History · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/theatre-history.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar(self::ROUTE,$basePath,$viewer);?><main class="unified-main"><?php AppNavigation::renderHeader('Your CTSMD story','Theatre History',$basePath);?><div class="history-page">
        <section class="history-hero"><div><small>CTSMD VERIFIED CREDITS</small><h2>Every show becomes part of the story.</h2><p>Roles and participation recorded by CTSMD stay here even after a production closes. These are organization-verified credits, separate from any future external theatre credits.</p></div><div class="history-seal"><b>CTSMD</b><span>VERIFIED</span></div></section>
        <?php if(count($subjects)>1):?><nav class="history-subjects" aria-label="Student theatre history"><?php foreach($subjects as $s):?><a class="<?=($subject&&(int)$subject['id']===(int)$s['id'])?'active':''?>" href="<?=$u(self::ROUTE.'?student='.(int)$s['id'])?>"><b><?=$e((string)$s['name'])?></b><small><?=$e((string)$s['relationship'])?></small></a><?php endforeach;?></nav><?php endif;?>
        <?php if(!$subject):?><section class="history-empty"><h2>No Student theatre history is connected to this account yet.</h2><p>Student credits appear here after CTSMD adds the Student to a production roster.</p></section>
        <?php else:?><section class="history-heading"><div><small>THEATRE RECORD</small><h2><?=$e((string)$subject['name'])?></h2><p><?=count($credits)?> verified CTSMD credit<?=count($credits)===1?'':'s'?></p></div><span>Résumé export coming later</span></section>
        <?php if(!$credits):?><section class="history-empty"><h3>No verified production credits yet.</h3><p>Once CTSMD records participation in a production, the verified credit will appear here automatically.</p></section><?php else:?><div class="history-timeline"><?php foreach($credits as $credit):$archived=($credit['production_status']??'')==='archived'||(!(bool)($credit['is_active']??false)&&!empty($credit['participation_ended_at']));?><article class="history-credit"><div class="history-marker"><span></span></div><div class="history-card"><header><div><small><?=$e((string)($credit['season_label']?:'CTSMD PRODUCTION'))?></small><h3><?=$e((string)$credit['production_title'])?></h3></div><strong>✓ CTSMD Verified</strong></header><div class="history-role"><span><small>ROLE / CREDIT</small><b><?=$e((string)($credit['role_title']?:'Production participant'))?></b></span><?php if(!empty($credit['participation_track'])):?><span><small>TRACK / COMPANY</small><b><?=$e((string)$credit['participation_track'])?></b></span><?php endif;?></div><?php if(!empty($credit['groups'])):?><div class="history-groups"><small>PRODUCTION GROUPS</small><div><?php foreach($credit['groups'] as $group):?><span><?=$e((string)$group)?></span><?php endforeach;?></div></div><?php endif;?><footer><span><?=$e((string)$credit['organization_name'])?></span><em><?=$archived?'Historical credit':'Current / recorded credit'?></em></footer></div></article><?php endforeach;?></div><?php endif;?>
        <?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
