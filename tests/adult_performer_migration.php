<?php
declare(strict_types=1);
/** Forward migration regression, disposable loopback schema only. Never deployment tooling. */
require_once dirname(__DIR__).'/src/Database.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE')))throw new RuntimeException('Explicit disposable schema required.');
$db=Database::connect(dirname(__DIR__));$ids=[];$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);++$checks;echo "PASS $label\n";};
$run=static function():void{
 $env=getenv();$env['MYSQL_PWD']=(string)(getenv('DB_PASSWORD')!==false?getenv('DB_PASSWORD'):'password');$pipes=[];
 $p=proc_open([(string)(getenv('RC1_MYSQL_BIN')?:'mysql'),'--protocol=TCP','--host=127.0.0.1','--port='.(string)getenv('DB_PORT'),'--user='.(string)(getenv('DB_USERNAME')?:'andrew'),'--database='.(string)getenv('DB_DATABASE'),'--batch'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$env);
 if(!is_resource($p))throw new RuntimeException('Cannot start migration client.');
 fwrite($pipes[0],file_get_contents(dirname(__DIR__).'/database/migrations/097_adult_performer_role.sql'));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($p)!==0)throw new RuntimeException('Disposable forward migration failed: '.$err);
};
try{
 foreach([['member',1],['member',0],['student',1]] as [$role,$legacy]){
  $db->prepare("INSERT INTO users(first_name,last_name,email,initials,display_role,account_status,adult_participant) VALUES('Migration','Fixture',?,'MF','Member','active',?)")->execute([bin2hex(random_bytes(12)).'@rc1.invalid',$legacy]);$id=(int)$db->lastInsertId();$ids[]=$id;
  $db->prepare('INSERT INTO auth_user_roles(user_id,role_id) SELECT ?,id FROM auth_roles WHERE code=?')->execute([$id,$role]);
 }
 [$legacy,$ordinary,$student]=$ids;
 $reviews=(int)$db->query('SELECT COUNT(*) FROM participant_transition_reviews')->fetchColumn();
 $run();$role=(int)$db->query("SELECT id FROM auth_roles WHERE code='adult_performer'")->fetchColumn();
 $has=static function(int $id)use($db,$role):bool{$q=$db->prepare('SELECT COUNT(*) FROM auth_user_roles WHERE user_id=? AND role_id=?');$q->execute([$id,$role]);return(bool)$q->fetchColumn();};
 $assert($has($legacy),'Legacy explicit Adult maps to Adult Performer');
 $assert(!$has($ordinary),'Ordinary Members receive no performer role');
 $assert(!$has($student),'Student legacy anomaly is skipped');
 $assert($db->query('SELECT decision FROM adult_performer_legacy_mappings WHERE user_id='.$student)->fetchColumn()==='skipped_student_or_child','Skipped anomaly recorded for operator review');
 $assert((int)$db->query('SELECT COUNT(*) FROM auth_role_permissions WHERE role_id='.$role)->fetchColumn()===0,'Adult Performer grants no staff/guardian permissions');
 $assert((int)$db->query('SELECT adult_participant FROM users WHERE id='.$legacy)->fetchColumn()===1,'Legacy populated column retained unchanged');
 $assert((int)$db->query('SELECT COUNT(*) FROM participant_transition_reviews')->fetchColumn()===$reviews,'Legacy review history retained');
 $db->prepare('DELETE FROM auth_user_roles WHERE user_id=? AND role_id=?')->execute([$legacy,$role]);$run();
 $assert(!$has($legacy),'Migration retry cannot restore a revoked role');
 $assert((int)$db->query("SELECT COUNT(*) FROM audit_events WHERE event_type='adult_performer.legacy_role_mapped' AND subject_id=$legacy")->fetchColumn()===1,'Retry preserves single mapping audit');
 echo "PASS $checks forward migration assertions.\n";
}finally{
 foreach($ids as $id)$db->prepare("UPDATE users SET active=0,account_status='disabled' WHERE id=?")->execute([$id]);
}
