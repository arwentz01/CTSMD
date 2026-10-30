<?php
declare(strict_types=1);
require_once __DIR__.'/MemberUI.php';
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/WorkspaceUI.php';
require_once __DIR__.'/CalendarService.php';

/** Read-only logistics view. Not a general guardian delegation. */
final class CaregiverLogisticsExperience {
    public static function handles(string $route):bool{return $route==='/family/caregiver-schedule';}
    public static function render(string $route,string $basePath):never {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$viewer=Auth::currentUser($db);
        if(!$viewer){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $stmt=$db->prepare("SELECT u.id,u.first_name,u.last_name FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 AND u.account_status<>'disabled' WHERE fr.guardian_user_id=:adult AND fr.status='active' AND fr.relationship_type='caregiver' ORDER BY u.last_name,u.first_name");
        $stmt->execute(['adult'=>(int)$viewer['id']]);$children=$stmt->fetchAll();
        if(!$children){http_response_code(403);exit('No active caregiver schedule access.');}
        $now=new DateTimeImmutable('now');$end=$now->modify('+45 days');$events=[];
        foreach($children as $child){
            $studentId=(int)$child['id'];
            $q=$db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,first_name,last_name,initials,display_role role,active FROM users WHERE id=:id");
            $q->execute(['id'=>$studentId]);$student=$q->fetch();
            if(!$student)continue;
            $student['roles']=Auth::roles($db,$studentId);
            $student['permissions']=Auth::permissions($db,$studentId);
            foreach(CalendarService::visibleEvents($db,$student,$now,$end) as $event){
                if(($event['status']??'active')==='cancelled')continue;
                // Deliberately do not expose notes, participants, forms, messaging,
                // registration or casting information to caregiver relationships.
                $events[]=['child'=>$child['first_name'],'title'=>$event['title'],'production'=>$event['production_title'],'start'=>$event['starts_at'],'end'=>$event['ends_at'],'location'=>$event['location']];
            }
        }
        usort($events,static fn(array $a,array $b):int=>strcmp((string)$a['start'],(string)$b['start']));
        $e=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');$u=static fn(string $path):string=>($basePath?:'').$path;
        header('Content-Type: text/html; charset=utf-8');?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Caregiver schedule · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><style>.caregiver-page{max-width:850px;margin:0 auto;padding:24px}.caregiver-page h1{font:700 30px Georgia,serif}.caregiver-page .call{padding:16px;margin:12px 0;background:#fff;border:1px solid #e6dbe0;border-radius:12px}.caregiver-page .call strong{display:block}.caregiver-page .call small{color:#665e63}.caregiver-page .note{color:#6b6268;font-size:13px}</style><?php WorkspaceUI::operationsStyles($basePath); ?><?php MemberUI::styles($basePath); ?></head><body class="app-body ux-operations ux-workspace ux-operational ux-family-settings<?=MemberUI::bodyClass()?>"><a class="ux-skip" href="#ux-main">Skip to main content</a><div class="unified-shell"><?php AppNavigation::renderSidebar('/family-hub',$basePath,$viewer);?><main class="unified-main" id="ux-main" tabindex="-1"><?php AppNavigation::renderHeader('Family','Caregiver schedule',$basePath,[]); MemberUI::wayfinding($basePath);?><div class="caregiver-page ops-page"><h1>Upcoming calls</h1><p class="note">Read-only schedule and location information for children connected to your caregiver account. Contact a parent or CTSMD staff about changes or permissions.</p><?php if(!$events):?><p>No upcoming calls in the next 45 days.</p><?php endif;?><?php foreach($events as $item):?><article class="call"><small><?=$e($item['child'])?> · <?=$e($item['production'])?></small><strong><?=$e($item['title'])?></strong><p><?=$e(date('D, M j · g:i A',strtotime((string)$item['start'])))?><?php if($item['end']):?> – <?=$e(date('g:i A',strtotime((string)$item['end'])))?><?php endif;?></p><?php if($item['location']):?><small><?=$e($item['location'])?></small><?php endif;?></article><?php endforeach;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
}
