<?php
declare(strict_types=1);
// Test-only routing, no identity bypass. Real login and CSRF handlers are exercised.
if(getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE'))||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit;}
$_SERVER['SCRIPT_NAME']='/front.php';
require dirname(__DIR__,2).'/front.php';
