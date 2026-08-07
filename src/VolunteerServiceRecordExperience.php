<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/VolunteerServiceHistoryService.php';

final class VolunteerServiceRecordExperience
{
    private const ROUTE='/volunteer/history';
    private const ALIAS='/volunteer/service-record';
    public static function handles(string $route):bool{return in_array($route,[self::ROUTE,self::ALIAS],true);}

    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);
        if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $record=VolunteerServiceHistoryService::record($db,(int)$user['id']);
        self::page($basePath,$user,$record,isset($_GET['print']));
    }

    private static function page(string $basePath,array $user,array $record,bool $print):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $hours=static fn(int $m):string=>number_format($m/60,1);
        $person=$record['person'];$sub=[['label'=>'Readiness','href'=>'/volunteer-readiness','active'=>false],['label'=>'Opportunities','href'=>'/volunteer-shifts','active'=>false],['label'=>'Service record','href'=>self::ROUTE,'active'=>true],['label'=>'Training','href'=>'/volunteer/training','active'=>false]];
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Volunteer service record · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/volunteer-service-record.css')?>"></head><body class="app-body<?=$print?' print-requested':''?>"><div class="unified-shell"><?php if(!$print)AppNavigation::renderSidebar(self::ROUTE,$basePath,$user);?><main class="unified-main"><?php if(!$print)AppNavigation::renderHeader('Volunteer','Service record',$basePath,$sub);?><div class="vsr-page">
        <section class="vsr-hero"><div><small>CTSMD VERIFIED SERVICE</small><h1><?=$e((string)$person['name'])?></h1><p>A durable record of verified volunteer service, completed training, and CTSMD credentials. Archived production service remains part of this history.</p></div><div class="vsr-actions"><?php if(!$print):?><a class="button" href="<?=$u(self::ROUTE.'?print=1')?>" target="_blank">Print service record</a><?php else:?><button class="button" type="button" onclick="window.print()">Print</button><?php endif;?></div></section>
        <section class="vsr-metrics"><article><b><?=$hours((int)$record['total_minutes'])?></b><span>Verified hours</span></article><article><b><?=(int)$record['production_count']?></b><span>Productions served</span></article><article><b><?=(int)$record['completed_training_count']?></b><span>Verified trainings</span></article><article><b><?=(int)$record['approved_credential_count']?></b><span>Current credentials</span></article></section>
        <div class="vsr-grid"><section class="vsr-panel wide"><header><div><small>SERVICE HISTORY</small><h2>Productions & CTSMD service</h2></div><span><?=count($record['productions'])?> service areas</span></header><?php if(!$record['productions']):?><div class="vsr-empty"><b>No verified service yet.</b><p>Completed shifts and staff-verified manual hours will appear here.</p></div><?php endif;?><?php foreach($record['productions'] as $p):?><article class="vsr-production"><div class="vsr-production-main"><small><?=$e(strtoupper((string)($p['season']?:'CTSMD')))?><?php if($p['production_status']==='archived'||(!$p['is_active']&&$p['production_id'])):?> · HISTORICAL<?php endif;?></small><h3><?=$e((string)$p['title'])?></h3><p><?=date('M j, Y',strtotime((string)$p['first_served_at']))?><?php if($p['first_served_at']!==$p['last_served_at']):?> – <?=date('M j, Y',strtotime((string)$p['last_served_at']))?><?php endif;?> · <?=(int)$p['entries']?> verified entr<?=((int)$p['entries']===1)?'y':'ies'?></p><div class="vsr-tags"><?php foreach($p['category_labels'] as $category):?><span><?=$e((string)$category)?></span><?php endforeach;?></div></div><strong><?=$hours((int)$p['minutes'])?>h</strong></article><?php endforeach;?></section>
        <section class="vsr-panel"><header><div><small>BY YEAR</small><h2>Verified hours</h2></div></header><?php if(!$record['years']):?><p class="vsr-muted">No verified years yet.</p><?php endif;?><?php foreach($record['years'] as $year=>$minutes):?><div class="vsr-row"><span><?=$e((string)$year)?></span><b><?=$hours((int)$minutes)?>h</b></div><?php endforeach;?></section>
        <section class="vsr-panel"><header><div><small>SERVICE AREAS</small><h2>Roles & categories</h2></div></header><?php if(!$record['categories']):?><p class="vsr-muted">Shift categories will appear after verified service.</p><?php endif;?><?php foreach($record['categories'] as $category=>$minutes):?><div class="vsr-row"><span><?=$e((string)$category)?></span><b><?=$hours((int)$minutes)?>h</b></div><?php endforeach;?></section>
        <section class="vsr-panel wide"><header><div><small>CREDENTIALS</small><h2>CTSMD readiness credentials</h2></div></header><?php if(!$record['credentials']):?><div class="vsr-empty compact">No credentials are recorded yet.</div><?php endif;?><div class="vsr-credential-grid"><?php foreach($record['credentials'] as $c):?><article class="vsr-credential <?=$e((string)$c['effective_status'])?>"><small><?=$e(strtoupper((string)$c['category']))?></small><h3><?=$e((string)$c['name'])?></h3><p><?=ucwords(str_replace('_',' ',(string)$c['effective_status']))?><?php if($c['expires_at']):?> · <?=strtotime((string)$c['expires_at'])<time()?'Expired':'Expires'?> <?=date('M j, Y',strtotime((string)$c['expires_at']))?><?php endif;?></p></article><?php endforeach;?></div></section>
        <section class="vsr-panel wide"><header><div><small>TRAINING</small><h2>Verified completions</h2></div></header><?php if(!$record['training']):?><div class="vsr-empty compact">No verified training completions yet.</div><?php endif;?><?php foreach($record['training'] as $t):?><article class="vsr-training"><div><h3><?=$e((string)$t['title'])?></h3><p><?=$t['requirement_name']?'Satisfies '.$e((string)$t['requirement_name']).' · ':''?>Completed <?=date('M j, Y',strtotime((string)$t['completed_at']))?></p></div><span>CTSMD Verified</span></article><?php endforeach;?></section>
        <section class="vsr-panel wide ledger"><header><div><small>VERIFICATION LEDGER</small><h2>Verified service entries</h2></div><span><?=count($record['hours'])?> entries</span></header><?php if(!$record['hours']):?><div class="vsr-empty compact">No verified service entries yet.</div><?php endif;?><?php foreach($record['hours'] as $h):?><article><div><b><?=$e((string)($h['shift_title']?:'Verified service'))?></b><small><?=date('M j, Y',strtotime((string)$h['served_at']))?> · <?=$e((string)($h['production_title']?:'CTSMD'))?><?=$h['shift_category']?' · '.$e((string)$h['shift_category']):''?><?=$h['note']?' · '.$e((string)$h['note']):''?></small></div><strong><?=$hours((int)$h['minutes'])?>h</strong></article><?php endforeach;?></section>
        </div><footer class="vsr-verification"><b>CTSMD Connect · Verified service record</b><p>Generated <?=date('F j, Y')?> from CTSMD's internal verified service records. This page is not a signed certification letter.</p></footer>
        </div></main></div><?php if(!$print):?><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script><?php endif;?></body></html><?php exit;
    }
}
