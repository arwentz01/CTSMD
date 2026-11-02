<?php
declare(strict_types=1);
// Production-mode HTTP behavior against synthetic loopback data only; no identity bypass.
if(PHP_SAPI!=='cli-server'||!in_array(getenv('APP_ENV'),['production','local'],true)||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE'))||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit;}
$path=parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/';if(str_starts_with($path,'/assets/')&&!str_contains($path,'..')&&is_file(dirname(__DIR__,2).$path))return false;
$_SERVER['SCRIPT_NAME']='/front.php';require dirname(__DIR__,2).'/front.php';
