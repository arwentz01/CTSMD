<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
require_once __DIR__.'/CommercePaymentProvider.php';
require_once __DIR__.'/CommerceFulfillmentService.php';
final class CommercePaymentService
{
    public static function start(PDO $db,array $u,int $org,int $order,string $token,string $scenario):array
    {
        $o=CommerceInventoryService::own($db,$u,$org,$order,$token);CommerceStore::organization($db,$org,true);
        if(!in_array($scenario,['success','declined','canceled','delayed','timeout'],true))throw new RuntimeException('Choose a supported simulation.');
        // Commit abandoned-hold expiration independently of a rejected payment request.
        CommerceInventoryService::availability($db,$u,$org,(int)$o['performance_id']);
        return CommerceStore::tx($db,function()use($db,$u,$org,$order,$token,$scenario,$o){CommerceStore::row($db,'performances',$org,(int)$o['performance_id'],true);$o=CommerceInventoryService::own($db,$u,$org,$order,$token);$q=$db->prepare('SELECT * FROM commerce_payment_attempts WHERE organization_id=? AND order_id=?');$q->execute([$org,$order]);if($a=$q->fetch())return$a;
            if($o['state']!=='held')throw new RuntimeException('This order no longer has a payable seat hold. Select seats again.');$q=$db->prepare('SELECT expires_at>NOW() FROM commerce_orders WHERE id=? AND organization_id=?');$q->execute([$order,$org]);if(!$q->fetchColumn())throw new RuntimeException('Your hold expired. Select seats again.');ProductionLifecycleGuard::assertWritable($db,(int)CommerceStore::row($db,'performances',$org,(int)$o['performance_id'])['production_id'],true);
            $db->prepare("INSERT INTO commerce_payment_attempts(organization_id,order_id,provider_reference,scenario,ready_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND))")->execute([$org,$order,bin2hex(random_bytes(16)),$scenario,$scenario==='delayed'?15:0]);$id=(int)$db->lastInsertId();$db->prepare("UPDATE commerce_orders SET state='payment_pending' WHERE organization_id=? AND id=? AND state='held'")->execute([$org,$order]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.payment_started','order',$order,['attempt'=>$id,'scenario'=>$scenario]);return CommerceStore::row($db,'payment_attempts',$org,$id);
        });
    }
    public static function refresh(PDO $db,array $u,int $org,int $order,?string $token=null):array
    {
        if($token!==null)$o=CommerceInventoryService::own($db,$u,$org,$order,$token);else{CommerceAccess::demand($db,$u,$org,'report');$o=CommerceStore::row($db,'orders',$org,$order);}
        return CommerceStore::tx($db,function()use($db,$u,$org,$order,$o){CommerceStore::row($db,'performances',$org,(int)$o['performance_id'],true);$o=CommerceStore::row($db,'orders',$org,$order,true);if(!in_array($o['state'],['payment_pending','exception'],true))return$o;
            $q=$db->prepare('SELECT * FROM commerce_payment_attempts WHERE organization_id=? AND order_id=? FOR UPDATE');$q->execute([$org,$order]);$a=$q->fetch();if(!$a)throw new RuntimeException('Payment attempt missing; reconciliation required.');$q=$db->prepare('SELECT outcome FROM commerce_simulated_results WHERE organization_id=? AND attempt_id=?');$q->execute([$org,$a['id']]);$a['simulated_resolution']=$q->fetchColumn()?:null;$now=new DateTimeImmutable((string)$db->query('SELECT NOW()')->fetchColumn());$status=(new CommerceSimulatedPaymentProvider())->inspect($a,$now);if($status==='pending')return$o;
            $event='simulated-'.$a['provider_reference'].'-'.$status;$db->prepare('INSERT IGNORE INTO commerce_payment_events(organization_id,attempt_id,event_key,outcome) VALUES(?,?,?,?)')->execute([$org,$a['id'],$event,$status]);$db->prepare('UPDATE commerce_payment_attempts SET state=? WHERE organization_id=? AND id=?')->execute([$status,$org,$a['id']]);
            if($status==='unknown'){$state='exception';}
            elseif($status==='succeeded'){
                CommerceFulfillmentService::issueLocked($db,$org,$o);$state='paid';
            }else{$state=$status==='canceled'?'canceled':'failed';$db->prepare("UPDATE commerce_inventory SET state='released',order_id=NULL WHERE organization_id=? AND performance_id=? AND order_id=? AND state='held'")->execute([$org,$o['performance_id'],$order]);}
            $db->prepare('UPDATE commerce_orders SET state=? WHERE organization_id=? AND id=?')->execute([$state,$org,$order]);if($o['state']!==$state)CommerceStore::audit($db,$org,(int)$u['id'],'commerce.payment_'.$state,'order',$order,['attempt'=>$a['id'],'provider'=>'simulated']);return CommerceStore::row($db,'orders',$org,$order);
        });
    }
    /** Explicit local provider evidence, never a customer/browser-return status. */
    public static function resolveSimulation(PDO $db,array $u,int $org,int $order,string $outcome,string $reason):void
    {
        CommerceAccess::demand($db,$u,$org,'configure');if(!in_array($outcome,['succeeded','failed','canceled'],true)||trim($reason)===''||mb_strlen($reason)>500)throw new RuntimeException('Choose simulator evidence and record a reason.');$o=CommerceStore::row($db,'orders',$org,$order);
        CommerceStore::tx($db,function()use($db,$u,$org,$order,$outcome,$reason,$o){CommerceStore::row($db,'performances',$org,(int)$o['performance_id'],true);$o=CommerceStore::row($db,'orders',$org,$order,true);if(!in_array($o['state'],['exception','payment_pending'],true))throw new RuntimeException('Only unresolved simulation attempts can be reconciled.');$q=$db->prepare("SELECT * FROM commerce_payment_attempts WHERE organization_id=? AND order_id=? AND scenario='timeout' FOR UPDATE");$q->execute([$org,$order]);$a=$q->fetch();if(!$a)throw new RuntimeException('This is not an uncertain timeout simulation.');$q=$db->prepare('SELECT outcome FROM commerce_simulated_results WHERE attempt_id=? AND organization_id=?');$q->execute([$a['id'],$org]);$prior=$q->fetchColumn();if($prior){if($prior!==$outcome)throw new RuntimeException('Simulator evidence cannot be changed.');return;}$db->prepare('INSERT INTO commerce_simulated_results(organization_id,attempt_id,outcome,actor_user_id,reason) VALUES(?,?,?,?,?)')->execute([$org,$a['id'],$outcome,$u['id'],$reason]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.simulator_evidence_recorded','order',$order,['outcome'=>$outcome,'reason'=>$reason]);});
    }
    public static function cancelHeld(PDO $db,array $u,int $org,int $order,string $token):void
    {
        $o=CommerceInventoryService::own($db,$u,$org,$order,$token);CommerceStore::tx($db,function()use($db,$u,$org,$order,$o){CommerceStore::row($db,'performances',$org,(int)$o['performance_id'],true);$o=CommerceStore::row($db,'orders',$org,$order,true);if($o['state']==='canceled')return;if($o['state']!=='held')throw new RuntimeException('Pending payments require provider reconciliation before seats can be released.');$db->prepare("UPDATE commerce_orders SET state='canceled' WHERE organization_id=? AND id=?")->execute([$org,$order]);$db->prepare("UPDATE commerce_inventory SET state='released',order_id=NULL WHERE organization_id=? AND performance_id=? AND order_id=? AND state='held'")->execute([$org,$o['performance_id'],$order]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.checkout_canceled','order',$order);});
    }
    public static function expireSimulation(PDO $db,array $u,int $org,int $order,string $token):void
    {
        $o=CommerceInventoryService::own($db,$u,$org,$order,$token);
        CommerceStore::tx($db,function()use($db,$u,$org,$order,$o){CommerceStore::row($db,'performances',$org,(int)$o['performance_id'],true);$o=CommerceStore::row($db,'orders',$org,$order,true);if($o['state']!=='held')throw new RuntimeException('Only an unpaid held checkout can simulate expiry.');$db->prepare('UPDATE commerce_orders SET expires_at=NOW() WHERE organization_id=? AND id=?')->execute([$org,$order]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.simulated_expiry','order',$order);CommerceInventoryService::expireLocked($db,$org,(int)$o['performance_id'],(int)$u['id']);});
    }
    public static function refund(PDO $db,array $u,int $org,int $order,string $reason):void
    {
        CommerceAccess::demand($db,$u,$org,'refund');if(trim($reason)===''||mb_strlen($reason)>500)throw new RuntimeException('A refund reason is required.');$o=CommerceStore::row($db,'orders',$org,$order);
        CommerceStore::tx($db,function()use($db,$u,$org,$order,$reason,$o){CommerceStore::row($db,'performances',$org,(int)$o['performance_id'],true);$o=CommerceStore::row($db,'orders',$org,$order,true);if($o['state']==='refunded')return;if($o['state']!=='paid')throw new RuntimeException('Only paid sandbox orders can be refunded.');$q=$db->prepare('SELECT 1 FROM commerce_tickets WHERE organization_id=? AND order_id=? AND checked_in_at IS NOT NULL LIMIT 1');$q->execute([$org,$order]);if($q->fetchColumn())throw new RuntimeException('Reverse admissions before refunding this order.');$db->prepare("UPDATE commerce_tickets SET state='refunded' WHERE organization_id=? AND order_id=?")->execute([$org,$order]);$db->prepare("UPDATE commerce_inventory SET state='released',order_id=NULL WHERE organization_id=? AND performance_id=? AND order_id=? AND state='sold'")->execute([$org,$o['performance_id'],$order]);$db->prepare("UPDATE commerce_orders SET state='refunded' WHERE organization_id=? AND id=?")->execute([$org,$order]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.simulated_refund','order',$order,['reason'=>$reason,'total_minor'=>(int)$o['total_minor']]);});
    }
}
