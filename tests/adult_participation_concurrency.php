<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/CastingService.php';
require_once dirname(__DIR__).'/src/CastingOfferService.php';
require_once dirname(__DIR__).'/src/AccountManagementExperience.php';
require_once dirname(__DIR__).'/src/CommunicationExperience.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE')))throw new RuntimeException('Explicit disposable loopback schema required.');
$db=Database::connect(dirname(__DIR__));
if(($argv[1]??'')==='worker'){
    echo "READY\n";flush();
    try{
        if($argv[2]==='accept')CastingOfferService::respond($db,(int)$argv[3],(int)$argv[4],'accepted','');
        elseif($argv[2]==='cast-add')CastingService::add($db,(int)$argv[4],(int)$argv[3],(int)$argv[5]);
        elseif($argv[2]==='remove-role')(new ReflectionMethod(AccountManagementExperience::class,'saveRoles'))->invoke(null,$db,['id'=>(int)$argv[3]],(int)$argv[4],[]);
        elseif($argv[2]==='send-message')(new ReflectionMethod(CommunicationExperience::class,'sendMessage'))->invoke(null,$db,(int)$argv[3],(int)$argv[4],'Concurrent message',[]);
        else ProductionParticipantEligibility::exception($db,(int)$argv[4],(int)$argv[5],(int)$argv[3],'Concurrent reviewed decision',$argv[2]==='revoke');
        echo "ACCEPTED\n";
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();echo $argv[2]==='cast-add'&&str_contains($e->getMessage(),'permission')?"REJECTED_PERMISSION\n":"REJECTED\n";}
    exit;
}
$checks=0;$people=[];$productions=[];$children=[];
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";};
$person=static function(string $role)use($db,&$people):int{
    $db->prepare("INSERT INTO users(first_name,last_name,email,initials,display_role,account_status,organization_membership_status,date_of_birth,adult_participant) VALUES('Concurrent',?,?, 'CT',?,'active','approved',?,?)")->execute([$role,bin2hex(random_bytes(10)).'@rc1.invalid',$role,$role==='student'?'2015-01-01':'1980-01-01',(int)($role!=='student')]);$id=(int)$db->lastInsertId();$people[]=$id;
    $db->prepare('INSERT INTO auth_user_roles(user_id,role_id) SELECT ?,id FROM auth_roles WHERE code=?')->execute([$id,$role]);return $id;
};
$spawn=static function(string $operation,int $actor,int $target,int $student=0):array{
    $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$operation,(string)$actor,(string)$target,(string)$student],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,getenv());
    if(!is_resource($process))throw new RuntimeException('Worker launch failed.');fclose($pipes[0]);stream_set_timeout($pipes[1],10);
    if(trim((string)fgets($pipes[1]))!=='READY')throw new RuntimeException('Worker did not reach the transaction barrier.');return [$process,$pipes];
};
$finish=static function(array $worker):string{
    [$process,$pipes]=$worker;$out=trim(stream_get_contents($pipes[1]));$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    if($exit!==0||$err!=='')throw new RuntimeException('Worker process failed: '.$err);return $out;
};
try{
    $admin=$person('administrator');$adult=$person('member');$student=$person('student');
    $db->prepare("INSERT INTO family_relationships(guardian_user_id,student_user_id,relationship_type,status) VALUES(?,?,'guardian','active')")->execute([$admin,$student]);
    $makeOffer=static function()use($db,$admin,$adult,&$productions):array{
        $db->prepare("INSERT INTO productions(title,status,lifecycle_stage,participant_category) VALUES('Concurrent acceptance','planning','planning','mixed')")->execute();$production=(int)$db->lastInsertId();$productions[]=$production;
        $record=CastingService::add($db,$production,$admin,$adult);CastingService::update($db,$production,$admin,$record,['casting_status'=>'offered','role_title'=>'Concurrent role']);return [$production,$record,CastingOfferService::issue($db,$production,$record,$admin)];
    };
    [$production,$record,$offer]=$makeOffer();
    $db->beginTransaction();$db->query('SELECT id FROM productions WHERE id='.$production.' FOR UPDATE');
    $a=$spawn('accept',$adult,$offer);$b=$spawn('accept',$adult,$offer);
    usleep(150000);$assert(proc_get_status($a[0])['running']&&proc_get_status($b[0])['running'],'Both acceptance processes overlap behind a real production lock');
    $db->commit();$out=[$finish($a),$finish($b)];sort($out);
    $assert($out===['ACCEPTED','REJECTED'],'Concurrent acceptance has exactly one winner');
    $assert((int)$db->query("SELECT COUNT(*) FROM production_memberships WHERE production_id=$production AND user_id=$adult AND audience_type='adult' AND status='active'")->fetchColumn()===1,'Concurrent acceptance creates one Adult roster membership');
    $assert((int)$db->query("SELECT COUNT(*) FROM theatre_history_credits WHERE production_id=$production AND user_id=$adult")->fetchColumn()===1,'Concurrent acceptance creates one retained history credit');
    [$closed,$closedRecord,$closedOffer]=$makeOffer();
    $db->beginTransaction();$db->query('SELECT id FROM productions WHERE id='.$closed.' FOR UPDATE');$worker=$spawn('accept',$adult,$closedOffer);usleep(100000);
    $db->prepare("UPDATE productions SET lifecycle_stage='completed' WHERE id=?")->execute([$closed]);$db->commit();
    $assert($finish($worker)==='REJECTED','Production closure racing acceptance rejects enrollment');
    $assert($db->query('SELECT status FROM production_cast_offers WHERE id='.$closedOffer)->fetchColumn()==='pending','Failed enrollment rolls offer response back');
    $assert((int)$db->query('SELECT COUNT(*) FROM production_memberships WHERE production_id='.$closed)->fetchColumn()===0,'Failed enrollment leaves no partial membership');
    $db->beginTransaction();$db->query('SELECT id FROM productions WHERE id='.$production.' FOR UPDATE');
    $approve=$spawn('approve',$admin,$production,$student);$revoke=$spawn('revoke',$admin,$production,$student);usleep(100000);$db->commit();
    $assert($finish($approve)==='ACCEPTED'&&$finish($revoke)==='ACCEPTED','Concurrent exception approval/revocation serialize successfully');
    $assert((int)$db->query("SELECT COUNT(*) FROM production_age_exceptions WHERE production_id=$production AND student_user_id=$student AND revoked_at IS NULL")->fetchColumn()<=1,'Exception race never leaves duplicate live approvals');
    $staff=$person('production_staff');
    $db->beginTransaction();$db->query('SELECT id FROM productions WHERE id='.$production.' FOR UPDATE');
    $casting=$spawn('cast-add',$staff,$production,$student);$removal=$spawn('remove-role',$admin,$staff);
    $assert($finish($removal)==='ACCEPTED','Role removal commits while casting waits on production');$db->commit();
    $assert($finish($casting)==='REJECTED_PERMISSION','Concurrent casting rechecks removed staff authority');
    $assert((int)$db->query("SELECT COUNT(*) FROM production_casting_records WHERE production_id=$production AND user_id=$student")->fetchColumn()===0,'Role race leaves no unauthorized casting record');
    $guardian=$person('member');$db->prepare("INSERT INTO family_relationships(guardian_user_id,student_user_id,relationship_type,status) VALUES(?,?,'guardian','active')")->execute([$guardian,$student]);
    $thread=CommunicationExperience::mobileCreateConversation($db,['id'=>$adult,'roles'=>['member']],$student,'Concurrent guardian revocation','Earlier retained message');
    $last=(int)$db->query('SELECT MAX(id) FROM messages WHERE conversation_id='.$thread)->fetchColumn();
    $db->beginTransaction();$db->query('SELECT id FROM conversations WHERE id='.$thread.' FOR UPDATE');
    $send=$spawn('send-message',$guardian,$thread);usleep(100000);
    $db->prepare("UPDATE family_relationships SET status='inactive' WHERE guardian_user_id=? AND student_user_id=?")->execute([$guardian,$student]);$db->commit();
    $assert($finish($send)==='REJECTED','Guardian revocation racing a waiting send rejects the former guardian');
    $assert((int)$db->query('SELECT COUNT(*) FROM messages WHERE conversation_id='.$thread)->fetchColumn()===1,'Rejected guardian send leaves no message');
    $assert(!SafeguardedConversationPolicy::canReceiveLive($db,$thread,$guardian),'Queued recipient gate rejects concurrently revoked guardian');
    $assert((int)$db->query("SELECT history_through_message_id FROM conversation_participants WHERE conversation_id=$thread AND user_id=$guardian")->fetchColumn()===$last,'Concurrent revocation preserves exact historical cutoff');
    echo "PASS $checks real multiprocess MySQL concurrency assertions; no delivery workers or live accounts.\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    if(isset($student))$db->prepare("UPDATE users SET account_status='disabled' WHERE id=?")->execute([$student]);
    foreach($people as $id)$db->prepare("UPDATE users SET account_status='disabled' WHERE id=?")->execute([$id]);
    foreach($productions as $id)$db->prepare("UPDATE productions SET lifecycle_stage='archived',status='archived',is_active=0 WHERE id=?")->execute([$id]);
}
