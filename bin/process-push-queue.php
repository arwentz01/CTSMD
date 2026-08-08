<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/PushService.php';
require_once dirname(__DIR__).'/src/PushEventBridgeService.php';

try{
    $db=Database::connect(dirname(__DIR__));
    $queued=PushEventBridgeService::queueNew($db);
    $result=PushService::processQueue($db,(int)($argv[1]??25));
    echo 'Queued from activity: '.array_sum($queued).' (messages '.$queued['messages'].', Community '.$queued['community'].', notifications '.$queued['notifications'].').'.PHP_EOL;
    echo 'Push deliveries sent: '.$result['sent'].'; failed: '.$result['failed'].PHP_EOL;
    exit($result['failed']>0?2:0);
}catch(Throwable $e){fwrite(STDERR,'Push queue failed: '.$e->getMessage().PHP_EOL);exit(1);}
