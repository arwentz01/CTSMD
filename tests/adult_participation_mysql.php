<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/AdultParticipantService.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE')))throw new RuntimeException('Explicit disposable loopback rc1-test schema required.');
$db=Database::connect(dirname(__DIR__));$checks=0;$ids=[];$productions=[];$suffix=bin2hex(random_bytes(6));
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label."\n";};
$denied=static function(callable $fn,string $label)use($assert):void{$blocked=false;try{$fn();}catch(RuntimeException){$blocked=true;}$assert($blocked,$label);};
$person=static function(string $role,string $dob)use($db,&$ids,$suffix):int{
    $db->prepare("INSERT INTO users(first_name,last_name,email,initials,display_role,account_status,active,organization_membership_status,date_of_birth) VALUES('AdultTest',?,?,'AT',?,'active',1,'approved',?)")->execute([$role,$role.'.'.$suffix.'@rc1.invalid',$role,$dob]);$id=(int)$db->lastInsertId();$ids[]=$id;
    $db->prepare('INSERT INTO auth_user_roles(user_id,role_id) SELECT ?,id FROM auth_roles WHERE code=?')->execute([$id,$role]);return $id;
};
try{
    $admin=$person('administrator','1980-01-01');$adult=$person('member','1990-01-01');$student=$person('student','2017-01-01');$volunteer=$person('volunteer','1990-01-01');$staff=$person('production_staff','1980-01-01');
    foreach(['mixed','adults'] as $category){$db->prepare("INSERT INTO productions(title,status,lifecycle_stage,participant_category,student_minimum_age,participant_eligibility_date) VALUES(?,'planning','planning',?,10,'2026-10-28')")->execute(['Adult test '.$suffix,$category]);$productions[]=(int)$db->lastInsertId();}
    [$mixed,$adultOnly]=$productions;
    $denied(fn()=>ProductionParticipantEligibility::saveSettings($db,$mixed,$adult,['participant_category'=>'mixed']),'Settings forged actor denied');
    $denied(fn()=>ProductionParticipantEligibility::saveSettings($db,$mixed,$admin,['participant_category'=>'mixed','student_minimum_age'=>'9']),'Restricted ages require explicit date');
    ProductionParticipantEligibility::saveSettings($db,$mixed,$admin,['participant_category'=>'mixed','student_minimum_age'=>'10','participant_eligibility_date'=>'2026-10-28']);
    $assert((int)$db->query('SELECT student_minimum_age FROM productions WHERE id='.$mixed)->fetchColumn()===10,'Participation settings persisted');
    $denied(fn()=>AdultParticipantService::classify($db,$adult,$adult,true,'Self approval'),'Self classification denied');
    AdultParticipantService::classify($db,$admin,$adult,true,'Reviewed independent Adult participation');
    $assert(ProductionParticipantEligibility::forUser($db,$mixed,$adult)['eligible'],'Reviewed Adult eligible');
    $assert(!in_array('production.manage',Auth::permissions($db,$adult),true),'Adult gains no management authority');
    $denied(fn()=>AdultParticipantService::classify($db,$admin,$student,true,'DOB bypass'),'Student classification bypass denied');
    $assert(ProductionParticipantEligibility::forUser($db,$mixed,$student)['status']==='below_minimum_age','Stored Student DOB enforced');
    $denied(fn()=>ProductionParticipantEligibility::exception($db,$mixed,$student,$adult,'Forged approval'),'Forged exception denied');
    $denied(fn()=>ProductionParticipantEligibility::exception($db,$mixed,$student,$staff,'Unassigned staff'),'Cross-production staff exception denied');
    ProductionParticipantEligibility::exception($db,$mixed,$student,$admin,'Reviewed age exception');
    $assert(ProductionParticipantEligibility::forUser($db,$mixed,$student)['status']==='authorized_exception','Audited exception used');
    ProductionParticipantEligibility::exception($db,$adultOnly,$student,$admin,'Age only');
    $assert(ProductionParticipantEligibility::forUser($db,$adultOnly,$student)['status']==='wrong_participant_category','Age exception cannot waive Adults-only');
    ProductionParticipantEligibility::exception($db,$mixed,$student,$admin,'Exception withdrawn',true);
    $assert(!ProductionParticipantEligibility::forUser($db,$mixed,$student)['eligible'],'Revoked exception rejected');
    $db->prepare('UPDATE users SET date_of_birth=? WHERE id=?')->execute(['1980-01-01',$student]);
    $assert(IdentityRolePolicy::isStudent($db,$student),'DOB edit preserves Student');
    $denied(fn()=>IdentityRolePolicy::assertRoleSelection($db,$student,[]),'Generic role edit cannot remove safeguarding');
    $db->prepare("UPDATE productions SET lifecycle_stage='archived',status='archived' WHERE id=?")->execute([$adultOnly]);
    $assert(!ProductionParticipantEligibility::forUser($db,$adultOnly,$adult)['eligible'],'Archived production denied');
    $db->prepare("UPDATE users SET account_status='disabled' WHERE id=?")->execute([$adult]);
    $assert(!ProductionParticipantEligibility::forUser($db,$mixed,$adult)['eligible'],'Disabled Adult denied');
    $assert((int)$db->query("SELECT COUNT(*) FROM audit_events WHERE event_type='participation.adult_classification' AND subject_id=".$adult)->fetchColumn()===1,'Classification audit retained');
    echo "PASS $checks migrated MySQL participation assertions; synthetic records only.\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    foreach($ids as $id)$db->prepare("UPDATE users SET account_status='disabled' WHERE id=?")->execute([$id]);
    foreach($productions as $id)$db->prepare("UPDATE productions SET lifecycle_stage='archived',status='archived',is_active=0 WHERE id=?")->execute([$id]);
}
