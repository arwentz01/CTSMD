<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/src/ReleaseCandidate.php';
require_once dirname(__DIR__).'/src/Database.php';
$args=getopt('',['target:','manifest:','web-root:']);
if(!in_array($args['target']??'',['local','staging','production'],true)){fwrite(STDERR,"Usage: php bin/rc1-preflight.php --target=local|staging|production [--manifest=/private/rc1.json]\nRead-only inspection only; target is an evidence label, not a connection selector.\n");exit(1);}
try{
 $root=dirname(__DIR__);$errors=[];
 if(isset($args['manifest'])){$manifest=json_decode(file_get_contents($args['manifest']),true,512,JSON_THROW_ON_ERROR);$errors=ReleaseCandidate::verifyFiles($root,$manifest);if(isset($args['web-root'])){$webRoot=realpath($args['web-root']);if(!$webRoot)throw new RuntimeException('Invalid web root.');$errors=array_merge($errors,ReleaseCandidate::verifyFiles($webRoot,$manifest,true));}}
 elseif(isset($args['web-root']))throw new RuntimeException('Web verification requires a manifest.');
 if($args['target']!=='local'&&!isset($manifest))$errors[]='Staging/production inspection requires a trusted revision-bound manifest.';
 $db=Database::connect($root);
 if(in_array($args['target'],['staging','production'],true)&&getenv('APP_ENV')!==$args['target'])throw new RuntimeException('Environment label mismatch.');
 $db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
 try{$result=ReleaseCandidate::inspect($db,$root);}finally{$db->rollBack();}
 $result=['candidate'=>'CTSMD-RC1','target_label'=>$args['target'],'inspection_utc'=>gmdate('c'),'source_revision'=>$manifest['revision']??null,'manifest_checked'=>isset($manifest),'file_issues'=>$errors,'release_authorized'=>false]+$result;
 echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
 exit($errors||!$result['schema_matches_declared_contracts']?2:0);
}catch(Throwable){fwrite(STDERR,"RC1 preflight failed safely. Check private configuration, manifest and read-only metadata privileges. No SQL applied; no credentials or database content printed.\n");exit(1);}
