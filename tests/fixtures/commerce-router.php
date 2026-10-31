<?php
declare(strict_types=1);
// Optional loopback development server: reuse RC1 auth/DB isolation without bypassing identity.
$path=parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/';
if(str_starts_with($path,'/assets/')&&is_file(dirname(__DIR__,2).$path)&&!str_contains($path,'..'))return false;
require __DIR__.'/rc1-router.php';
