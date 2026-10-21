<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/CastingService.php';
require_once __DIR__.'/CastingCommunicationService.php';

final class CastingExperience
{
    private const ROUTE='/production/casting';
    public static function handles(string $route):bool{return $route===self::ROUTE;}

    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);
        if(!$user||!AccessPolicy::canManageProduction($user))self::forbidden();
        $_SESSION['casting_csrf']??=bin2hex(random_bytes(24));
        $production=ProductionContext::selected($db,$user);
        if(!$production)self::page($basePath,$user,null,[],[],[],[],[],[],null);
        if($_SERVER['REQUEST_METHOD']==='POST')self::post($db,$user,(int)$production['id'],$basePath);
        self::page($basePath,$user,$production,CastingService::board($db,(int)$production['id']),CastingService::intakeCandidates($db,(int)$production['id']),CastingService::peopleCandidates($db,(int)$production['id']),CastingService::groups($db,(int)$production['id']),['callback'=>CastingCommunicationService::publication($db,(int)$production['id'],'callback'),'cast'=>CastingCommunicationService::publication($db,(int)$production['id'],'cast')],CastingCommunicationService::publicSettings($db,(int)$production['id']),$_SESSION['casting_flash']??null);
    }

    private static function post(PDO $db,array $user,int $productionId,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['casting_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session expired.');self::redirect($basePath.self::ROUTE);}
        $selected=ProductionContext::selected($db,$user);if(!$selected||(int)$selected['id']!==$productionId){self::flash('error','The Working Production changed before this casting action was saved.');self::redirect($basePath.self::ROUTE);}
        try{
            $action=(string)($_POST['action']??'');
            if($action==='add_intake'){CastingService::add($db,$productionId,(int)$user['id'],(int)($_POST['user_id']??0),(int)($_POST['submission_id']??0));self::flash('success','Audition intake added to the casting board.');}
            elseif($action==='add_person'){CastingService::add($db,$productionId,(int)$user['id'],(int)($_POST['user_id']??0),null);self::flash('success','Student added to the casting board.');}
            elseif($action==='update'){CastingService::update($db,$productionId,(int)$user['id'],(int)($_POST['record_id']??0),$_POST);self::flash('success','Casting decision saved privately. No family communication was sent.');}
            elseif($action==='finalize'){CastingService::finalizeRoster($db,$productionId,(int)$user['id'],(int)($_POST['record_id']??0),(array)($_POST['group_ids']??[]));self::flash('success','Casting finalized to the production roster. No family communication was sent automatically.');}
            elseif($action==='send_result'){$queued=CastingCommunicationService::sendResult($db,$productionId,(int)$user['id'],(int)($_POST['record_id']??0),$basePath);self::flash('success','Casting result queued for '.max(1,$queued).' family recipient'.($queued===1?'':'s').'.');}
            elseif($action==='save_publication'){
                $type=(string)($_POST['publication_type']??'cast');
                CastingCommunicationService::savePublication($db,$productionId,(int)$user['id'],$type,(string)($_POST['headline']??''),(string)($_POST['member_note']??''),false);
                self::flash('success',ucfirst($type).' publication draft saved.');
            }
            elseif($action==='publish_publication'){
                $type=(string)($_POST['publication_type']??'cast');
                CastingCommunicationService::savePublication($db,$productionId,(int)$user['id'],$type,(string)($_POST['headline']??''),(string)($_POST['member_note']??''),true);
                self::flash('success',$type==='callback'?'Callbacks published to production members in Connect.':'Cast published to production members in Connect.');
            }
            elseif($action==='unpublish_publication'){
                $type=(string)($_POST['publication_type']??'cast');
                CastingCommunicationService::unpublishPublication($db,$productionId,(int)$user['id'],$type);
                self::flash('success',$type==='callback'?'Callbacks unpublished.':'Cast unpublished.');
            }
            elseif($action==='toggle_public_page'){
                $enabled=(string)($_POST['public_enabled']??'0')==='1';
                CastingCommunicationService::setPublicPage($db,$productionId,(int)$user['id'],$enabled);
                self::flash('success',$enabled?'Public website casting page enabled.':'Public website casting page disabled.');
            }
            else throw new RuntimeException('Choose a valid casting action.');
        }catch(RuntimeException $e){self::flash('error',$e->getMessage());}
        self::redirect($basePath.self::ROUTE);
    }

    private static function page(string $basePath,array $user,?array $production,array $board,array $intake,array $people,array $groups,array $publication,array $publicSettings,?array $flash):never
    {
        unset($_SESSION['casting_flash']);$u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $counts=['under_review'=>0,'callback'=>0,'offered'=>0,'cast'=>0,'not_cast'=>0,'withdrawn'=>0];foreach($board as $r)if(isset($counts[$r['casting_status']]))$counts[$r['casting_status']]++;
        $sub=AppNavigation::productionSubnav(self::ROUTE,$user);
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Casting · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/casting.css?v=20261021-4')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar(self::ROUTE,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Casting',$basePath,$sub);?><div class="cast-page">
        <?php if(!$production):?><section class="cast-empty"><h2>Select an active production first.</h2><p>Casting is a Working-Production operation.</p><a class="button" href="<?=$u('/production')?>">Choose production</a></section>
        <?php else:?><section class="cast-hero"><div><small>CASTING BOARD</small><h2><?=$e((string)$production['title'])?></h2><p>Decisions stay private until staff explicitly sends a result or publishes the cast. Finalizing roster access still does not communicate a result automatically.</p></div><a href="<?=$u('/production/people')?>">Production roster →</a></section>
        <?php if($flash):?><div class="cast-flash <?=$e((string)$flash['type'])?>"><?=$e((string)$flash['message'])?></div><?php endif;?>
        <section class="cast-publications">
            <header><div><small>CASTING RELEASES</small><h2>Callbacks & cast</h2><p>Publish only the lists families should see. If both are published, the Cast announcement takes priority on the member page.</p></div><?php if($publication['callback']['status']==='published'||$publication['cast']['status']==='published'):?><a href="<?=$u('/cast?production='.(int)$production['id'])?>">View member page →</a><?php endif;?></header>
            <?php foreach(['callback'=>'Callbacks','cast'=>'Cast'] as $publicationType=>$publicationLabel):$release=$publication[$publicationType];$isPublished=$release['status']==='published';?>
            <details class="cast-release-row">
                <summary><span><b><?=$e($publicationLabel)?></b><small><?=$isPublished&&$release['published_at']?'Published '.date('M j · g:i A',strtotime((string)$release['published_at'])):($publicationType==='callback'?'Not published · only Callback decisions are included':'Not published · only finalized Cast decisions are included')?></small></span><em class="<?=$isPublished?'published':'draft'?>"><?=$isPublished?'Published':'Draft'?></em><strong>Edit</strong></summary>
                <form method="post" class="cast-release-form"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['casting_csrf'])?>"><input type="hidden" name="publication_type" value="<?=$e($publicationType)?>"><label>Headline<input name="headline" maxlength="190" value="<?=$e((string)($release['headline']??''))?>" placeholder="<?=$publicationType==='callback'?'Callbacks for ':'Meet the cast of '?><?=$e((string)$production['title'])?>"></label><label>Member note<textarea name="member_note" rows="2" maxlength="2000" placeholder="<?=$publicationType==='callback'?'Callback instructions, timing, or a short note…':'A short note from the production team…'?>"><?=$e((string)($release['member_note']??''))?></textarea></label><div><button name="action" value="save_publication" type="submit">Save draft</button><button class="button" name="action" value="publish_publication" type="submit"><?=$publicationType==='callback'?'Publish callbacks':'Publish cast'?></button><?php if($isPublished):?><button class="cast-unpublish" name="action" value="unpublish_publication" type="submit">Unpublish</button><?php endif;?></div></form>
            </details>
            <?php endforeach;?>
        </section>
        <section class="cast-public-link">
            <div><small>PUBLIC WEBSITE</small><b>Embeddable casting announcement</b><span><?=$publicSettings['enabled']?'Live · first name + last initial':'Off · members only'?></span></div>
            <?php if($publicSettings['enabled']&&!empty($publicSettings['slug'])):?><code><?=$e('https://ctsmd.me/public/casting?slug='.(string)$publicSettings['slug'])?></code><?php endif;?>
            <form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['casting_csrf'])?>"><input type="hidden" name="action" value="toggle_public_page"><input type="hidden" name="public_enabled" value="<?=$publicSettings['enabled']?'0':'1'?>"><button type="submit"><?=$publicSettings['enabled']?'Disable public page':'Enable public page'?></button></form>
        </section>
        <section class="cast-summary"><?php foreach(['under_review'=>'Under review','callback'=>'Callback','offered'=>'Offered','cast'=>'Cast','not_cast'=>'Not cast'] as $key=>$label):?><article><b><?=(int)$counts[$key]?></b><span><?=$e($label)?></span></article><?php endforeach;?></section>
        <div class="cast-intake-grid"><section class="cast-panel"><header><small>AUDITION INTAKE</small><h3>Reviewed registrations</h3></header><?php if(!$intake):?><p>No linked audition registrations are waiting to be added.</p><?php endif;?><?php foreach($intake as $row):?><form class="cast-candidate" method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['casting_csrf'])?>"><input type="hidden" name="action" value="add_intake"><input type="hidden" name="user_id" value="<?=(int)$row['user_id']?>"><input type="hidden" name="submission_id" value="<?=(int)$row['submission_id']?>"><span><b><?=$e((string)$row['person_name'])?></b><small><?=$e((string)$row['opportunity_title'])?> · <?=ucwords(str_replace('_',' ',(string)$row['registration_status']))?></small></span><button type="submit">Add to casting</button></form><?php endforeach;?></section>
        <section class="cast-panel"><header><small>PEOPLE</small><h3>Add existing student</h3></header><?php if($people):?><form class="cast-add" method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['casting_csrf'])?>"><input type="hidden" name="action" value="add_person"><select name="user_id" required><option value="">Choose student…</option><?php foreach($people as $p):?><option value="<?=(int)$p['id']?>"><?=$e((string)$p['name'])?> · <?=$e((string)$p['role'])?></option><?php endforeach;?></select><button type="submit">Add student</button></form><?php else:?><p>Every active Student is already represented on the casting board.</p><?php endif;?><p class="cast-help">Adult/staff production participation stays in Production Roster; Casting currently represents Student casting decisions.</p></section></div>
        <section class="cast-board"><header><div><small>WORKING BOARD</small><h2>Casting decisions</h2></div><span><?=count($board)?> students</span></header><?php if(!$board):?><div class="cast-empty compact">Add a reviewed audition or existing Student to begin casting.</div><?php endif;?>
        <?php foreach($board as $r):$groupIds=array_map(static fn(array $g):int=>(int)$g['id'],$r['groups']);?><article class="cast-card status-<?=$e((string)$r['casting_status'])?>"><div class="cast-person"><span class="cast-avatar"><?=strtoupper(substr((string)$r['person_name'],0,1))?></span><div><small><?=$r['opportunity_title']?$e((string)$r['opportunity_title']):'Added from People'?></small><h3><?=$e((string)$r['person_name'])?></h3><p><?=$e((string)$r['account_role'])?><?php if($r['production_membership_id']):?> · <strong>Rostered</strong><?php endif;?><?php if($r['result_communicated_at']):?> · <strong>Result sent</strong><?php endif;?></p></div></div>
        <form class="cast-decision" method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['casting_csrf'])?>"><input type="hidden" name="action" value="update"><input type="hidden" name="record_id" value="<?=(int)$r['id']?>"><label>Decision<select name="casting_status"><?php foreach(['under_review'=>'Under review','callback'=>'Callback','offered'=>'Offered','cast'=>'Cast','not_cast'=>'Not cast','withdrawn'=>'Withdrawn'] as $value=>$label):?><option value="<?=$value?>"<?=$r['casting_status']===$value?' selected':''?>><?=$label?></option><?php endforeach;?></select></label><label>Character / role<input name="role_title" maxlength="190" value="<?=$e((string)($r['role_title']??''))?>" placeholder="Young Anna, Ensemble, Sven…"></label><label>Track / company note<input name="participation_track" maxlength="100" value="<?=$e((string)($r['participation_track']??''))?>" placeholder="Principal, Ensemble, Dance…"></label><label class="wide">Staff notes<textarea name="staff_notes" rows="2" maxlength="2000" placeholder="Private casting notes…"><?=$e((string)($r['staff_notes']??''))?></textarea></label><button type="submit">Save decision</button></form>
        <div class="cast-communication"><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['casting_csrf'])?>"><input type="hidden" name="action" value="send_result"><input type="hidden" name="record_id" value="<?=(int)$r['id']?>"><span><b>Family communication</b><small><?=in_array($r['casting_status'],['offered','cast','not_cast'],true)?'Explicitly send the current result. Changing the decision above never sends this automatically.':'Set the decision to Offered, Cast, or Not cast before sending.'?></small></span><button type="submit"<?=!in_array($r['casting_status'],['offered','cast','not_cast'],true)?' disabled':''?>><?=$r['result_communicated_at']?'Send updated result':'Send result'?></button></form></div>
        <form class="cast-finalize" method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['casting_csrf'])?>"><input type="hidden" name="action" value="finalize"><input type="hidden" name="record_id" value="<?=(int)$r['id']?>"><div><small>PRODUCTION GROUPS</small><div class="cast-groups"><?php if(!$groups):?><em>No active Production Groups yet.</em><?php endif;?><?php foreach($groups as $g):?><label><input type="checkbox" name="group_ids[]" value="<?=(int)$g['id']?>"<?=in_array((int)$g['id'],$groupIds,true)?' checked':''?>><?=$e((string)$g['name'])?></label><?php endforeach;?></div></div><div class="cast-finalize-action"><?php if($r['production_membership_id']):?><span>Roster membership linked</span><?php endif;?><button type="submit"<?=$r['casting_status']!=='cast'?' disabled':''?>><?=$r['production_membership_id']?'Update roster & groups':'Finalize to roster'?></button></div></form></article><?php endforeach;?></section>
        <?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function flash(string $type,string $message):void{$_SESSION['casting_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden():never{http_response_code(403);exit('Restricted');}
}
