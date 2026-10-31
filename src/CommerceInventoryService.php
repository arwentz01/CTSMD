<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceVenueService.php';
require_once __DIR__.'/ProductionLifecycleGuard.php';
final class CommerceInventoryService
{
    public static function prices(array $prices,array $seats):array
    {
        if(!$prices||count($prices)>100)throw new RuntimeException('Provide prices for each seat zone.');
        foreach($prices as $zone=>$categories){if(!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',(string)$zone)||!is_array($categories)||!$categories||count($categories)>12)throw new RuntimeException('Invalid pricing zone.');foreach($categories as $category=>$minor)if(!preg_match('/^[a-zA-Z0-9 _-]{1,60}$/',(string)$category)||!is_int($minor)||$minor<0||$minor>1000000)throw new RuntimeException('Prices use integer minor units, between 0 and 1000000.');}
        foreach($seats as $seat)if(!isset($prices[$seat['zone']]))throw new RuntimeException('Every seat zone needs a price.');return$prices;
    }
    public static function create(PDO $db,array $u,int $org,int $production,int $layout,string $starts,array $prices):int
    {
        CommerceAccess::demand($db,$u,$org,'inventory');$owner=CommerceStore::organization($db,$org,true);
        // The rest of Connect has no organization ownership model. Never infer a foreign production owner.
        if($owner['organization_key']!==CommerceAccess::primaryKey())throw new RuntimeException('Production linking is limited to this installation owner until backend tenant isolation exists.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$starts);if(!$date||$date->format('Y-m-d\TH:i')!==$starts)throw new RuntimeException('Enter a valid performance date and time.');
        return CommerceStore::tx($db,function()use($db,$u,$org,$production,$layout,$date,$prices,$owner){
            ProductionLifecycleGuard::assertWritable($db,$production,true);$l=CommerceStore::row($db,'layouts',$org,$layout,true);if($l['status']!=='published')throw new RuntimeException('Choose a published seating layout.');$doc=json_decode($l['document_json'],true,flags:JSON_THROW_ON_ERROR);$seats=array_values(array_filter($doc['items'],fn($i)=>$i['kind']==='seat'));self::prices($prices,$seats);
            $db->prepare('INSERT INTO commerce_performances(organization_id,production_id,layout_id,starts_at,prices_json,currency) VALUES(?,?,?,?,?,?)')->execute([$org,$production,$layout,$date->format('Y-m-d H:i:s'),json_encode($prices),$owner['currency']]);$id=(int)$db->lastInsertId();
            foreach($seats as $s){$s['section_name']=self::name($doc['sections'],$s['section']);$s['row_name']=self::name($doc['rows'],$s['row']);$db->prepare('INSERT INTO commerce_inventory(organization_id,performance_id,seat_key,seat_json) VALUES(?,?,?,?)')->execute([$org,$id,$s['key'],json_encode($s)]);}
            CommerceStore::audit($db,$org,(int)$u['id'],'commerce.performance_created','performance',$id,['layout'=>$layout,'seats'=>count($seats)]);return$id;
        });
    }
    private static function name(array $items,string $key):string{foreach($items as $i)if($i['key']===$key)return$i['name'];throw new RuntimeException('Missing layout label.');}
    public static function inventory(PDO $db,int $org,int $performance):array
    {$q=$db->prepare('SELECT * FROM commerce_inventory WHERE organization_id=? AND performance_id=? ORDER BY seat_key');$q->execute([$org,$performance]);return$q->fetchAll();}
    public static function configure(PDO $db,array $u,int $org,int $id,array $prices,string $sales,bool $checkin,int $fee,int $tax,int $discount):void
    {
        CommerceAccess::demand($db,$u,$org,'inventory');if(!in_array($sales,['open','closed'],true)||$fee<0||$fee>100000||$tax<0||$tax>10000||$discount<0||$discount>1000000)throw new RuntimeException('Check sales status, fees, tax and discount amounts.');
        CommerceStore::tx($db,function()use($db,$u,$org,$id,$prices,$sales,$checkin,$fee,$tax,$discount){$p=CommerceStore::row($db,'performances',$org,$id,true);ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);self::prices($prices,array_map(fn($i)=>json_decode($i['seat_json'],true),self::inventory($db,$org,$id)));$db->prepare('UPDATE commerce_performances SET prices_json=?,sales_status=?,checkin_enabled=?,fee_minor=?,tax_basis_points=?,discount_minor=? WHERE id=? AND organization_id=?')->execute([json_encode($prices),$sales,$checkin?1:0,$fee,$tax,$discount,$id,$org]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.performance_configured','performance',$id,['sales'=>$sales,'checkin'=>$checkin]);});
    }
    public static function allocate(PDO $db,array $u,int $org,int $id,array $keys,string $state,string $reason):void
    {
        CommerceAccess::demand($db,$u,$org,'inventory');if(!in_array($state,['available','blocked','house','complimentary','released'],true)||!$keys||count($keys)>1500||mb_strlen($reason)>500||($state!=='available'&&$state!=='released'&&trim($reason)===''))throw new RuntimeException('Choose seats, an allocation and a reason for restricted inventory.');
        CommerceStore::tx($db,function()use($db,$u,$org,$id,$keys,$state,$reason){$p=CommerceStore::row($db,'performances',$org,$id,true);ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);self::expireLocked($db,$org,$id,(int)$u['id']);$inventory=array_column(self::inventory($db,$org,$id),null,'seat_key');foreach($keys as $key)if(!isset($inventory[$key])||in_array($inventory[$key]['state'],['held','sold'],true))throw new RuntimeException('Held or sold seats cannot be overridden.');foreach(array_unique($keys) as $key)$db->prepare('UPDATE commerce_inventory SET state=?,reason=?,order_id=NULL WHERE organization_id=? AND performance_id=? AND seat_key=?')->execute([$state,$reason,$org,$id,$key]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.inventory_allocated','performance',$id,['seats'=>$keys,'state'=>$state,'reason'=>$reason]);});
    }
    /** Caller holds the performance row lock. Pending/exception payments are deliberately excluded. */
    public static function expireLocked(PDO $db,int $org,int $performance,int $actor):void
    {
        $q=$db->prepare("SELECT id FROM commerce_orders WHERE organization_id=? AND performance_id=? AND state='held' AND expires_at<=NOW() FOR UPDATE");$q->execute([$org,$performance]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){$db->prepare("UPDATE commerce_inventory SET state='released',order_id=NULL WHERE organization_id=? AND performance_id=? AND order_id=? AND state='held'")->execute([$org,$performance,$id]);$db->prepare("UPDATE commerce_orders SET state='canceled' WHERE id=? AND organization_id=?")->execute([$id,$org]);CommerceStore::audit($db,$org,$actor,'commerce.hold_expired','order',(int)$id);}
    }
    public static function availability(PDO $db,array $u,int $org,int $performance):array
    {
        CommerceAccess::demand($db,$u,$org,'test');CommerceStore::organization($db,$org,true);
        return CommerceStore::tx($db,function()use($db,$u,$org,$performance){CommerceStore::row($db,'performances',$org,$performance,true);self::expireLocked($db,$org,$performance,(int)$u['id']);return self::inventory($db,$org,$performance);});
    }
    public static function hold(PDO $db,array $u,int $org,int $performance,array $selection,string $name,string $email,string $phone,string $key,string $token):array
    {
        CommerceAccess::demand($db,$u,$org,'test');$owner=CommerceStore::organization($db,$org,true);
        $name=trim($name);$email=strtolower(trim($email));if($name===''||mb_strlen($name)>190||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||!str_ends_with($email,'.invalid')||mb_strlen($phone)>40)throw new RuntimeException('Use a purchaser name and synthetic .invalid email. Phone is optional. No email will be sent.');
        if(!preg_match('/^[a-zA-Z0-9_-]{16,100}$/',$key)||!preg_match('/^[a-f0-9]{64}$/',$token)||count($selection)<1||count($selection)>12)throw new RuntimeException('Choose between 1 and 12 seats and retry with a valid checkout token.');
        $normalized=[];foreach($selection as $seat=>$category){if(!is_string($seat)||!is_string($category))throw new RuntimeException('Invalid seat selection.');$normalized[$seat]=$category;}ksort($normalized);
        $requestHash=hash('sha256',json_encode([$performance,$normalized,$name,$email,$phone],JSON_THROW_ON_ERROR));
        return CommerceStore::tx($db,function()use($db,$u,$org,$performance,$normalized,$name,$email,$phone,$key,$token,$owner,$requestHash){
            $p=CommerceStore::row($db,'performances',$org,$performance,true);self::expireLocked($db,$org,$performance,(int)$u['id']);
            $q=$db->prepare('SELECT * FROM commerce_orders WHERE organization_id=? AND tester_user_id=? AND idempotency_key=? FOR UPDATE');$q->execute([$org,$u['id'],$key]);if($prior=$q->fetch()){if(!hash_equals($prior['request_hash'],$requestHash)||!hash_equals($prior['ownership_hash'],hash('sha256',$token)))throw new RuntimeException('Checkout retry does not match its original selection.');return$prior;}
            ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);if($p['sales_status']!=='open')throw new RuntimeException('Sales are closed for this performance.');$inventory=array_column(self::inventory($db,$org,$performance),null,'seat_key');$prices=json_decode($p['prices_json'],true);$lines=[];$subtotal=0;
            foreach($normalized as $seat=>$category){$i=$inventory[$seat]??null;if(!$i||!in_array($i['state'],['available','released'],true))throw new RuntimeException('A selected seat is no longer available. No seats were reserved; review the updated map.');$s=json_decode($i['seat_json'],true);$price=$prices[$s['zone']][$category]??null;if(!is_int($price))throw new RuntimeException('Choose an available ticket category.');$subtotal+=$price;$lines[]=[$seat,$category,$price,$i['seat_json'],$s['section_name'].' / '.$s['row_name'].' / '.$s['label']];}
            $discount=min($subtotal,(int)$p['discount_minor']);$tax=intdiv(($subtotal-$discount)*(int)$p['tax_basis_points']+5000,10000);$total=$subtotal-$discount+$tax+(int)$p['fee_minor'];
            $db->prepare("INSERT INTO commerce_orders(organization_id,performance_id,tester_user_id,public_reference,ownership_hash,idempotency_key,request_hash,state,expires_at,customer_name,customer_email,customer_phone,currency,subtotal_minor,discount_minor,tax_minor,fee_minor,total_minor) VALUES(?,?,?,?,?,?,?,'held',DATE_ADD(NOW(),INTERVAL ? SECOND),?,?,?,?,?,?,?,?,?)")->execute([$org,$performance,$u['id'],bin2hex(random_bytes(16)),hash('sha256',$token),$key,$requestHash,(int)$owner['hold_seconds'],$name,$email,$phone?:null,$p['currency'],$subtotal,$discount,$tax,$p['fee_minor'],$total]);$id=(int)$db->lastInsertId();
            foreach($lines as [$seat,$category,$price,$json,$desc]){$db->prepare('INSERT INTO commerce_order_lines(organization_id,order_id,seat_key,category,description,unit_minor,seat_json) VALUES(?,?,?,?,?,?,?)')->execute([$org,$id,$seat,$category,$desc,$price,$json]);$db->prepare("UPDATE commerce_inventory SET state='held',order_id=?,reason=NULL WHERE organization_id=? AND performance_id=? AND seat_key=?")->execute([$id,$org,$performance,$seat]);}
            CommerceStore::audit($db,$org,(int)$u['id'],'commerce.hold_created','order',$id,['seats'=>array_keys($normalized),'total_minor'=>$total]);return CommerceStore::row($db,'orders',$org,$id);
        });
    }
    public static function own(PDO $db,array $u,int $org,int $id,string $token):array
    {
        CommerceAccess::demand($db,$u,$org,'test');$o=CommerceStore::row($db,'orders',$org,$id);if((int)$o['tester_user_id']!==(int)$u['id']||!hash_equals($o['ownership_hash'],hash('sha256',$token)))throw new RuntimeException('Order is unavailable for this checkout.');return$o;
    }
}
