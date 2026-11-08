<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/HelpGuideCatalog.php';

$guides=HelpGuideCatalog::all();
$failures=[];
$seen=[];
$categories=[];
foreach($guides as $guide){
    $id=(string)($guide['id']??'');
    if(!preg_match('/^[a-z0-9-]+$/',$id)||isset($seen[$id]))$failures[]='Guide id invalid or duplicated: '.$id;
    $seen[$id]=true;
    foreach(['title','category','audience','where','intro','tip','permission'] as $key){
        if(!is_string($guide[$key]??null)||trim($guide[$key])==='')$failures[]=$id.' missing '.$key;
    }
    if(!in_array($guide['permission']??null,['all','production','accounts','moderation','schedule'],true))$failures[]=$id.' unknown permission';
    if(!is_array($guide['steps']??null)||count($guide['steps'])<3)$failures[]=$id.' needs at least three steps';
    foreach(($guide['steps']??[]) as $step){if(!is_string($step)||trim($step)==='')$failures[]=$id.' blank step';}
    $route=(string)($guide['route']??'');
    if(!preg_match('~^/[a-z0-9/?#=&%-]+$~i',$route))$failures[]=$id.' invalid relative route: '.$route;
    $categories[$guide['category']??'']=true;
}
if(count($categories)<5)$failures[]='Expected a broad set of guide categories';
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}
echo count($guides).' Help guides across '.count($categories).' categories validated.'.PHP_EOL;
