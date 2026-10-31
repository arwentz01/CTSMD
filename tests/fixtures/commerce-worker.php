<?php
declare(strict_types=1);
require_once __DIR__.'/commerce.php';
if(getenv('APP_ENV')!=='rc1-test'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE'))||getenv('DB_HOST')!=='127.0.0.1')exit(3);
$f=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);putenv('COMMERCE_SANDBOX_ENABLED=1');putenv('COMMERCE_ORGANIZATION_KEY=commerce-'.$f['suffix']);$db=Database::connect(dirname(__DIR__,2));$deadline=microtime(true)+10;while(!is_file($argv[2])){if(microtime(true)>$deadline)exit(4);usleep(1000);}
try{if(($argv[3]??'hold')==='checkin'){require_once dirname(__DIR__,2).'/src/CommerceTicketService.php';CommerceTicketService::checkin($db,$f['admin'],$f['org'],$f['performance'],$f['scan']);}else commerceHold($db,$f,['a20'=>'Adult']);echo 'WIN';}catch(RuntimeException $e){echo 'REJECT';}
