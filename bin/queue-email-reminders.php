<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
require_once $root.'/src/Database.php';
require_once $root.'/src/NotificationReminderService.php';
$db=Database::connect($root);
$appUrl=(string)(getenv('APP_URL')?:'http://localhost/CTSMD');
$result=NotificationReminderService::queueDue($db,$appUrl);
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
