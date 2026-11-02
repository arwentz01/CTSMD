<?php
declare(strict_types=1);
/** Explicit synthetic local fixture; never a migration runner or public endpoint. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1]??'')!=='--create-synthetic'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE'))){fwrite(STDERR,"Requires --create-synthetic, APP_ENV=rc1-test, loopback and an explicitly disposable ctsmd_rc1_* database. Does not install migrations.\n");exit(1);}
require_once dirname(__DIR__).'/tests/fixtures/commerce.php';
try{$db=Database::connect(dirname(__DIR__));$f=commerceFixture($db);echo json_encode(['local_only'=>true,'database'=>(string)getenv('DB_DATABASE'),'organization_key'=>'commerce-'.$f['suffix'],'administrator_email'=>'admin.'.$f['suffix'].'@rc1.invalid','tester_email'=>'member.'.$f['suffix'].'@rc1.invalid','password'=>$f['password'],'performance_id'=>$f['performance'],'layout_id'=>$f['layout'],'entry'=>'/admin/commerce','warning'=>'Synthetic credentials. Store privately; never use on production. No payment or email delivery.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(Throwable $e){fwrite(STDERR,"Synthetic fixture setup failed. Check the migrated disposable schema. No production action is supported.\n");exit(1);}
