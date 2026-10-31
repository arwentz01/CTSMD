<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/my-theatre-personas.php';
require_once dirname(__DIR__).'/src/CommerceVenueService.php';
$db=Database::connect(dirname(__DIR__));$f=null;$n=0;
$ok=static function(bool $v,string $label)use(&$n){if(!$v)throw new RuntimeException($label);$n++;echo "PASS $label\n";};
$reject=static function(callable $fn,string $label)use($ok){try{$fn();}catch(RuntimeException $e){$ok(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);};
try{
$f=myTheatreFixture($db);putenv('COMMERCE_SANDBOX_ENABLED=1');putenv('COMMERCE_ORGANIZATION_KEY=venue-'.$f['suffix']);$u=['id'=>$f['users']['Admin'],'roles'=>['administrator']];$o=CommerceAccess::bootstrap($db,$u);
$v=CommerceVenueService::venue($db,$u,$o,'Synthetic theatre','Test-only foyer');$space=CommerceVenueService::space($db,$u,$o,$v,'Irregular auditorium');$space2=CommerceVenueService::space($db,$u,$o,$v,'Small studio');$ok($space!==$space2,'Multiple spaces per venue');
$q=$db->prepare('SELECT * FROM commerce_layouts WHERE organization_id=? AND space_id=?');$q->execute([$o,$space]);$l=$q->fetch();$doc=json_decode($l['document_json'],true);$seat=['key'=>'s1','kind'=>'seat','label'=>'1','x'=>92,'y'=>132,'row'=>'a','section'=>'main','zone'=>'standard','wheelchair'=>true,'description'=>'Level entry position'];$doc['items'][]=$seat;$doc['items'][]=array_replace($seat,['key'=>'s2','label'=>'2','x'=>155,'y'=>150,'wheelchair'=>false,'companion'=>'s1']);$doc['items'][]=['key'=>'aisle','kind'=>'aisle','label'=>'Aisle','x'=>220,'y'=>145];
CommerceVenueService::save($db,$u,$o,(int)$l['id'],$doc,hash('sha256',$l['document_json']));$ok(true,'Arbitrary positions and accessible companion attributes saved');
$reject(fn()=>CommerceVenueService::save($db,$u,$o,(int)$l['id'],$doc,'stale'),'Concurrent draft update rejected');
$bad=$doc;$bad['items'][2]['companion']='missing';$reject(fn()=>CommerceVenueService::validate($bad),'Invalid companion reference rejected');$bad=$doc;$bad['items'][2]['label']='1';$reject(fn()=>CommerceVenueService::validate($bad),'Duplicate seat numbers rejected');$bad=$doc;$bad['items'][1]['x']=-3;$reject(fn()=>CommerceVenueService::validate($bad),'Invalid map coordinates rejected');
CommerceVenueService::publish($db,$u,$o,(int)$l['id']);$reject(fn()=>CommerceVenueService::save($db,$u,$o,(int)$l['id'],$doc,''),'Published editing rejected by service');$reject(fn()=>$db->prepare('DELETE FROM commerce_layouts WHERE id=?')->execute([$l['id']]),'Published deletion rejected by database');
$new=CommerceVenueService::cloneVersion($db,$u,$o,(int)$l['id']);$clone=CommerceStore::row($db,'layouts',$o,$new);$ok($clone['status']==='draft'&&(int)$clone['version_number']===2,'Clone preserves original and creates editable version');
$vol=['id'=>$f['users']['Volunteer'],'roles'=>['volunteer']];$reject(fn()=>CommerceVenueService::venue($db,$vol,$o,'No',''),'Unprivileged volunteer cannot create venue');
$other=$o+100000;$reject(fn()=>CommerceStore::row($db,'layouts',$other,(int)$l['id']),'Cross-owner layout unavailable');
$ok((int)$db->query("SELECT COUNT(*) FROM commerce_audit WHERE organization_id=$o")->fetchColumn()>=6,'Venue mutations audit logged');echo "PASS $n venue checks\n";
}finally{if($f)retireMyTheatreFixture($db,$f);}
