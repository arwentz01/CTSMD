<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/ReleaseCandidate.php';
$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
foreach(['.env','.env.example','storage/private/a.txt','database/seeds/001_demo.sql','mobile/node_modules/example/index.js'] as $path)$assert(ReleaseCandidate::category($path)==='excluded','Sensitive/reset file excluded: '.$path);
$assert(ReleaseCandidate::category('bin/process-push-queue.php')==='operator_only_not_public','Worker is not web payload');
$assert(ReleaseCandidate::category('mobile/app/login.tsx')==='native_source_not_distributable','Native source is not signed build');
$inventory=ReleaseCandidate::inventory(dirname(__DIR__));
$assert(isset($inventory['columns']['users']['date_of_birth']),'086 included');
$assert(isset($inventory['tables']['rehearsal_videos']),'091 included');
$assert(isset($inventory['triggers']['trg_family_conversation_guardian_update']),'Revised guardian trigger expected');
$assert(isset($inventory['dropped']['trg_family_conversation_guardian_insert']),'Obsolete widening trigger forbidden');
$assert(count(array_filter($inventory['migrations'],static fn(array $m):bool=>str_starts_with(basename($m['path']),'069_')))===2,'Both 069 migrations enumerated by filename');
$entries=[];
foreach(['front.php','.htaccess','src/Auth.php','src/MobileApi.php','src/SafeguardedConversationPolicy.php'] as $path)$entries[]=['path'=>$path,'category'=>'web_runtime','sha256'=>$path==='front.php'?str_repeat('0',64):hash_file('sha256',dirname(__DIR__).'/'.$path)];
$assert(in_array('front.php',ReleaseCandidate::verifyFiles(dirname(__DIR__),['format'=>1,'revision'=>str_repeat('a',40),'files'=>$entries]),true),'Payload hash mismatch is detected');
final class Rc1MetadataPDO extends PDO {
 public function __construct(private array $contract,private bool $broken=false){}
 public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{
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
$assert(!$result['schema_matches_declared_contracts'],'Missing metadata fails closed');
$assert(in_array('column: users.date_of_birth',$result['issues'],true),'DOB failure is actionable');
$assert((bool)array_filter($result['issues'],static fn(string $s):bool=>str_starts_with($s,'trigger missing/drifted:')),'Trigger absence detected');
foreach(['../.env','/etc/passwd','src/../../.env','src\\Auth.php'] as $path){
 try{ReleaseCandidate::verifyFiles(dirname(__DIR__),['format'=>1,'revision'=>str_repeat('a',40),'files'=>[['path'=>$path,'category'=>'web_runtime','sha256'=>str_repeat('a',64)]]]);throw new LogicException('Unsafe manifest accepted.');}catch(RuntimeException){$checks++;}
}
try{ReleaseCandidate::verifyFiles(dirname(__DIR__),['format'=>1,'revision'=>str_repeat('a',40),'files'=>[['path'=>'docs/TODO.md','category'=>'evidence_not_public','sha256'=>str_repeat('a',64)]]]);throw new LogicException('Incomplete manifest accepted.');}catch(RuntimeException){$checks++;}
echo "PASS: $checks RC1 tooling safety contracts (no database/network).\n";
