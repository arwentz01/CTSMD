<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/ReleaseCandidate.php';
$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
foreach(['.env','.env.example','storage/private/a.txt','database/seeds/001_demo.sql','mobile/node_modules/example/index.js'] as $path)$assert(ReleaseCandidate::category($path)==='excluded','Sensitive/reset file excluded: '.$path);
$assert(ReleaseCandidate::category('bin/process-push-queue.php')==='operator_only_not_public','Worker is not web payload');
$assert(ReleaseCandidate::category('mobile/app/login.tsx')==='native_source_not_distributable','Native source is not signed build');
$assert(ReleaseCandidate::category('database/schema.sql')==='schema_reference_not_executable','Source schema contract is hashed, not deployed/imported');
$assert(ReleaseCandidate::candidateScope(dirname(__DIR__))['candidate']==='CTSMD-ADULT-PARTICIPATION-REVIEW','Current enhancement is not mislabeled as original RC1');
$assert(ReleaseCandidate::candidateScope(dirname(__DIR__))['release_scope']==='adult_enhancement_separate_from_rc1','Enhancement release scope is explicit');
$inventory=ReleaseCandidate::inventory(dirname(__DIR__));
$assert(isset($inventory['columns']['users']['date_of_birth']),'086 included');
$assert(isset($inventory['tables']['rehearsal_videos']),'091 included');
$assert(isset($inventory['triggers']['trg_family_conversation_guardian_update']),'Revised guardian trigger expected');
$assert(isset($inventory['dropped']['trg_family_conversation_guardian_insert']),'Obsolete widening trigger forbidden');
$assert(count(array_filter($inventory['migrations'],static fn(array $m):bool=>str_starts_with(basename($m['path']),'069_')))===2,'Both 069 migrations enumerated by filename');
$assert(ReleaseCandidate::normalizeSql("SET value='a b';")!==ReleaseCandidate::normalizeSql("SET value='ab';"),'Trigger literal whitespace remains significant');
$assert(ReleaseCandidate::normalizeSql("SET value='--literal';")!==ReleaseCandidate::normalizeSql("SET value='';"),'Comment-looking literal remains significant');
$assert(ReleaseCandidate::normalizeSql("SET value='Guardian';")!==ReleaseCandidate::normalizeSql("SET value='guardian';"),'Trigger literal case remains significant');
$entries=[];
foreach(['front.php','.htaccess','src/Auth.php','src/MobileApi.php','src/SafeguardedConversationPolicy.php','database/schema.sql'] as $path)$entries[]=['path'=>$path,'category'=>ReleaseCandidate::category($path),'sha256'=>$path==='front.php'?str_repeat('0',64):hash_file('sha256',dirname(__DIR__).'/'.$path)];
$assert(in_array('front.php',ReleaseCandidate::verifyFiles(dirname(__DIR__),['format'=>1,'revision'=>str_repeat('a',40),'files'=>$entries]),true),'Payload hash mismatch is detected');
$fixtureRoot=sys_get_temp_dir().'/ctsmd_rc1_tooling_'.bin2hex(random_bytes(8));$created=[];
mkdir($fixtureRoot,0700);mkdir($fixtureRoot.'/src',0700);mkdir($fixtureRoot.'/database',0700);
try{
 $fixtureEntries=[];
 foreach(['front.php','.htaccess','src/Auth.php','src/MobileApi.php','src/SafeguardedConversationPolicy.php','database/schema.sql'] as $path){$created[]=$fixtureRoot.'/'.$path;file_put_contents($fixtureRoot.'/'.$path,'synthetic fixture');$fixtureEntries[]=['path'=>$path,'category'=>ReleaseCandidate::category($path),'sha256'=>hash_file('sha256',$fixtureRoot.'/'.$path)];}
 $created[]=$fixtureRoot.'/.unexpected.phtml';file_put_contents($fixtureRoot.'/.unexpected.phtml','synthetic fixture');
 $assert(in_array('unmanaged executable: .unexpected.phtml',ReleaseCandidate::verifyFiles($fixtureRoot,['format'=>1,'revision'=>str_repeat('a',40),'files'=>$fixtureEntries]),true),'Hidden unmanaged executable detected');
 $fixtureEntries[]=['path'=>'.unexpected.phtml','category'=>'evidence_not_public','sha256'=>hash_file('sha256',$fixtureRoot.'/.unexpected.phtml')];
 $assert(in_array('unmanaged executable: .unexpected.phtml',ReleaseCandidate::verifyFiles($fixtureRoot,['format'=>1,'revision'=>str_repeat('a',40),'files'=>$fixtureEntries]),true),'Non-web manifest entry cannot authorize a web-root executable');
}finally{foreach($created as $file)unlink($file);rmdir($fixtureRoot.'/src');rmdir($fixtureRoot.'/database');rmdir($fixtureRoot);}
final class Rc1MetadataPDO extends PDO {
 public function __construct(private array $contract,private bool $broken=false){}
 public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{
  if(str_starts_with($query,'SELECT r.code,r.active,COUNT(rp.permission_id)'))return new Rc1MetadataStatement([]);
  if(!str_starts_with($query,'SELECT ')||!str_contains($query,'information_schema.'))throw new RuntimeException('Non-metadata read rejected.');
  $rows=[];
  if(str_contains($query,'information_schema.TABLES'))foreach($this->contract['tables'] as $table=>$_)$rows[]=['TABLE_NAME'=>$table,'ENGINE'=>'InnoDB'];
  if(str_contains($query,'information_schema.COLUMNS'))foreach($this->contract['columns'] as $table=>$columns)foreach($columns as $column=>$_){if($this->broken&&$column==='date_of_birth')continue;$rows[]=['TABLE_NAME'=>$table,'COLUMN_NAME'=>$column,'COLUMN_TYPE'=>match($column){'date_of_birth'=>'date','access_revoked_at'=>'datetime','history_through_message_id'=>'bigint unsigned',default=>'unknown'},'IS_NULLABLE'=>'YES'];}
  if(str_contains($query,'information_schema.TRIGGERS'))return new Rc1MetadataStatement([]); // Must fail closed, never merely count triggers.
  return new Rc1MetadataStatement($rows);
 }
}
final class Rc1MetadataStatement extends PDOStatement {
 public function __construct(private array $rows){}
 public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
}
$result=ReleaseCandidate::inspect(new Rc1MetadataPDO($inventory,true),dirname(__DIR__));
$assert(in_array('required active role: adult_performer',$result['issues'],true),'Missing performer role fails preflight');
$assert(!$result['schema_matches_declared_contracts'],'Missing metadata fails closed');
$assert(in_array('column: users.date_of_birth',$result['issues'],true),'DOB failure is actionable');
$assert((bool)array_filter($result['issues'],static fn(string $s):bool=>str_starts_with($s,'trigger missing/drifted:')),'Trigger absence detected');
foreach(['../.env','/etc/passwd','src/../../.env','src\\Auth.php'] as $path){
 try{ReleaseCandidate::verifyFiles(dirname(__DIR__),['format'=>1,'revision'=>str_repeat('a',40),'files'=>[['path'=>$path,'category'=>'web_runtime','sha256'=>str_repeat('a',64)]]]);throw new LogicException('Unsafe manifest accepted.');}catch(RuntimeException){$checks++;}
}
try{ReleaseCandidate::verifyFiles(dirname(__DIR__),['format'=>1,'revision'=>str_repeat('a',40),'files'=>[['path'=>'docs/TODO.md','category'=>'evidence_not_public','sha256'=>str_repeat('a',64)]]]);throw new LogicException('Incomplete manifest accepted.');}catch(RuntimeException){$checks++;}
echo "PASS: $checks RC1 tooling safety contracts (no database/network).\n";
