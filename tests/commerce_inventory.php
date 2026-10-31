<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/commerce.php';
$db=Database::connect(dirname(__DIR__));$f=null;$n=0;
$ok=static function(bool $v,string $label)use(&$n){if(!$v)throw new RuntimeException($label);++$n;echo "PASS $label\n";};
$reject=static function(callable $fn,string $label)use($ok){try{$fn();}catch(RuntimeException $e){$ok(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);};
try{$f=commerceFixture($db);$o=$f['org'];$u=$f['admin'];$p=$f['performance'];
$a=commerceHold($db,$f,['a1'=>'Adult','a2'=>'Student']);$ok((int)$a['subtotal_minor']===3200&&(int)$a['total_minor']===3439,'Server prices, discount, rounded tax and fee authoritative');
$reject(fn()=>commerceHold($db,$f,['a2'=>'Adult','a3'=>'Adult']),'Partial selection rejected atomically');$items=array_column(CommerceInventoryService::inventory($db,$o,$p),null,'seat_key');$ok($items['a3']['state']==='available','No partial hold left behind');
CommerceInventoryService::allocate($db,$u,$o,$p,['a4'],'blocked','Obstructed maintenance');CommerceInventoryService::allocate($db,$u,$o,$p,['a5'],'house','House allocation');foreach(['a4','a5'] as $seat)$reject(fn()=>commerceHold($db,$f,[$seat=>'Adult']),'Restricted inventory rejected '.$seat);
$reject(fn()=>CommerceInventoryService::allocate($db,$u,$o,$p,['a1'],'available','Attempted held-seat override'),'Held seats cannot be overridden');
$b=CommerceInventoryService::hold($db,$u,$o,$f['performance2'],['a1'=>'Adult'],'Test','test@commerce.invalid','',bin2hex(random_bytes(16)),str_repeat('b',64));$ok($b['performance_id']!=$a['performance_id'],'Performance inventory isolation');
$key=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$c=commerceHold($db,$f,['a6'=>'Adult'],$key,$token);$replay=commerceHold($db,$f,['a6'=>'Adult'],$key,$token);$ok($c['id']===$replay['id']&&$c['expires_at']===$replay['expires_at'],'Idempotent hold never extends expiry');$reject(fn()=>commerceHold($db,$f,['a7'=>'Adult'],$key,$token),'Idempotency payload mismatch rejected');$reject(fn()=>CommerceInventoryService::own($db,$u,$o,(int)$c['id'],'forged'),'Forged ownership token rejected');$reject(fn()=>CommerceInventoryService::own($db,$f['tester'],$o,(int)$c['id'],$token),'Other testing account cannot retrieve order');
$db->prepare('UPDATE commerce_orders SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$c['id']]);CommerceInventoryService::availability($db,$u,$o,$p);$expired=CommerceStore::row($db,'orders',$o,(int)$c['id']);$ok($expired['state']==='canceled','Abandoned hold expires on server');$again=commerceHold($db,$f,['a6'=>'Adult']);$ok($again['id']!==$c['id'],'Expired seat can be acquired again');
CommerceInventoryService::configure($db,$u,$o,$p,['standard'=>['Adult'=>9999,'Student'=>1200]],'open',true,300,0,0);$frozen=CommerceStore::row($db,'orders',$o,(int)$a['id']);$ok($frozen['total_minor']===$a['total_minor'],'Price changes do not rewrite existing orders');
$clone=CommerceVenueService::cloneVersion($db,$u,$o,$f['layout']);$ok((int)CommerceStore::row($db,'performances',$o,$p)['layout_id']===$f['layout'],'Template clone preserves performance layout snapshot');
$reject(fn()=>commerceHold($db,$f,['a8'=>'Forged -1']),'Browser cannot invent ticket prices');$reject(fn()=>CommerceInventoryService::create($db,$u,$o,$f['productions']['Archive'],$f['layout'],date('Y-m-d\TH:i'),$f['prices']),'Archived production cannot open new performance');
$ok((int)$db->query("SELECT COUNT(*) FROM commerce_audit WHERE organization_id=$o AND action='commerce.inventory_allocated'")->fetchColumn()===2,'Inventory changes audited');echo "PASS $n inventory checks\n";
}finally{if($f)retireMyTheatreFixture($db,$f);}
