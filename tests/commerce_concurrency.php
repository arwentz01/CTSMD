<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/commerce.php';
/** Two separate interpreters and fresh PDO connections start behind a shared barrier. */
function commerceRace(array $f,string $mode):array
{
    $dir=sys_get_temp_dir().'/commerce-race-'.bin2hex(random_bytes(8));mkdir($dir,0700);file_put_contents($dir.'/fixture.json',json_encode($f));chmod($dir.'/fixture.json',0600);$workers=[];
    try{for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/fixtures/commerce-worker.php',$dir.'/fixture.json',$dir.'/start',$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Cannot start concurrency worker');fclose($pipes[0]);$workers[]=[$process,$pipes];}touch($dir.'/start');$results=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($exit!==0||$err!=='')throw new RuntimeException('Worker failed: '.$err);$results[]=$out;}sort($results);return$results;
    }finally{foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
}
if(realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__){$db=Database::connect(dirname(__DIR__));$f=null;try{$f=commerceFixture($db);$results=commerceRace($f,'hold');if($results!==['REJECT','WIN'])throw new RuntimeException('Expected exactly one hold winner: '.json_encode($results));$q=$db->prepare("SELECT COUNT(*) FROM commerce_orders WHERE performance_id=? AND state='held'");$q->execute([$f['performance']]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Unexpected duplicate hold');echo "PASS Real competing processes: exactly one hold, one rejection\n";}finally{if($f)retireMyTheatreFixture($db,$f);}}
