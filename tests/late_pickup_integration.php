<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/LatePickupService.php';
date_default_timezone_set('America/New_York');

$dsn=(string)getenv('LATE_PICKUP_TEST_DSN');
if($dsn===''||!preg_match('/dbname=ctsmd_late_pickup_test(?:;|$)/',$dsn)){
    throw new RuntimeException('Set LATE_PICKUP_TEST_DSN to the disposable ctsmd_late_pickup_test database.');
}
$db=new PDO($dsn,(string)getenv('LATE_PICKUP_TEST_USER'),(string)getenv('LATE_PICKUP_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);

$checks=0;
$assert=static function(bool $condition,string $message)use(&$checks):void{
    if(!$condition)throw new RuntimeException('FAIL: '.$message);
    $checks++;
};
$transaction=static function(callable $callback)use($db){
    $db->beginTransaction();
    try{$result=$callback();$db->commit();return$result;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
};
$expectFailure=static function(callable $callback,string $contains)use($assert):void{
    try{$callback();}catch(RuntimeException $e){$assert(str_contains($e->getMessage(),$contains),'expected error containing '.$contains);return;}
    throw new RuntimeException('FAIL: expected RuntimeException containing '.$contains);
};
$scalar=static function(string $sql,array $params=[])use($db){$s=$db->prepare($sql);$s->execute($params);return$s->fetchColumn();};

$tag='late-pickup-'.bin2hex(random_bytes(5));
$insertUser=$db->prepare("INSERT INTO users (first_name,last_name,email,account_status,organization_membership_status,initials,display_role,active) VALUES (:first,:last,:email,'active','approved',:initials,:role,1)");
$user=static function(string $first,string $role)use($insertUser,$db,$tag):int{
    $insertUser->execute(['first'=>$first,'last'=>'Test','email'=>strtolower($first).'.'.$tag.'@example.test','initials'=>strtoupper(substr($first,0,1).'T'),'role'=>$role]);
    return(int)$db->lastInsertId();
};
$actor=$user('Staff','Production staff');
$student=$user('Student','Student');
$guardianA=$user('GuardianA','Guardian');
$guardianB=$user('GuardianB','Guardian');
$mutedGuardian=$user('MutedGuardian','Guardian');
$formerGuardian=$user('FormerGuardian','Guardian');
$otherStudent=$user('OtherStudent','Student');
$studentRoleId=(int)$scalar("SELECT id FROM auth_roles WHERE code='student' AND active=1 LIMIT 1");
$assert($studentRoleId>0,'student authorization role exists');
$assignStudentRole=$db->prepare('INSERT INTO auth_user_roles (user_id,role_id,assigned_by_user_id) VALUES (:user,:role,:actor)');
foreach([$student,$otherStudent] as $studentUserId)$assignStudentRole->execute(['user'=>$studentUserId,'role'=>$studentRoleId,'actor'=>$actor]);

$insertProduction=$db->prepare("INSERT INTO productions (title,season,status,is_active,lifecycle_stage) VALUES (:title,'Test','current',1,:stage)");
$production=static function(string $title,string $stage='in_production')use($insertProduction,$db,$tag):int{$insertProduction->execute(['title'=>$title.' '.$tag,'stage'=>$stage]);return(int)$db->lastInsertId();};
$productionA=$production('Late Pickup A');
$productionB=$production('Late Pickup B');
$closedProduction=$production('Closed','completed');

$insertMembership=$db->prepare("INSERT INTO production_memberships (production_id,user_id,audience_type,participation_role,status) VALUES (:production,:user,:type,:role,'active')");
$membership=static function(int $productionId,int $userId,string $type)use($insertMembership,$db):int{$insertMembership->execute(['production'=>$productionId,'user'=>$userId,'type'=>$type,'role'=>$type]);return(int)$db->lastInsertId();};
$studentMembership=$membership($productionA,$student,'student');
$otherStudentMembership=$membership($productionA,$otherStudent,'student');
foreach([$guardianA,$guardianB,$mutedGuardian,$formerGuardian] as $guardian)$membership($productionA,$guardian,'guardian');
$membership($productionA,$actor,'staff');
$membership($productionB,$student,'student');
$membership($productionB,$actor,'staff');
$membership($closedProduction,$student,'student');
$membership($closedProduction,$actor,'staff');

$insertRelationship=$db->prepare("INSERT INTO family_relationships (guardian_user_id,student_user_id,relationship_type,is_primary,status,created_by_user_id) VALUES (:guardian,:student,'guardian',:primary,:status,:actor)");
$relationship=static function(int $guardian,string $status,int $primary=0)use($insertRelationship,$student,$actor):void{$insertRelationship->execute(['guardian'=>$guardian,'student'=>$student,'primary'=>$primary,'status'=>$status,'actor'=>$actor]);};
$relationship($guardianA,'active',1);$relationship($guardianB,'active');$relationship($mutedGuardian,'active');$relationship($formerGuardian,'inactive');
$db->prepare('INSERT INTO notification_preferences (user_id,email_enabled) VALUES (?,0)')->execute([$mutedGuardian]);

$insertEvent=$db->prepare("INSERT INTO schedule_items (production_id,title,starts_at,ends_at,location,visibility,audience_mode,item_type,status) VALUES (:production,:title,:starts,:ends,'Test stage','family',:mode,'rehearsal',:status)");
$event=static function(int $productionId,string $title,?string $ends='2026-10-25 18:00:00',string $status='active',string $mode='production')use($insertEvent,$db,$tag):int{
    $insertEvent->execute(['production'=>$productionId,'title'=>$title.' '.$tag,'starts'=>'2026-10-25 16:00:00','ends'=>$ends,'mode'=>$mode,'status'=>$status]);return(int)$db->lastInsertId();
};
$event1=$event($productionA,'Occurrence 1');
$event2=$event($productionA,'Inside grace');
$event3=$event($productionA,'Occurrence 2');
$event4=$event($productionA,'Occurrence 3');
$eventB=$event($productionB,'Other production');
$cancelled=$event($productionA,'Cancelled','2026-10-25 18:00:00','cancelled');
$noEnd=$event($productionA,'No end',null);
$closedEvent=$event($closedProduction,'Closed event');
$groupEvent=$event($productionA,'Other group','2026-10-25 18:00:00','active','groups');
$db->prepare("INSERT INTO production_groups (production_id,name,group_type,created_by_user_id) VALUES (:production,:name,'cast',:actor)")->execute(['production'=>$productionA,'name'=>'Other group '.$tag,'actor'=>$actor]);
$groupId=(int)$db->lastInsertId();
$db->prepare('INSERT INTO production_group_members (group_id,production_membership_id,added_by_user_id) VALUES (:group_id,:membership,:actor)')->execute(['group_id'=>$groupId,'membership'=>$otherStudentMembership,'actor'=>$actor]);
$db->prepare('INSERT INTO schedule_item_groups (schedule_item_id,group_id) VALUES (:item,:group_id)')->execute(['item'=>$groupEvent,'group_id'=>$groupId]);

$transaction(fn()=>LatePickupService::saveSettings($db,$productionA,$actor,2,10,null));
$transaction(fn()=>LatePickupService::record($db,$event1,$student,$actor,'2026-10-25 18:15:00'));
$assert((int)$scalar('SELECT COUNT(*) FROM late_pickup_occurrences WHERE schedule_item_id=? AND student_user_id=?',[$event1,$student])===1,'first occurrence is stored once');
$assert((int)$scalar('SELECT late_minutes FROM late_pickup_occurrences WHERE schedule_item_id=? AND student_user_id=?',[$event1,$student])===15,'expected release determines lateness');
$transaction(fn()=>LatePickupService::record($db,$event1,$student,$actor,'2026-10-25 18:15:00'));
$assert((int)$scalar('SELECT COUNT(*) FROM late_pickup_occurrence_revisions r JOIN late_pickup_occurrences o ON o.id=r.occurrence_id WHERE o.schedule_item_id=? AND o.student_user_id=?',[$event1,$student])===1,'identical retry is idempotent');
$transaction(fn()=>LatePickupService::record($db,$event1,$student,$actor,'2026-10-25 18:20:00','Corrected from sign-out sheet'));
$assert((int)$scalar('SELECT late_minutes FROM late_pickup_occurrences WHERE schedule_item_id=? AND student_user_id=?',[$event1,$student])===20,'correction updates the occurrence');
$assert((int)$scalar("SELECT COUNT(*) FROM late_pickup_occurrence_revisions r JOIN late_pickup_occurrences o ON o.id=r.occurrence_id WHERE o.schedule_item_id=? AND r.action='corrected'",[$event1])===1,'correction is audited');

$transaction(fn()=>LatePickupService::record($db,$event2,$student,$actor,'2026-10-25 18:05:00'));
$assert((int)$scalar('SELECT COUNT(*) FROM late_pickup_occurrences WHERE schedule_item_id=?',[$event2])===1,'after-release pickup inside grace is retained');
$transaction(fn()=>LatePickupService::record($db,$event3,$student,$actor,'2026-10-25 18:30:00'));
$reviewId=(int)$scalar('SELECT id FROM late_pickup_reviews WHERE production_id=? AND student_user_id=?',[$productionA,$student]);
$noticeId=(int)$scalar('SELECT id FROM late_pickup_notice_drafts WHERE review_id=? ORDER BY id DESC LIMIT 1',[$reviewId]);
$assert($reviewId>0&&$noticeId>0,'threshold crossing creates review and draft');
$assert($scalar('SELECT status FROM late_pickup_notice_drafts WHERE id=?',[$noticeId])==='draft','threshold draft is not approved or sent');
$assert((int)$scalar("SELECT COUNT(*) FROM email_queue WHERE dedupe_key LIKE ?",['late-pickup-notice-'.$noticeId.'-%'])===0,'threshold crossing does not queue email');

$transaction(fn()=>LatePickupService::record($db,$eventB,$student,$actor,'2026-10-25 18:40:00'));
$assert((int)$scalar('SELECT occurrence_count FROM late_pickup_reviews WHERE id=?',[$reviewId])===2,'other-production occurrence does not affect review');
$transaction(fn()=>LatePickupService::record($db,$event3,$student,$actor,null,'Pickup entry belonged to another student'));
$assert($scalar('SELECT status FROM late_pickup_reviews WHERE id=?',[$reviewId])==='below_threshold','reversal moves review below threshold');
$assert($scalar('SELECT status FROM late_pickup_notice_drafts WHERE id=?',[$noticeId])==='superseded','reversal supersedes unsent notice');
$transaction(fn()=>LatePickupService::record($db,$event3,$student,$actor,'2026-10-25 18:25:00','Restored after signed record review'));
$newNoticeId=(int)$scalar('SELECT id FROM late_pickup_notice_drafts WHERE review_id=? ORDER BY id DESC LIMIT 1',[$reviewId]);
$assert($newNoticeId!==$noticeId&&$scalar('SELECT status FROM late_pickup_reviews WHERE id=?',[$reviewId])==='open','restoration reopens review with a new draft');
$assert(!str_contains((string)$scalar('SELECT body FROM late_pickup_notice_drafts WHERE id=?',[$newNoticeId]),'6:05 PM'),'draft omits pickups inside the grace period');
$assert((int)$scalar("SELECT COUNT(*) FROM late_pickup_occurrence_revisions r JOIN late_pickup_occurrences o ON o.id=r.occurrence_id WHERE o.schedule_item_id=? AND r.action IN ('voided','restored')",[$event3])===2,'reversal and restoration are audited');

$expectFailure(fn()=>$transaction(fn()=>LatePickupService::send($db,$productionA,$newNoticeId,$actor)),'required state');
$transaction(fn()=>LatePickupService::saveDraft($db,$productionA,$newNoticeId,$actor,'Updated pickup review','Approved test-only notice body.',true));
$expectFailure(fn()=>$transaction(fn()=>LatePickupService::send($db,$productionA,$newNoticeId,$actor)),'required state');
$transaction(fn()=>LatePickupService::approve($db,$productionA,$newNoticeId,$actor));
$queued=$transaction(fn()=>LatePickupService::send($db,$productionA,$newNoticeId,$actor));
$assert($queued===2,'only notification-eligible current guardians are queued');
$assert((int)$scalar('SELECT COUNT(*) FROM late_pickup_notice_recipients WHERE notice_id=?',[$newNoticeId])===3,'current guardians are snapshotted and former guardian is excluded');
$assert((int)$scalar("SELECT COUNT(*) FROM late_pickup_notice_recipients WHERE notice_id=? AND queue_status='suppressed'",[$newNoticeId])===1,'notification preference suppression is recorded');
$assert((int)$scalar("SELECT COUNT(*) FROM late_pickup_notice_recipients WHERE notice_id=? AND JSON_UNQUOTE(JSON_EXTRACT(content_snapshot_json,'$.body'))='Approved test-only notice body.'",[$newNoticeId])===3,'recipient content is snapshotted');
$assert($transaction(fn()=>LatePickupService::send($db,$productionA,$newNoticeId,$actor))===0,'repeat send is idempotent');
$assert((int)$scalar("SELECT COUNT(*) FROM email_queue WHERE dedupe_key LIKE ?",['late-pickup-notice-'.$newNoticeId.'-%'])===2,'repeat send creates no duplicate queue records');
$assert(count(LatePickupService::familyNotices($db,$guardianA))===1,'current guardian sees approved family notice');
$assert(count(LatePickupService::familyNotices($db,$formerGuardian))===0,'former guardian cannot see family notice');

$queueA=(int)$scalar('SELECT email_queue_id FROM late_pickup_notice_recipients WHERE notice_id=? AND guardian_user_id=?',[$newNoticeId,$guardianA]);
$db->prepare("UPDATE family_relationships SET status='inactive' WHERE guardian_user_id=? AND student_user_id=?")->execute([$guardianA,$student]);
putenv('MAIL_DRIVER=log');
$result=MailService::process($db,dirname(__DIR__),1,$queueA);
$assert($result['suppressed']===1&&$scalar('SELECT status FROM email_queue WHERE id=?',[$queueA])==='suppressed','worker suppresses a queued notice after guardian revocation');
$assert(count(LatePickupService::familyNotices($db,$guardianA))===0,'revoked guardian loses mobile Action Center notice access');

$expectFailure(fn()=>$transaction(fn()=>LatePickupService::record($db,$cancelled,$student,$actor,'2026-10-25 18:20:00')),'cancelled event');
$expectFailure(fn()=>$transaction(fn()=>LatePickupService::record($db,$noEnd,$student,$actor,'2026-10-25 18:20:00')),'end time');
$expectFailure(fn()=>$transaction(fn()=>LatePickupService::record($db,$closedEvent,$student,$actor,'2026-10-25 18:20:00')),'completed or archived');
$expectFailure(fn()=>$transaction(fn()=>LatePickupService::record($db,$groupEvent,$student,$actor,'2026-10-25 18:20:00')),'not expected');
$db->prepare("UPDATE users SET account_status='disabled' WHERE id=?")->execute([$student]);
$expectFailure(fn()=>$transaction(fn()=>LatePickupService::record($db,$event4,$student,$actor,'2026-10-25 18:20:00')),'not an active Student');
$db->prepare("UPDATE users SET account_status='active' WHERE id=?")->execute([$student]);

$transaction(fn()=>LatePickupService::saveSettings($db,$productionA,$actor,2,25,null));
$assert($scalar('SELECT status FROM late_pickup_reviews WHERE id=?',[$reviewId])==='below_threshold','grace change reclassifies occurrence count');
$assert((int)$scalar('SELECT occurrence_count FROM late_pickup_reviews WHERE id=?',[$reviewId])===0,'reclassified count is persisted');

echo 'Late pickup MySQL integration checks passed: '.$checks."\n";
