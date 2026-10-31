<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceFulfillmentService.php';
/** Explicit staff-authorized zero-value settlement; never a payment-provider result. */
final class CommerceComplimentaryService
{
    public static function issue(PDO $db,array $u,int $org,int $performance,array $seats,string $name,string $email,string $reason,string $key,string $token):array
    {
        CommerceAccess::demand($db,$u,$org,'complimentary');$owner=CommerceStore::organization($db,$org,true);$name=trim($name);$email=strtolower(trim($email));$reason=trim($reason);
        if($name===''||mb_strlen($name)>190||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||!str_ends_with($email,'.invalid'))throw new RuntimeException('Enter a recipient name and synthetic .invalid email. No email will be sent.');
        if($reason===''||mb_strlen($reason)>500)throw new RuntimeException('Record a complimentary-issuance reason of up to 500 characters.');
        if(count($seats)<1||count($seats)>12||!preg_match('/^[a-zA-Z0-9_-]{16,100}$/',$key)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('Choose 1–12 seats and reload the complimentary form if its session expired.');
        $keys=[];foreach($seats as $seat){if(!is_string($seat)||isset($keys[$seat]))throw new RuntimeException('Choose unique seats.');$keys[$seat]=true;}ksort($keys);$keys=array_keys($keys);
        $hash=hash('sha256',json_encode(['complimentary',$performance,$keys,$name,$email,$reason],JSON_THROW_ON_ERROR));
        return CommerceStore::tx($db,function()use($db,$u,$org,$performance,$keys,$name,$email,$reason,$key,$token,$hash,$owner){
            $p=CommerceStore::row($db,'performances',$org,$performance,true);CommerceInventoryService::expireLocked($db,$org,$performance,(int)$u['id']);
            $q=$db->prepare('SELECT * FROM commerce_orders WHERE organization_id=? AND tester_user_id=? AND idempotency_key=? FOR UPDATE');$q->execute([$org,$u['id'],$key]);if($prior=$q->fetch()){if(!hash_equals($prior['request_hash'],$hash)||!hash_equals($prior['ownership_hash'],hash('sha256',$token)))throw new RuntimeException('This issuance retry does not match its original request.');return$prior;}
            CommercePerformancePolicy::demand($db,$org,$p);$inventory=array_column(CommerceInventoryService::inventory($db,$org,$performance),null,'seat_key');
            foreach($keys as $seat)if(!isset($inventory[$seat])||!in_array($inventory[$seat]['state'],['available','released','complimentary'],true))throw new RuntimeException('A selected seat is held, sold, blocked or house reserved. No complimentary tickets were issued.');
            $db->prepare("INSERT INTO commerce_orders(organization_id,performance_id,tester_user_id,public_reference,ownership_hash,idempotency_key,request_hash,state,expires_at,customer_name,customer_email,currency,subtotal_minor,discount_minor,tax_minor,fee_minor,total_minor) VALUES(?,?,?,?,?,?,?,'held',NOW(),?,?,?,0,0,0,0,0)")->execute([$org,$performance,$u['id'],bin2hex(random_bytes(16)),hash('sha256',$token),$key,$hash,$name,$email,$owner['currency']]);$id=(int)$db->lastInsertId();
            foreach($keys as $seat){$json=$inventory[$seat]['seat_json'];$s=json_decode($json,true);$db->prepare("INSERT INTO commerce_order_lines(organization_id,order_id,seat_key,product_type,category,description,unit_minor,seat_json) VALUES(?,?,?,'complimentary_ticket','Complimentary',?,0,?)")->execute([$org,$id,$seat,$p['seating_mode']==='general_admission'?'General admission · '.$s['zone']:$s['section_name'].' / '.$s['row_name'].' / '.$s['label'],$json]);$db->prepare("UPDATE commerce_inventory SET state='held',order_id=?,reason=? WHERE organization_id=? AND performance_id=? AND seat_key=?")->execute([$id,$reason,$org,$performance,$seat]);}
            $order=CommerceStore::row($db,'orders',$org,$id);CommerceFulfillmentService::issueLocked($db,$org,$order);$db->prepare("UPDATE commerce_orders SET state='paid' WHERE organization_id=? AND id=?")->execute([$org,$id]);require_once __DIR__.'/CommerceDeliveryService.php';CommerceDeliveryService::enqueueLocked($db,$org,$id,(int)$u['id']);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.complimentary_issued','order',$id,['reason'=>$reason,'seats'=>$keys,'total_minor'=>0,'settlement'=>'complimentary']);return CommerceStore::row($db,'orders',$org,$id);
        });
    }
    public static function isOrder(PDO $db,int $org,int $order):bool
    {$q=$db->prepare("SELECT 1 FROM commerce_order_lines WHERE organization_id=? AND order_id=? AND product_type='complimentary_ticket' LIMIT 1");$q->execute([$org,$order]);return(bool)$q->fetchColumn();}
}
