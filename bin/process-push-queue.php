<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/PushService.php';

try{$db=Database::connect(dirname(__DIR__));$result=PushService::processQueue($db,(int)($argv[1]??25));echo 'Push deliveries sent: '.$result['sent'].'; failed: '.$result['failed'].PHP_EOL;exit($result['failed']>0?2:0);}catch(Throwable $e){fwrite(STDERR,'Push queue failed: '.$e->getMessage().PHP_EOL);exit(1);}
