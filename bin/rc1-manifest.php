<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/src/ReleaseCandidate.php';
try{echo json_encode(ReleaseCandidate::manifest(dirname(__DIR__)),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
catch(Throwable){fwrite(STDERR,"RC1 manifest failed. Requires a clean, supported Git checkout. No deployment performed.\n");exit(1);}
