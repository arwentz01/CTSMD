<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/ScheduleAudience.php';
require_once __DIR__ . '/AttendanceService.php';

final class AttendanceExperience
{
    private const ROUTES=['/attendance','/attendance/take','/attendance/report'];

    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        if(session_status()!==PHP_SESSION_ACTIVE)session_start();
        $db=Database::connect(dirname(__DIR__));
        $user=self::currentUser($db);
        $_SESSION['attendance_csrf']??=bin2hex(random_bytes(24));
        $production=ProductionContext::selected($db,$user);
        if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$user,$production,$route,$basePath);
        self::page($db,$route,$basePath,$user,$production);
    }

    private static function handlePost(PDO $db,array $user,?array $production,string $route,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['attendance_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session token expired. Please try again.');self::redirect($basePath.'/attendance');}
        if(!$production){self::flash('error','Select an active production first.');self::redirect($basePath.'/production');}
        $action=(string)($_POST['action']??'');
        try{
            if($action==='save_roster'){
                if(!AccessPolicy::canManageProduction($user))throw new RuntimeException('Only production staff can take attendance.');
                $itemId=filter_input(INPUT_POST,'schedule_item_id',FILTER_VALIDATE_INT)?:0;
                $item=AttendanceService::scheduleItem($db,(int)$itemId,(int)$production['id']);if(!$item)throw new RuntimeException('That schedule item is not in the working production.');
                $db->beginTransaction();
                AttendanceService::saveRoster($db,(int)$itemId,(int)$user['id'],(array)($_POST['status']??[]),(array)($_POST['note']??[]));
                self::audit($db,(int)$user['id'],'attendance.roster_saved','schedule_item',(int)$itemId,'Saved attendance roster.',['production_id'=>(int)$production['id']]);
                $db->commit();self::flash('success','Attendance saved.');self::redirect($basePath.'/attendance/take?id='.(int)$itemId);
            }
            if($action==='acknowledge_report'){
                if(!AccessPolicy::canManageProduction($user))throw new RuntimeException('Only production staff can review absence reports.');
                $reportId=filter_input(INPUT_POST,'report_id',FILTER_VALIDATE_INT)?:0;
                $check=$db->prepare("SELECT aar.id,aar.schedule_item_id FROM attendance_absence_reports aar JOIN schedule_items si ON si.id=aar.schedule_item_id WHERE aar.id=:id AND si.production_id=:production LIMIT 1");$check->execute(['id'=>$reportId,'production'=>(int)$production['id']]);$report=$check->fetch();if(!$report)throw new RuntimeException('That absence report is not in the working production.');
                $db->beginTransaction();AttendanceService::acknowledgeReport($db,(int)$reportId,(int)$user['id']);self::audit($db,(int)$user['id'],'attendance.absence_acknowledged','attendance_absence_report',(int)$reportId,'Acknowledged absence report and marked student excused.',['schedule_item_id'=>(int)$report['schedule_item_id'],'production_id'=>(int)$production['id']]);$db->commit();self::flash('success','Absence acknowledged and marked excused.');self::redirect($basePath.'/attendance/take?id='.(int)$report['schedule_item_id']);
            }
            if($action==='report_absence'){
                $itemId=filter_input(INPUT_POST,'schedule_item_id',FILTER_VALIDATE_INT)?:0;$studentId=filter_input(INPUT_POST,'student_user_id',FILTER_VALIDATE_INT)?:0;
                $item=AttendanceService::scheduleItem($db,(int)$itemId,(int)$production['id']);if(!$item)throw new RuntimeException('That schedule item is not available in this production.');
                $eligible=AttendanceService::reportableStudents($db,$user,(int)$itemId);$eligibleIds=array_map(static fn(array $m):int=>(int)$m['id'],$eligible);if(!in_array((int)$studentId,$eligibleIds,true))throw new RuntimeException('You cannot submit an absence report for that student or schedule item.');
                $db->beginTransaction();$reportId=AttendanceService::submitAbsenceReport($db,(int)$itemId,(int)$studentId,(int)$user['id'],(string)($_POST['reason']??''));self::audit($db,(int)$user['id'],'attendance.absence_reported','attendance_absence_report',$reportId,'Submitted an attendance absence report.',['schedule_item_id'=>(int)$itemId,'student_user_id'=>(int)$studentId,'production_id'=>(int)$production['id']]);$db->commit();self::flash('success','Absence report sent to production staff.');self::redirect($basePath.'/attendance/report?id='.(int)$itemId);
            }
            throw new RuntimeException('That attendance action is not available.');
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();self::flash('error',$e instanceof RuntimeException?$e->getMessage():'The attendance update could not be saved.');$fallback=$route==='/attendance/take'?'/attendance/take?id='.(int)($_POST['schedule_item_id']??0):($route==='/attendance/report'?'/attendance/report?id='.(int)($_POST['schedule_item_id']??0):'/attendance');self::redirect($basePath.$fallback);}
    }

    private static function page(PDO $db,string $route,string $basePath,array $user,?array $production):never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;$esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$staff=AccessPolicy::canManageProduction($user);$flash=$_SESSION['attendance_flash']??$_SESSION['production_context_flash']??null;unset($_SESSION['attendance_flash'],$_SESSION['production_context_flash']);
        $items=[];$selected=null;$roster=[];$reportable=[];$myReports=[];
        if($production){
            $stmt=$db->prepare("SELECT si.id,si.production_id,si.title,si.starts_at,si.ends_at,si.location,si.visibility,si.item_type,si.audience_mode FROM schedule_items si WHERE si.production_id=:production ORDER BY si.starts_at DESC,si.id DESC");$stmt->execute(['production'=>(int)$production['id']]);
            foreach($stmt->fetchAll() as $item){if($staff||ScheduleAudience::userCanViewItem($db,$user,$item)){$item['group_names']=$item['audience_mode']==='groups'?ScheduleAudience::groupNamesForItem($db,(int)$item['id']):[];$item['roster']=AttendanceService::roster($db,(int)$item['id']);$item['counts']=AttendanceService::statusCounts($item['roster']);$items[]=$item;}}
            if(in_array($route,['/attendance/take','/attendance/report'],true)){$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$selected=AttendanceService::scheduleItem($db,(int)$id,(int)$production['id']);if($selected&&($staff||ScheduleAudience::userCanViewItem($db,$user,$selected))){$selected['group_names']=$selected['audience_mode']==='groups'?ScheduleAudience::groupNamesForItem($db,(int)$selected['id']):[];$roster=AttendanceService::roster($db,(int)$selected['id']);$reportable=AttendanceService::reportableStudents($db,$user,(int)$selected['id']);$reports=$db->prepare("SELECT aar.id,aar.student_user_id,aar.reason,aar.status,aar.submitted_at,CONCAT(student.first_name,' ',student.last_name) student_name FROM attendance_absence_reports aar JOIN users student ON student.id=aar.student_user_id WHERE aar.schedule_item_id=:item AND aar.reported_by_user_id=:reporter ORDER BY aar.submitted_at DESC,aar.id DESC");$reports->execute(['item'=>(int)$selected['id'],'reporter'=>(int)$user['id']]);$myReports=$reports->fetchAll();}else{$selected=null;}}
        }
        $title=$route==='/attendance'?'Attendance':($route==='/attendance/take'?'Take attendance':'Report an absence');$subnav=[['label'=>'Overview','href'=>'/production','active'=>false],['label'=>'Schedule','href'=>'/schedule','active'=>false],['label'=>'Groups','href'=>'/production/groups','active'=>false],['label'=>'Attendance','href'=>'/attendance','active'=>true],['label'=>'Resources','href'=>'/resources','active'=>false]];
        header('Content-Type: text/html; charset=utf-8');?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/attendance.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$title,$basePath,$subnav);?><div class="att-page">
