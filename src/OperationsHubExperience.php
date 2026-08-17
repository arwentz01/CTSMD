<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';

final class OperationsHubExperience
{
    private const ROUTES=['/admin/operations','/admin/operations/community','/admin/operations/resources','/admin/operations/forms'];

    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();
        $db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user)self::redirect(($basePath?:'').'/login');
        [$title,$eyebrow,$description,$items]=self::definition($route,$user);
        if(!$items){http_response_code(403);exit('Restricted');}
        $url=static fn(string $p):string=>($basePath?:'').$p;
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$e($title)?> · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/operations-hub.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Operations',$title,$basePath);?><div class="opshub-page"><section class="opshub-hero"><small><?=$e($eyebrow)?></small><h2><?=$e($title)?></h2><p><?=$e($description)?></p></section><div class="opshub-grid"><?php foreach($items as $item):?><a class="opshub-card" href="<?=$url($item['href'])?>"><i><?=$e($item['icon'])?></i><div><small><?=$e($item['eyebrow'])?></small><h3><?=$e($item['label'])?></h3><p><?=$e($item['detail'])?></p><span>Open →</span></div></a><?php endforeach;?></div></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function definition(string $route,array $user):array
    {
        if($route==='/admin/operations'){
            if(!AccessPolicy::isStaff($user))return ['Operations','STAFF TOOLS','',[]];
            $items=[
                ['icon'=>'◎','eyebrow'=>'TRIAGE','label'=>'Staff Overview','detail'=>'Cross-production attention queues, upcoming calls, intake, staffing gaps and operational signals.','href'=>'/staff'],
                ['icon'=>'★','eyebrow'=>'SHOWS','label'=>'Production workspace','detail'=>'Choose the Working Production and open casting, schedule, attendance, readiness, Playbill and show operations.','href'=>'/production'],
            ];
            if(AccessPolicy::canManageAccounts($user)){
                $items[]=['icon'=>'⚿','eyebrow'=>'PEOPLE & ACCESS','label'=>'Account & Access','detail'=>'Membership approvals, accounts, roles, families, readiness and access administration.','href'=>'/admin/accounts'];
            }elseif(AccessPolicy::canManagePeople($user)){
                $items[]=['icon'=>'♟','eyebrow'=>'PEOPLE','label'=>'People & families','detail'=>'Manage people records, household relationships and participant connections.','href'=>'/people'];
            }
            if(AccessPolicy::canManageCommunity($user)||AccessPolicy::canModerateCommunity($user)){
                $items[]=['icon'=>'#','eyebrow'=>'COMMUNITY','label'=>'Community Operations','detail'=>'Channels, teams, private rooms, posting rules and moderation tools.','href'=>'/admin/operations/community'];
            }
            if(AccessPolicy::canManageResources($user)){
                $items[]=['icon'=>'▤','eyebrow'=>'LIBRARIES','label'=>'Resource Operations','detail'=>'Manage CTSMD-wide resources plus Working Production links, files and versions.','href'=>'/admin/operations/resources'];
            }
            if(AccessPolicy::canManageVolunteers($user)){
                $items[]=['icon'=>'♡','eyebrow'=>'VOLUNTEERS','label'=>'Volunteer Operations','detail'=>'Create shifts, review approvals, manage requirements, training and verification.','href'=>'/admin/volunteer-shifts'];
            }
            if(AccessPolicy::canManageForms($user)){
                $items[]=['icon'=>'✓','eyebrow'=>'FORMS & INTAKE','label'=>'Forms & Registration','detail'=>'Build forms, assign requirements, review submissions and process registration intake.','href'=>'/admin/operations/forms'];
            }
            if(AccessPolicy::canManageSafeguarding($user)){
                $items[]=['icon'=>'●','eyebrow'=>'RESTRICTED','label'=>'Safeguarding','detail'=>'Review safeguarded messaging, relationship concerns and restricted cases.','href'=>'/safeguarding'];
            }
            if(AccessPolicy::canManageAccounts($user)){
                $items[]=['icon'=>'✉','eyebrow'=>'DELIVERY','label'=>'Email Operations','detail'=>'Review queued mail, delivery outcomes and failures without crowding everyday navigation.','href'=>'/admin/email'];
            }
            return ['Operations','STAFF & ADMINISTRATION','One place for CTSMD-wide work. Your permissions determine which operational areas appear here.',$items];
        }

        if($route==='/admin/operations/community'){
            $items=[];
            if(AccessPolicy::canManageCommunity($user)){
                $items[]=['icon'=>'#','eyebrow'=>'CHANNELS','label'=>'Channel administration','detail'=>'Create channels, audiences, posting rules, and production-scoped rooms.','href'=>'/admin/channels'];
                $items[]=['icon'=>'◎','eyebrow'=>'PRIVATE SPACES','label'=>'Teams & private rooms','detail'=>'Manage reusable teams and safeguarded selected-member rooms.','href'=>'/admin/teams'];
            }
            if(AccessPolicy::canModerateCommunity($user))$items[]=['icon'=>'◇','eyebrow'=>'SAFETY','label'=>'Community moderation','detail'=>'Moderation terms, review queues, and auditable community actions.','href'=>'/admin/moderation/terms'];
            return ['Community Operations','COMMUNITY TOOLS','Manage structure and safety without crowding the global navigation.',$items];
        }
        if($route==='/admin/operations/resources'){
            if(!AccessPolicy::canManageResources($user))return ['Resource Operations','RESOURCE TOOLS','',[]];
            return ['Resource Operations','RESOURCE TOOLS','Choose the library you need. CTSMD-wide material stays separate from the current Working Production.',[
                ['icon'=>'◇','eyebrow'=>'CTSMD-WIDE','label'=>'Member resources','detail'=>'Policies, handbooks, facility information, links, notes, and files for approved members.','href'=>'/admin/member-resources'],
                ['icon'=>'▤','eyebrow'=>'WORKING PRODUCTION','label'=>'Production resources','detail'=>'Links and notes published for the selected production.','href'=>'/admin/resources'],
                ['icon'=>'▣','eyebrow'=>'WORKING PRODUCTION','label'=>'Production files','detail'=>'Uploads, immutable versions, categories, audiences, and download access.','href'=>'/admin/files'],
            ]];
        }
        if(!AccessPolicy::canManageForms($user))return ['Forms & Registration','FORM TOOLS','',[]];
        return ['Forms & Registration','FORM TOOLS','Build reusable forms, assign them in context, review submissions, and move registrations into production membership.',[
            ['icon'=>'✓','eyebrow'=>'REVIEW','label'=>'Submission review','detail'=>'Review forms that require staff approval and complete the workflow.','href'=>'/admin/forms'],
            ['icon'=>'▤','eyebrow'=>'LIBRARY','label'=>'Forms management','detail'=>'Create form definitions, set scope, assign audiences, and monitor completion.','href'=>'/admin/forms/manage'],
            ['icon'=>'＋','eyebrow'=>'BUILDER','label'=>'Form builder','detail'=>'Add structured questions and fields to existing form definitions.','href'=>'/admin/forms/build'],
            ['icon'=>'↗','eyebrow'=>'INTAKE','label'=>'Registration operations','detail'=>'Review registration intake and link participants into CTSMD accounts and productions.','href'=>'/admin/registrations'],
        ]];
    }

    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
