<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/StaffDashboardService.php';

final class StaffDashboardExperience
{
    private const ROUTES=['/staff'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user)self::redirect($basePath.'/login');if(!AccessPolicy::isStaff($user))self::forbidden();$data=StaffDashboardService::build($db,$user);self::page($basePath,$user,$data);
    }

    private static function page(string $basePath,array $user,array $data):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$summary=$data['summary'];$selected=$data['selected_production'];$first=trim((string)($user['first_name']??''));
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#171419"><title>Staff Overview · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/staff-dashboard.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/staff',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Staff','Cross-production overview',$basePath);?><div class="staffdash-page">

        <section class="staffdash-hero"><div><small>CTSMD OPERATIONS</small><h2>Good <?=self::daypart()?>, <?=$e($first?:$user['name'])?>.</h2><p>One operational view across every active production. Working Production still controls where you edit; this page shows what CTSMD needs across the whole organization.</p></div><div class="staffdash-context"><span>WORKING PRODUCTION</span><b><?=$selected?$e((string)$selected['title']):'None selected'?></b><a href="<?=$u('/production')?>">Open workspace →</a></div></section>

        <section class="staffdash-summary" aria-label="Staff overview summary"><article><strong><?=(int)$summary['active_productions']?></strong><span>active productions</span></article><article><strong><?=(int)$summary['upcoming_calls']?></strong><span>calls in next 21 days</span></article><article class="attention"><strong><?=(int)$summary['attention']?></strong><span>items across visible queues</span></article></section>

        <section class="staffdash-section"><header><div><small>TRIAGE</small><h3>What needs attention</h3></div><span>Only queues your role is allowed to manage are shown.</span></header><div class="staffdash-cards"><?php if(!$data['cards']):?><div class="staffdash-empty"><b>No operational queues are assigned to this role.</b><span>Your upcoming production activity is still shown below.</span></div><?php endif;?><?php foreach($data['cards'] as $card):?><a class="staffdash-card <?=$card['count']>0?'has-items':'clear'?>" href="<?=$u((string)$card['href'])?>"><small><?=$e(strtoupper((string)$card['label']))?></small><strong><?=(int)$card['count']?></strong><p><?=$e((string)$card['detail'])?></p><span><?=$card['count']>0?'Review queue →':'Clear for now →'?></span></a><?php endforeach;?></div></section>

        <div class="staffdash-grid"><section class="staffdash-panel upcoming"><header><div><small>NEXT 21 DAYS</small><h3>Across the stage</h3></div><a href="<?=$u('/calendar')?>">Full calendar</a></header><?php if(!$data['upcoming']):?><div class="staffdash-empty"><b>No upcoming calls.</b><span>Active-production schedule items will appear here automatically.</span></div><?php endif;?><?php foreach($data['upcoming'] as $call):?><article><time><b><?=$e(date('M j',strtotime((string)$call['starts_at'])))?></b><span><?=$e(date('g:i A',strtotime((string)$call['starts_at'])))?></span></time><div><small><?=$e((string)$call['production_title'])?></small><b><?=$e((string)$call['title'])?></b><span><?=$e((string)$call['location'])?> · <?=$e(ucwords(str_replace('_',' ',(string)$call['item_type'])))?></span></div></article><?php endforeach;?></section>

        <aside class="staffdash-side"><?php if(AccessPolicy::canManageAccounts($user)):?><section class="staffdash-panel"><header><div><small>PEOPLE</small><h3>Membership review</h3></div><a href="<?=$u('/admin/accounts')?>">All accounts</a></header><?php if(!$data['pending_memberships']):?><div class="staffdash-empty compact"><b>Approval queue is clear.</b></div><?php endif;?><?php foreach($data['pending_memberships'] as $person):?><a class="staffdash-row" href="<?=$u('/admin/accounts/view?id='.(int)$person['id'])?>"><i><?= $e(mb_strtoupper(mb_substr((string)$person['first_name'],0,1).mb_substr((string)$person['last_name'],0,1))) ?></i><div><b><?=$e($person['first_name'].' '.$person['last_name'])?></b><span><?=$e((string)$person['email'])?></span></div><em>Review</em></a><?php endforeach;?></section><?php endif;?>

        <?php if(AccessPolicy::canManageForms($user)):?><section class="staffdash-panel"><header><div><small>INTAKE</small><h3>Registration follow-through</h3></div><a href="<?=$u('/admin/registrations')?>">All</a></header><?php if(!$data['registration_intake']):?><div class="staffdash-empty compact"><b>No unlinked intake right now.</b></div><?php endif;?><?php foreach($data['registration_intake'] as $reg):?><a class="staffdash-row" href="<?=$u('/admin/registrations/intake?submission='.(int)$reg['id'])?>"><i>↗</i><div><b><?=$e($reg['participant_first_name'].' '.$reg['participant_last_name'])?></b><span><?=$e((string)$reg['opportunity_title'])?><?=!empty($reg['production_title'])?' · '.$e((string)$reg['production_title']):''?></span></div><em><?=$e(ucfirst((string)$reg['status']))?></em></a><?php endforeach;?></section><?php endif;?></aside></div>

        <?php if(AccessPolicy::canManageVolunteers($user)):?><section class="staffdash-panel volunteer-panel"><header><div><small>VOLUNTEER COVERAGE</small><h3>Upcoming staffing gaps</h3></div><a href="<?=$u('/admin/volunteer-shifts')?>">Volunteer Operations</a></header><div class="staffdash-shifts"><?php if(!$data['uncovered_shifts']):?><div class="staffdash-empty compact"><b>No uncovered shifts in the next 21 days.</b></div><?php endif;?><?php foreach($data['uncovered_shifts'] as $shift):$needed=max(0,(int)$shift['required_slots']-(int)$shift['filled_slots']);?><a href="<?=$u('/admin/volunteer-shifts/view?id='.(int)$shift['id'])?>"><small><?=$e((string)$shift['production_title'])?></small><b><?=$e((string)$shift['title'])?></b><span><?=$e(date('M j · g:i A',strtotime((string)$shift['starts_at'])))?> · <?=$e((string)$shift['location'])?></span><em><?=$needed?> open of <?=(int)$shift['required_slots']?></em></a><?php endforeach;?></div></section><?php endif;?>

        <section class="staffdash-productions"><header><div><small>ACTIVE PRODUCTIONS</small><h3>Working across CTSMD</h3></div></header><div><?php foreach($data['productions'] as $production):?><article class="<?=$selected&&(int)$selected['id']===(int)$production['id']?'selected':''?>"><span>★</span><div><b><?=$e((string)$production['title'])?></b><small><?=$e((string)($production['season']?:'Active production'))?></small></div><?=$selected&&(int)$selected['id']===(int)$production['id']?'<em>Working production</em>':''?></article><?php endforeach;?></div></section>

        </div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function daypart():string{$h=(int)date('G');return $h<12?'morning':($h<17?'afternoon':'evening');}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden():never{http_response_code(403);exit('Restricted');}
}
