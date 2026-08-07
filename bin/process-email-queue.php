<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
require_once $root.'/src/Database.php';
require_once $root.'/src/MailService.php';
$db=Database::connect($root);
$limit=isset($argv[1])?(int)$argv[1]:25;
$result=MailService::process($db,$root,$limit);
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
