<?php
declare(strict_types=1);
require_once __DIR__.'/my-theatre-personas.php';
require_once dirname(__DIR__,2).'/src/CommerceInventoryService.php';
function commerceFixture(PDO $db):array
{
    $f=myTheatreFixture($db);putenv('COMMERCE_SANDBOX_ENABLED=1');putenv('COMMERCE_ORGANIZATION_KEY=commerce-'.$f['suffix']);$u=['id'=>$f['users']['Admin'],'roles'=>['administrator']];$o=CommerceAccess::bootstrap($db,$u);CommerceStore::configure($db,$u,$o,['ticketing_enabled'=>1]);$v=CommerceVenueService::venue($db,$u,$o,'Synthetic commerce venue','Local testing only');$space=CommerceVenueService::space($db,$u,$o,$v,'Test auditorium');$q=$db->prepare('SELECT * FROM commerce_layouts WHERE space_id=?');$q->execute([$space]);$l=$q->fetch();$doc=json_decode($l['document_json'],true);for($i=1;$i<=20;$i++)$doc['items'][]=['key'=>'a'.$i,'kind'=>'seat','label'=>(string)$i,'x'=>50+$i*45,'y'=>140,'row'=>'a','section'=>'main','zone'=>'standard','wheelchair'=>$i===1,'companion'=>$i===2?'a1':'','description'=>$i===1?'Level entry wheelchair position':''];CommerceVenueService::save($db,$u,$o,(int)$l['id'],$doc,hash('sha256',$l['document_json']));CommerceVenueService::publish($db,$u,$o,(int)$l['id']);$prices=['standard'=>['Adult'=>2000,'Student'=>1200]];$perf=CommerceInventoryService::create($db,$u,$o,$f['productions']['A'],(int)$l['id'],date('Y-m-d\TH:i',time()+86400),$prices);$perf2=CommerceInventoryService::create($db,$u,$o,$f['productions']['B'],(int)$l['id'],date('Y-m-d\TH:i',time()+172800),$prices);foreach([$perf,$perf2] as $id)CommerceInventoryService::configure($db,$u,$o,$id,$prices,'open',true,100,600,50);
    $tester=['id'=>$f['users']['Member'],'roles'=>['member']];CommerceStore::grant($db,$u,$o,(int)$tester['id'],'tester',true);
    return $f+['admin'=>$u,'tester'=>$tester,'org'=>$o,'layout'=>(int)$l['id'],'performance'=>$perf,'performance2'=>$perf2,'prices'=>$prices];
}
function commerceHold(PDO $db,array $f,array $selection,?string $key=null,?string $token=null):array{return CommerceInventoryService::hold($db,$f['admin'],$f['org'],$f['performance'],$selection,'Synthetic Buyer','buyer@commerce.invalid','',$key??bin2hex(random_bytes(16)),$token??str_repeat('a',64));}
