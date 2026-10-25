<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/ProductionLifecycleAutomation.php';

$counts=ProductionLifecycleAutomation::applyDue(Database::connect(dirname(__DIR__)));
fwrite(STDOUT,json_encode($counts,JSON_THROW_ON_ERROR).PHP_EOL);
