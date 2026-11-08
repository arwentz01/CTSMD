<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/StudentInvitationStatusService.php';

if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){
    echo "SKIP: pdo_sqlite is required for student invitation status scenarios.\n";
    exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT,account_status TEXT,active INTEGER)');
$db->exec('CREATE TABLE family_relationships(guardian_user_id INTEGER,student_user_id INTEGER,relationship_type TEXT,status TEXT)');
$db->exec('CREATE TABLE auth_roles(id INTEGER PRIMARY KEY,code TEXT,active INTEGER)');
$db->exec('CREATE TABLE auth_user_roles(user_id INTEGER,role_id INTEGER)');
$db->exec('CREATE TABLE auth_email_verifications(id INTEGER PRIMARY KEY,user_id INTEGER,expires_at TEXT,verified_at TEXT)');
$db->exec("INSERT INTO auth_roles(id,code,active) VALUES (1,'student',1)");
$db->exec("INSERT INTO users VALUES
 (1,'Parent','Example','parent@example.org','active',1),
 (2,'Pending','Student','pending@example.org','managed',1),
 (3,'Expired','Student','expired@example.org','managed',1),
 (4,'No Email','Student',NULL,'managed',1),
 (5,'Active','Student','active@example.org','active',1),
 (6,'Other','Student','other@example.org','managed',1),
 (7,'Unsent','Student','unsent@example.org','managed',1)");
$db->exec("INSERT INTO auth_user_roles(user_id,role_id) VALUES (2,1),(3,1),(4,1),(5,1),(6,1),(7,1)");
$db->exec("INSERT INTO family_relationships VALUES
 (1,2,'parent','active'),(1,3,'guardian','active'),(1,4,'parent','active'),
 (1,5,'parent','active'),(8,6,'parent','active'),(1,7,'parent','active')");
$db->exec("INSERT INTO auth_email_verifications(user_id,expires_at,verified_at) VALUES
 (2,datetime('now','+1 day'),NULL),(3,datetime('now','-1 day'),NULL),
 (5,datetime('now','-2 days'),datetime('now'))");
$rows=StudentInvitationStatusService::forGuardian($db,1);
$statuses=[];foreach($rows as $row)$statuses[(int)$row['child_id']]=$row['status'];
ksort($statuses);
if($statuses!==[2=>'pending',3=>'expired',7=>'not_sent'])throw new RuntimeException('Expected only pending, expired and unsent invitations for linked students; got '.json_encode($statuses));
if(count(StudentInvitationStatusService::forGuardian($db,8))!==1)throw new RuntimeException('Guardian boundaries failed.');
if(StudentInvitationStatusService::forGuardian($db,9)!==[])throw new RuntimeException('Unrelated account must see no student status.');
echo "PASS: student invitation status, no-email exclusion, activation clearance, and guardian scope.\n";
