<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
require_once $root.'/src/Database.php';
require_once $root.'/src/MailService.php';
$db=Database::connect($root);
$limit=25;$queueId=null;$auto=false;
for($i=1;$i<count($argv);$i++){
    $arg=(string)$argv[$i];
    if($arg==='--auto'){$auto=true;continue;}
    if(str_starts_with($arg,'--queue-id=')){$queueId=(int)substr($arg,11);continue;}
    if($arg==='--queue-id'){$queueId=(int)($argv[++$i]??0);continue;}
    if(ctype_digit($arg)){$limit=(int)$arg;continue;}
    throw new InvalidArgumentException('Usage: process-email-queue.php [limit] [--queue-id ID] [--auto]');
}
$minId=null;
if($auto){
    if($queueId!==null)throw new InvalidArgumentException('Automatic mode cannot target a specific message.');
    $raw=trim((string)(getenv('MAIL_AUTO_MIN_ID')?:''));
    if(!ctype_digit($raw)||(int)$raw<1)throw new RuntimeException('MAIL_AUTO_MIN_ID must be configured with an approved minimum eligible queue ID before automatic email delivery.');
    $minId=(int)$raw;
}
$result=MailService::process($db,$root,$limit,$queueId,$minId);
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
