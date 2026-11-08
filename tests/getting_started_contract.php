<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/HomeDashboardService.php';

if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){
    fwrite(STDOUT,"SKIP: PDO SQLite is not available; run this test in an environment with pdo_sqlite.\n");
    exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY,email_verified_at TEXT NULL,onboarding_completed_at TEXT NULL)');
$db->exec("INSERT INTO users (id,email_verified_at,onboarding_completed_at) VALUES (1,'2026-11-07 12:00:00',NULL),(2,'2026-11-07 12:00:00','2026-11-07 12:05:00'),(3,'2026-11-07 12:00:00',NULL),(4,'2026-11-07 12:00:00',NULL),(5,NULL,NULL)");
$check=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);};
$base=['name'=>'Test Account','role'=>'member','roles'=>['member'],'organization_membership_status'=>'pending'];
$parent=HomeDashboardService::gettingStarted($db,$base+['id'=>1],[]);
$check($parent['audience']==='family'&&$parent['outstanding'],'Parent awaiting household setup and approval sees checklist.');
$check($parent['steps'][1]['state']==='action','Household setup is a user action.');
$check($parent['steps'][2]['state']==='waiting','Membership approval is explicitly a staff action.');
$ready=HomeDashboardService::gettingStarted($db,array_replace($base,['id'=>2,'organization_membership_status'=>'approved']),[['id'=>27]]);
$check(!$ready['outstanding'],'Approved parent with completed household and an active show has no pending onboarding.');
$student=HomeDashboardService::gettingStarted($db,array_replace($base,['id'=>3,'roles'=>['student']]),[]);
$check($student['audience']==='student'&&$student['steps'][1]['state']==='waiting','Students cannot assign their own production.');
$staff=HomeDashboardService::gettingStarted($db,array_replace($base,['id'=>4,'roles'=>['production_staff']]),[]);
$check($staff['audience']==='staff'&&$staff['steps'][1]['state']==='waiting','Staff without a show sees a staff-assignment waiting state.');
$denied=HomeDashboardService::gettingStarted($db,array_replace($base,['id'=>1,'organization_membership_status'=>'denied']),[]);
$check($denied['steps'][2]['state']==='needs_help','Denied membership is not falsely presented as pending approval.');
$unverified=HomeDashboardService::gettingStarted($db,$base+['id'=>5],[]);
$check(!$unverified['steps'][0]['done'],'Unverified email cannot be marked complete.');
echo "PASS: first-use status, role, approval, production and household transitions.\n";
