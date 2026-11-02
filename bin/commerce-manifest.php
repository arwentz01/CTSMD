<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/src/ReleaseCandidate.php';
try{$m=ReleaseCandidate::manifest(dirname(__DIR__));$m['platform_scope']=$m['release_scope'];$m['candidate']='CONNECT-COMMERCE-PHASE3-TEST-PILOT';$m['release_scope']='commerce_test_only';$m['baseline']='e9cf460';$m['required_commerce_migrations']=range(101,109);$m['hosted_sql_authorized']=false;$m['public_sales_authorized']=false;$m['live_payments_supported']=false;$m['audience_email_authorized']=false;echo json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(Throwable){fwrite(STDERR,"Commerce manifest failed. Requires clean supported Git checkout. No deployment performed.\n");exit(1);}