<?php if($flash):?><div class="att-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?>
<?php if(!$production):?><section class="att-empty"><h2>No active production selected</h2><p>Choose a working production before opening attendance.</p><a class="button" href="<?= $url('/production') ?>">Choose production</a></section>
<?php elseif($route==='/attendance'):?>
<section class="att-hero"><div><small><?= $esc(strtoupper($production['title'])) ?> · ATTENDANCE</small><h2><?= $staff?'Know who is in the room.':'Your production attendance.' ?></h2><p><?= $staff?'Expected attendees come directly from the schedule and Production Groups—no second roster to maintain.':'See relevant calls and report an absence when needed.' ?></p></div><span><?= count($items) ?> schedule items</span></section>
<div class="att-list"><?php if(!$items):?><div class="att-empty"><b>No schedule items available</b></div><?php endif;?><?php foreach($items as $item):$target=$item['audience_mode']==='groups'&&$item['group_names']?implode(' + ',$item['group_names']):'Whole production';$c=$item['counts'];?><article class="att-item"><div class="att-date"><b><?= $esc(date('M',strtotime($item['starts_at']))) ?></b><span><?= $esc(date('j',strtotime($item['starts_at']))) ?></span></div><div class="att-copy"><small><?= $esc(strtoupper($item['item_type'])) ?> · <?= $esc(strtoupper($target)) ?></small><h3><?= $esc($item['title']) ?></h3><p><?= $esc(date('g:i A',strtotime($item['starts_at']))) ?> · <?= $esc($item['location']) ?></p><div class="att-chips"><span><?= count($item['roster']) ?> expected</span><?php if($staff):?><span><?= (int)$c['present'] ?> present</span><span><?= (int)($c['absent']+$c['excused']) ?> absent/excused</span><span><?= (int)$c['unmarked'] ?> unmarked</span><?php endif;?></div></div><div class="att-actions"><?php if($staff):?><a class="button" href="<?= $url('/attendance/take?id='.(int)$item['id']) ?>">Take attendance</a><?php else:?><?php $eligible=AttendanceService::reportableStudents($db,$user,(int)$item['id']);if($eligible):?><a href="<?= $url('/attendance/report?id='.(int)$item['id']) ?>">Report absence</a><?php endif;?><?php endif;?></div></article><?php endforeach;?></div>
<?php elseif($route==='/attendance/take'):?>
<?php if(!$staff):?><section class="att-empty"><h2>Staff only</h2><p>Your account cannot take production attendance.</p><a class="button" href="<?= $url('/attendance') ?>">Back to attendance</a></section><?php elseif(!$selected):?><section class="att-empty"><h2>Schedule item unavailable</h2><a class="button" href="<?= $url('/attendance') ?>">Back to attendance</a></section><?php else:$target=$selected['audience_mode']==='groups'&&$selected['group_names']?implode(' + ',$selected['group_names']):'Whole production';$counts=AttendanceService::statusCounts($roster);?>
<section class="att-detail-head"><div><small><?= $esc(strtoupper($target)) ?> · <?= count($roster) ?> EXPECTED</small><h2><?= $esc($selected['title']) ?></h2><p><?= $esc(date('l, M j · g:i A',strtotime($selected['starts_at']))) ?> · <?= $esc($selected['location']) ?></p></div><a href="<?= $url('/attendance') ?>">← Attendance</a></section>
<div class="att-summary"><span><b><?= (int)$counts['present'] ?></b><small>Present</small></span><span><b><?= (int)$counts['late'] ?></b><small>Late</small></span><span><b><?= (int)$counts['excused'] ?></b><small>Excused</small></span><span><b><?= (int)$counts['unmarked'] ?></b><small>Unmarked</small></span></div>
<form method="post" class="att-roster"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['attendance_csrf']) ?>"><input type="hidden" name="action" value="save_roster"><input type="hidden" name="schedule_item_id" value="<?= (int)$selected['id'] ?>">
<?php if(!$roster):?><div class="att-empty"><b>No expected attendees resolve from this schedule audience.</b><p>Check the schedule audience and Production Groups.</p></div><?php endif;?>
<?php foreach($roster as $member):?><article class="att-person"><div class="att-person-id"><i><?= $esc(strtoupper(substr($member['name'],0,1))) ?></i><span><b><?= $esc($member['name']) ?></b><small><?= $esc(ucfirst((string)$member['audience_type'])) ?></small></span></div><?php if($member['absence_report']&&$member['absence_report']['status']==='submitted'):?><div class="att-report"><b>Absence reported</b><span><?= $esc($member['absence_report']['reason']) ?></span><small>By <?= $esc($member['absence_report']['reporter']) ?> · <?= $esc(date('M j · g:i A',strtotime($member['absence_report']['submitted_at']))) ?></small><button name="action" value="acknowledge_report" form="ack-<?= (int)$member['absence_report']['id'] ?>" type="submit">Acknowledge + excuse</button></div><?php endif;?><label>Status<select name="status[<?= (int)$member['id'] ?>]"><?php foreach(['unmarked'=>'Unmarked','present'=>'Present','absent'=>'Absent','late'=>'Late','excused'=>'Excused','left_early'=>'Left early'] as $value=>$label):?><option value="<?= $value ?>"<?= $member['attendance_status']===$value?' selected':'' ?>><?= $label ?></option><?php endforeach;?></select></label><label>Note<input name="note[<?= (int)$member['id'] ?>]" maxlength="1000" value="<?= $esc((string)($member['staff_note']??'')) ?>" placeholder="Optional"></label></article><?php endforeach;?><footer><button class="button" type="submit">Save attendance</button></footer></form>
<?php foreach($roster as $member):if($member['absence_report']&&$member['absence_report']['status']==='submitted'):?><form id="ack-<?= (int)$member['absence_report']['id'] ?>" method="post" class="att-hidden-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['attendance_csrf']) ?>"><input type="hidden" name="action" value="acknowledge_report"><input type="hidden" name="report_id" value="<?= (int)$member['absence_report']['id'] ?>"><input type="hidden" name="schedule_item_id" value="<?= (int)$selected['id'] ?>"></form><?php endif;endforeach;?>
<?php endif;?>
<?php else:?>
<?php if(!$selected):?><section class="att-empty"><h2>Schedule item unavailable</h2><a class="button" href="<?= $url('/attendance') ?>">Back to attendance</a></section><?php elseif(!$reportable):?><section class="att-empty"><h2>No student to report for this call</h2><p>This account is not eligible to submit an absence for an expected student on this schedule item.</p><a class="button" href="<?= $url('/attendance') ?>">Back to attendance</a></section><?php else:?>
<section class="att-detail-head"><div><small>ABSENCE REPORT</small><h2><?= $esc($selected['title']) ?></h2><p><?= $esc(date('l, M j · g:i A',strtotime($selected['starts_at']))) ?> · <?= $esc($selected['location']) ?></p></div><a href="<?= $url('/attendance') ?>">← Attendance</a></section><div class="att-report-layout"><form method="post" class="att-report-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['attendance_csrf']) ?>"><input type="hidden" name="action" value="report_absence"><input type="hidden" name="schedule_item_id" value="<?= (int)$selected['id'] ?>"><label>Student<select name="student_user_id" required><?php foreach($reportable as $student):?><option value="<?= (int)$student['id'] ?>"><?= $esc($student['name']) ?></option><?php endforeach;?></select></label><label>Reason<textarea name="reason" rows="6" maxlength="1500" required placeholder="Briefly let production staff know why attendance will be affected."></textarea></label><p>This does not automatically change attendance. Production staff will review the report and acknowledge it.</p><button class="button" type="submit">Send absence report</button></form><aside class="att-report-history"><small>YOUR REPORTS</small><?php if(!$myReports):?><p>No reports submitted for this call.</p><?php endif;?><?php foreach($myReports as $report):?><article><b><?= $esc($report['student_name']) ?></b><span><?= $esc(ucfirst($report['status'])) ?></span><p><?= $esc($report['reason']) ?></p><small><?= $esc(date('M j · g:i A',strtotime($report['submitted_at']))) ?></small></article><?php endforeach;?></aside></div>
<?php endif;?><?php endif;?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function currentUser(PDO $db):array{$row=$db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role,initials FROM users WHERE is_demo_current_user=1 AND active=1 LIMIT 1")->fetch();if(!$row)throw new RuntimeException('Demo user is missing. Re-import the local seed data.');return $row;}
    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $meta):void{$stmt=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:meta)');$stmt->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
    private static function flash(string $type,string $message):void{$_SESSION['attendance_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
