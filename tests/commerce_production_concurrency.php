<?php
declare(strict_types=1);
require_once __DIR__.'/commerce_concurrency.php';
require_once dirname(__DIR__).'/src/CommercePricingService.php';
$db=Database::connect(dirname(__DIR__));$f=null;
try{
 $f=commerceFixture($db);$f['pricing_key']=bin2hex(random_bytes(16));$f['pricing_review']=CommercePricingService::preview($db,$f['admin'],$f['org'],$f['productions']['A'],'override',[$f['performance']],['standard'=>['Adult'=>2500]],'Synthetic concurrent pricing review',true);
 $result=commerceRace($f,'pricing_purchase');if(!in_array('WIN',$result,true))throw new RuntimeException('No transaction completed.');
 $q=$db->prepare('SELECT o.subtotal_minor,l.unit_minor FROM commerce_orders o JOIN commerce_order_lines l ON l.order_id=o.id AND l.organization_id=o.organization_id WHERE o.organization_id=? AND o.performance_id=?');$q->execute([$f['org'],$f['performance']]);$orders=$q->fetchAll();if(count($orders)>1)throw new RuntimeException('Duplicate purchase.');
 foreach($orders as $order)if(!in_array((int)$order['unit_minor'],[2000,2500],true)||(int)$order['subtotal_minor']!==(int)$order['unit_minor'])throw new RuntimeException('Torn pricing snapshot.');
 $p=CommerceStore::row($db,'performances',$f['org'],$f['performance']);$prices=json_decode($p['prices_json'],true);if(!in_array($prices['standard']['Adult'],[2000,2500],true))throw new RuntimeException('Partial pricing update.');
 $q=$db->prepare("SELECT COUNT(*) FROM commerce_inventory WHERE organization_id=? AND performance_id=? AND state='held'");$q->execute([$f['org'],$f['performance']]);if((int)$q->fetchColumn()!==count($orders))throw new RuntimeException('Partial inventory mutation.');
 echo 'PASS Concurrent pricing/purchase: atomic review or safe rollback, coherent old/new quote, no duplicate or partial hold ('.implode('/',$result).")\n";
}finally{if($f)retireMyTheatreFixture($db,$f);}
