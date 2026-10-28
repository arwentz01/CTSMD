<?php
declare(strict_types=1);
/** Fresh disposable local schema ONLY. Never a deployment/migration runner. */
require_once dirname(__DIR__).'/src/Database.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE')))throw new RuntimeException('Requires explicit rc1-test, loopback and a ctsmd_rc1_* disposable schema.');
$db=Database::connect(dirname(__DIR__));
if((int)$db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn()!==0)throw new RuntimeException('Refusing nonempty schema. Use a new disposable database, never reset an existing one.');
$files=array_merge([dirname(__DIR__).'/database/schema.sql'],glob(dirname(__DIR__).'/database/migrations/*.sql'));
$mysql=(string)(getenv('RC1_MYSQL_BIN')?:'mysql');
$env=getenv();$env['MYSQL_PWD']=(string)(getenv('DB_PASSWORD')!==false?getenv('DB_PASSWORD'):'password');
foreach($files as $file){
 $sql=file_get_contents($file);
 if(basename($file)==='062_safeguarded_conversation_revocation.sql'){
  // Current canonical base already declares these two columns and their index.
  // This pinned, fresh-empty-schema-only overlay is NOT a hosted upgrade recipe.
  if(hash('sha256',$sql)!=='d85a0fbdf240a0c47e08007a729c48128226005f7169d4cdfd0d2d12c3f91c4e')throw new RuntimeException('062 overlay requires renewed review.');
  $sql=preg_replace('/ALTER TABLE conversation_participants\s+.*?;/s','',$sql,1,$removed);
  if($removed!==1)throw new RuntimeException('Unexpected 062 shape.');
  echo "Reviewed fresh-base overlay: omit duplicate 062 column/index ALTER; retain backfill and revised triggers.\n";
 }
 $pipes=[];$process=proc_open([$mysql,'--protocol=TCP','--host=127.0.0.1','--port='.(string)getenv('DB_PORT'),'--user='.(string)(getenv('DB_USERNAME')?:'andrew'),'--database='.(string)getenv('DB_DATABASE'),'--batch'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$env);
 if(!is_resource($process))throw new RuntimeException('Cannot start mysql client.');
 fwrite($pipes[0],$sql);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 if($exit!==0){fwrite(STDERR,'FAIL '.basename($file).": ".$err."\nPartial disposable schema retained for diagnosis; do not resume blindly.\n");exit(1);}
 echo 'PASS bootstrap '.basename($file)."\n";
}
echo "Fresh source schema bootstrap passed; no demo seed or worker executed.\n";
