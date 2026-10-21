<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
require_once $root.'/src/Database.php';
require_once $root.'/src/MailService.php';
$db=Database::connect($root);
$limit=25;$queueId=null;
for($i=1;$i<count($argv);$i++){
    $arg=(string)$argv[$i];
    if(str_starts_with($arg,'--queue-id=')){$queueId=(int)substr($arg,11);continue;}
    if($arg==='--queue-id'){$queueId=(int)($argv[++$i]??0);continue;}
    if(ctype_digit($arg)){$limit=(int)$arg;continue;}
    throw new InvalidArgumentException('Usage: process-email-queue.php [limit] [--queue-id ID]');
}
$result=MailService::process($db,$root,$limit,$queueId);
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
