<?php
declare(strict_types=1);
require_once __DIR__.'/CommercePaymentService.php';
/** Staff tender evidence shares the existing held -> paid lifecycle and fulfillment. */
final class CommerceBoxOfficeService
{
    public static function reserve(PDO $db,array $u,int $org,int $performance,array $seats,array $quantities,string $name,string $email,string $key,string $token):array
    {
        CommerceAccess::demand($db,$u,$org,'sell');
        $p=CommerceStore::row($db,'performances',$org,$performance);
        $order=$p['seating_mode']==='general_admission'?CommerceInventoryService::holdAdmissions($db,$u,$org,$performance,$quantities,trim($name)?:'Walk-in guest',trim($email)?:'walkin@commerce.invalid',$key,$token):CommerceInventoryService::holdQuantities($db,$u,$org,$performance,$seats,$quantities,trim($name)?:'Walk-in guest',trim($email)?:'walkin@commerce.invalid','',$key,$token);
        // Failed tagging leaves only an ordinary expiring hold, never a payment or issued ticket.
        $db->prepare('INSERT IGNORE INTO commerce_box_office_sales(organization_id,order_id,cashier_user_id) VALUES(?,?,?)')->execute([$org,$order['id'],$u['id']]);
        return $order;
    }
    public static function evidence(PDO $db,int $org,int $order):?array
    {
        $q=$db->prepare('SELECT * FROM commerce_box_office_sales WHERE organization_id=? AND order_id=?');$q->execute([$org,$order]);return $q->fetch()?:null;
    }
    public static function search(PDO $db,array $u,int $org,string $term,int $performance=0):array
    {
        CommerceAccess::demand($db,$u,$org,'sell');$term=trim($term);if(mb_strlen($term)>190)throw new RuntimeException('Use a search of up to 190 characters.');
        $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$term).'%';
        $q=$db->prepare("SELECT o.* FROM commerce_orders o WHERE o.organization_id=? AND (?=0 OR o.performance_id=?) AND (?='' OR o.public_reference=? OR CAST(o.id AS CHAR)=? OR o.customer_name LIKE ? ESCAPE '!' OR o.customer_email LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM commerce_order_lines l WHERE l.organization_id=o.organization_id AND l.order_id=o.id AND l.description LIKE ? ESCAPE '!')) ORDER BY o.id DESC LIMIT 50");$q->execute([$org,$performance,$performance,$term,$term,$term,$like,$like,$like]);return $q->fetchAll();
    }
    public static function settle(PDO $db,array $u,int $org,int $order,string $token,string $method,int $cash,string $provider,string $reference,bool $confirmed):array
    {
        CommerceAccess::demand($db,$u,$org,'sell');CommerceStore::organization($db,$org,true);
        if(!$confirmed||!in_array($method,['cash','external_terminal'],true))throw new RuntimeException('Confirm receipt and choose cash or an external terminal.');
        $provider=trim($provider);$reference=trim($reference);
        if($method==='cash'&&($cash<0||$cash>100000000))throw new RuntimeException('Enter a valid cash amount in minor currency units.');
        if($method==='external_terminal'&&($provider===''||mb_strlen($provider)>100||$reference===''||mb_strlen($reference)>190))throw new RuntimeException('Enter the terminal provider and payment reference. Never enter card numbers.');
        if($method==='cash'){$provider='';$reference='';}else $cash=0;
        $hash=hash('sha256',json_encode([$method,$cash,$provider,$reference],JSON_THROW_ON_ERROR));
        $o=CommerceInventoryService::own($db,$u,$org,$order,$token);
        CommerceInventoryService::availability($db,$u,$org,(int)$o['performance_id']);
        return CommerceStore::tx($db,function()use($db,$u,$org,$order,$token,$method,$cash,$provider,$reference,$hash,$o){
            $p=CommerceStore::row($db,'performances',$org,(int)$o['performance_id'],true);
            $o=CommerceInventoryService::own($db,$u,$org,$order,$token);
            $q=$db->prepare('SELECT * FROM commerce_box_office_sales WHERE organization_id=? AND order_id=? FOR UPDATE');$q->execute([$org,$order]);$sale=$q->fetch();
            if(!$sale||(int)$sale['cashier_user_id']!==(int)$u['id'])throw new RuntimeException('This is not your Box Office reservation.');
            if($sale['evidence_hash']){if(!hash_equals($sale['evidence_hash'],$hash))throw new RuntimeException('Recorded tender cannot be changed by a retry.');return $o;}
            if($o['state']!=='held')throw new RuntimeException('Only a current unpaid reservation can be completed.');
            $q=$db->prepare('SELECT expires_at>NOW() FROM commerce_orders WHERE organization_id=? AND id=?');$q->execute([$org,$order]);if(!$q->fetchColumn())throw new RuntimeException('The reservation expired. No tickets were issued; review payment with the customer.');
            ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);
            $q=$db->prepare('SELECT 1 FROM commerce_payment_attempts WHERE organization_id=? AND order_id=?');$q->execute([$org,$order]);if($q->fetchColumn())throw new RuntimeException('A provider attempt already exists. Reconcile it instead.');
            if($method==='cash'&&$cash<(int)$o['total_minor'])throw new RuntimeException('Cash received must cover the total. No tickets were issued.');
            $change=$method==='cash'?$cash-(int)$o['total_minor']:0;
            CommerceFulfillmentService::issueLocked($db,$org,$o);
            $db->prepare("UPDATE commerce_orders SET state='paid' WHERE organization_id=? AND id=? AND state='held'")->execute([$org,$order]);
            $db->prepare('UPDATE commerce_box_office_sales SET method=?,cash_received_minor=?,change_due_minor=?,terminal_provider=?,terminal_reference=?,evidence_hash=?,confirmed_at=NOW() WHERE organization_id=? AND order_id=?')->execute([$method,$method==='cash'?$cash:null,$method==='cash'?$change:null,$provider?:null,$reference?:null,$hash,$org,$order]);
            CommerceStore::audit($db,$org,(int)$u['id'],'commerce.staff_tender_confirmed','order',$order,['method'=>$method,'verification'=>'staff_attested_not_api_verified','total_minor'=>(int)$o['total_minor'],'cash_received_minor'=>$method==='cash'?$cash:null,'change_due_minor'=>$change,'provider'=>$provider,'reference'=>$reference]);
            return CommerceStore::row($db,'orders',$org,$order);
        });
    }
}
