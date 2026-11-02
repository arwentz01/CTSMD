<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceStorefrontService.php';

/** Cart-owned inventory holds; checkout integration must remain disabled until completed. */
final class CommerceShoppingCartService
{
    private const HOLD_SECONDS=900;

    public static function current(PDO $db,array $user,int $org):array
    {
        CommerceAccess::demand($db,$user,$org,'test');CommerceStore::organization($db,$org,true);
        return CommerceStore::tx($db,function()use($db,$user,$org){
            // Serialize cart creation across sessions belonging to the same account.
            $q=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);if(!$q->fetchColumn())throw new RuntimeException('Account unavailable.');
            $q=$db->prepare("SELECT * FROM commerce_shopping_carts WHERE organization_id=? AND owner_user_id=? AND state='open' ORDER BY id DESC LIMIT 1 FOR UPDATE");$q->execute([$org,$user['id']]);if($cart=$q->fetch())return$cart;
            $q=$db->prepare('INSERT INTO commerce_shopping_carts(organization_id,owner_user_id,public_reference) VALUES(?,?,?)');$q->execute([$org,$user['id'],bin2hex(random_bytes(16))]);
            return self::owned($db,$user,$org,(int)$db->lastInsertId());
        });
    }

    /** Worker-side cleanup for abandoned carts, including when nobody opens a seat map. */
    public static function expireAbandoned(PDO $db,int $org,int $limit=50):int
    {
        $limit=max(1,min(100,$limit));$q=$db->prepare("SELECT DISTINCT i.performance_id,c.owner_user_id FROM commerce_shopping_cart_items i JOIN commerce_shopping_carts c ON c.id=i.cart_id AND c.organization_id=i.organization_id WHERE i.organization_id=? AND i.state='held' AND c.state='open' AND c.expires_at_utc<=UTC_TIMESTAMP() ORDER BY i.performance_id LIMIT ".$limit);$q->execute([$org]);$rows=$q->fetchAll();$count=0;foreach($rows as $row){CommerceStore::tx($db,function()use($db,$org,$row){CommerceStore::row($db,'performances',$org,(int)$row['performance_id'],true);CommerceInventoryService::expireLocked($db,$org,(int)$row['performance_id'],(int)$row['owner_user_id']);});++$count;}return$count;
    }

    private static function owned(PDO $db,array $user,int $org,int $cart,bool $lock=false):array
    {
        $q=$db->prepare('SELECT *, (expires_at_utc IS NULL OR expires_at_utc>UTC_TIMESTAMP()) AS active FROM commerce_shopping_carts WHERE id=? AND organization_id=? AND owner_user_id=?'.($lock?' FOR UPDATE':''));$q->execute([$cart,$org,$user['id']]);
        return$q->fetch()?:throw new RuntimeException('Cart unavailable.');
    }

    public static function contents(PDO $db,array $user,int $org,int $cart):array
    {
        CommerceAccess::demand($db,$user,$org,'test');$owner=self::owned($db,$user,$org,$cart);
        $q=$db->prepare('SELECT i.*,p.title,c.starts_at,c.seating_mode FROM commerce_shopping_cart_items i JOIN commerce_performances c ON c.id=i.performance_id AND c.organization_id=i.organization_id JOIN productions p ON p.id=c.production_id WHERE i.organization_id=? AND i.cart_id=? AND i.state<>\'removed\' ORDER BY c.starts_at,i.id');$q->execute([$org,$cart]);
        return['cart'=>$owner,'items'=>$q->fetchAll()];
    }

    /** Add seats without replacing any existing selection; retrying identical seats is harmless. */
    public static function add(PDO $db,array $user,int $org,int $cart,int $performance,array $selection,string $requestKey=''):void
    {
        CommerceAccess::demand($db,$user,$org,'test');CommerceStorefrontService::demandPerformance($db,$user,$org,$performance);
        if(!$selection||count($selection)>12)throw new RuntimeException('Choose between 1 and 12 tickets.');
        foreach($selection as $seat=>$category)if(!is_string($seat)||!is_string($category)||$seat===''||$category==='')throw new RuntimeException('Choose a ticket type for each seat.');
        ksort($selection,SORT_STRING);if($requestKey!==''&&!preg_match('/^[a-f0-9]{32}$/D',$requestKey))throw new RuntimeException('This selection has expired. Reload and try again.');$requestHash=hash('sha256',json_encode([$performance,$selection],JSON_THROW_ON_ERROR));
        CommerceStore::tx($db,function()use($db,$user,$org,$cart,$performance,$selection,$requestKey,$requestHash){
            // Performance before cart is the common lock order for all inventory writes.
            $p=CommerceStore::row($db,'performances',$org,$performance,true);CommerceInventoryService::expireLocked($db,$org,$performance,(int)$user['id']);
            $c=self::owned($db,$user,$org,$cart,true);if($c['state']!=='open'||!(bool)$c['active'])throw new RuntimeException('Your cart has expired. Review your tickets.');
            if($requestKey!==''){$q=$db->prepare('SELECT request_hash FROM commerce_shopping_requests WHERE organization_id=? AND cart_id=? AND request_key=? FOR UPDATE');$q->execute([$org,$cart,$requestKey]);$priorRequest=$q->fetchColumn();if($priorRequest!==false){if(!hash_equals($priorRequest,$requestHash))throw new RuntimeException('This request does not match your original selection.');return;}}
            CommercePerformancePolicy::demand($db,$org,$p);self::demandCurrency($db,$org,$cart,$p['currency']);if($p['seating_mode']!=='reserved')throw new RuntimeException('Choose general admission quantities for this performance.');
            $prices=json_decode($p['prices_json'],true,flags:JSON_THROW_ON_ERROR);$inventory=array_column(CommerceInventoryService::inventory($db,$org,$performance),null,'seat_key');
            foreach($selection as $key=>$category){
                $seat=$inventory[$key]??null;if(!$seat)throw new RuntimeException('A selected seat is unavailable.');$info=json_decode($seat['seat_json'],true,flags:JSON_THROW_ON_ERROR);$price=$prices[$info['zone']][$category]??null;if(!is_int($price))throw new RuntimeException('Choose an available ticket type.');
                $q=$db->prepare("SELECT * FROM commerce_shopping_cart_items WHERE organization_id=? AND cart_id=? AND performance_id=? AND seat_key=? FOR UPDATE");$q->execute([$org,$cart,$performance,$key]);$prior=$q->fetch();
                if($prior&&$prior['state']==='held'){if($seat['state']!=='held'||$seat['order_id']!==null||$prior['category']!==$category)throw new RuntimeException('Your selection changed. Review the cart.');continue;}
                if(!in_array($seat['state'],['available','released'],true))throw new RuntimeException('A selected seat is no longer available. Your other tickets remain reserved.');
                $q=$db->prepare("INSERT INTO commerce_shopping_cart_items(organization_id,cart_id,performance_id,seat_key,category,unit_minor,currency,seat_json,state) VALUES(?,?,?,?,?,?,?,?,'held') ON DUPLICATE KEY UPDATE category=VALUES(category),unit_minor=VALUES(unit_minor),currency=VALUES(currency),seat_json=VALUES(seat_json),state='held'");$q->execute([$org,$cart,$performance,$key,$category,$price,$p['currency'],$seat['seat_json']]);
                $q=$db->prepare("UPDATE commerce_inventory SET state='held',order_id=NULL,reason=NULL WHERE organization_id=? AND performance_id=? AND seat_key=? AND state IN ('available','released')");$q->execute([$org,$performance,$key]);if($q->rowCount()!==1)throw new RuntimeException('A selected seat is no longer available.');
            }
            if($requestKey!=='')$db->prepare('INSERT INTO commerce_shopping_requests(organization_id,cart_id,request_key,request_hash) VALUES(?,?,?,?)')->execute([$org,$cart,$requestKey,$requestHash]);
            if($c['expires_at_utc']===null)$db->prepare('UPDATE commerce_shopping_carts SET expires_at_utc=DATE_ADD(UTC_TIMESTAMP(), INTERVAL '.self::HOLD_SECONDS.' SECOND) WHERE id=? AND organization_id=?')->execute([$cart,$org]);
            CommerceStore::audit($db,$org,(int)$user['id'],'commerce.cart_seats_added','cart',$cart,['performance'=>$performance,'count'=>count($selection)]);
        });
    }

    public static function addSeatsByQuantity(PDO $db,array $user,int $org,int $cart,int $performance,array $seatKeys,array $quantities,string $requestKey):void
    {
        CommerceAccess::demand($db,$user,$org,'test');$inventory=array_column(CommerceInventoryService::inventory($db,$org,$performance),null,'seat_key');$zones=[];$seen=[];
        foreach($seatKeys as $key){if(!is_string($key)||!isset($inventory[$key])||isset($seen[$key]))throw new RuntimeException('Choose available seats.');$seen[$key]=true;$seat=json_decode($inventory[$key]['seat_json'],true,flags:JSON_THROW_ON_ERROR);$zones[$seat['zone']][]=$key;}
        $selection=[];foreach($quantities as $zone=>$categories){if((!is_string($zone)&&!is_int($zone))||!is_array($categories))throw new RuntimeException('Choose ticket quantities.');$seats=$zones[$zone]??[];sort($seats,SORT_STRING);ksort($categories,SORT_STRING);$index=0;foreach($categories as $category=>$count){if((!is_string($category)&&!is_int($category))||(!is_int($count)&&!is_string($count))||!preg_match('/^(0|[1-9][0-9]?)$/D',(string)$count))throw new RuntimeException('Enter whole ticket quantities.');for($i=0;$i<(int)$count;$i++){if(!isset($seats[$index]))throw new RuntimeException('Ticket quantities must match selected seats.');$selection[$seats[$index++]]=(string)$category;}}if($index!==count($seats))throw new RuntimeException('Ticket quantities must match selected seats.');unset($zones[$zone]);}if($zones)throw new RuntimeException('Ticket quantities must match selected seats.');
        if(!preg_match('/^[a-f0-9]{32}$/D',$requestKey))throw new RuntimeException('This selection has expired. Reload and try again.');self::add($db,$user,$org,$cart,$performance,$selection,$requestKey);
    }

    private static function demandCurrency(PDO $db,int $org,int $cart,string $currency):void
    {
        $q=$db->prepare("SELECT 1 FROM commerce_shopping_cart_items WHERE organization_id=? AND cart_id=? AND state='held' AND currency<>? LIMIT 1");$q->execute([$org,$cart,$currency]);if($q->fetchColumn())throw new RuntimeException('Tickets in one cart must use the same currency.');
    }

    /** Reserve additional general admission tickets once for a stable request key. */
    public static function addAdmissions(PDO $db,array $user,int $org,int $cart,int $performance,array $quantities,string $requestKey):void
    {
        CommerceAccess::demand($db,$user,$org,'test');CommerceStorefrontService::demandPerformance($db,$user,$org,$performance);
        if(!preg_match('/^[a-f0-9]{32}$/D',$requestKey))throw new RuntimeException('This selection has expired. Reload and try again.');
        $requested=[];$total=0;foreach($quantities as $zone=>$categories){if((!is_string($zone)&&!is_int($zone))||!is_array($categories))throw new RuntimeException('Choose valid ticket quantities.');foreach($categories as $category=>$count){if((!is_string($category)&&!is_int($category))||(!is_string($count)&&!is_int($count))||!preg_match('/^(0|[1-9][0-9]?)$/D',(string)$count))throw new RuntimeException('Enter whole ticket quantities.');$count=(int)$count;if($count){$requested[$zone][$category]=$count;$total+=$count;}}if(isset($requested[$zone]))ksort($requested[$zone],SORT_STRING);}ksort($requested,SORT_STRING);
        if($total<1||$total>12)throw new RuntimeException('Choose between 1 and 12 tickets.');$hash=hash('sha256',json_encode([$performance,$requested],JSON_THROW_ON_ERROR));
        CommerceStore::tx($db,function()use($db,$user,$org,$cart,$performance,$requested,$requestKey,$hash,$total){
            $p=CommerceStore::row($db,'performances',$org,$performance,true);CommerceInventoryService::expireLocked($db,$org,$performance,(int)$user['id']);$c=self::owned($db,$user,$org,$cart,true);
            if($c['state']!=='open'||!(bool)$c['active'])throw new RuntimeException('Your cart has expired. Review your tickets.');
            $q=$db->prepare('SELECT request_hash FROM commerce_shopping_requests WHERE organization_id=? AND cart_id=? AND request_key=? FOR UPDATE');$q->execute([$org,$cart,$requestKey]);$previous=$q->fetchColumn();if($previous!==false){if(!hash_equals($previous,$hash))throw new RuntimeException('This request does not match your original selection.');return;}
            CommercePerformancePolicy::demand($db,$org,$p);self::demandCurrency($db,$org,$cart,$p['currency']);if($p['seating_mode']!=='general_admission')throw new RuntimeException('Choose assigned seats for this performance.');
            $prices=json_decode($p['prices_json'],true,flags:JSON_THROW_ON_ERROR);$available=[];foreach(CommerceInventoryService::inventory($db,$org,$performance) as $seat){if(!in_array($seat['state'],['available','released'],true))continue;$info=json_decode($seat['seat_json'],true,flags:JSON_THROW_ON_ERROR);$available[$info['zone']][]=$seat;}
            foreach($requested as $zone=>$categories)foreach($categories as $category=>$count){$price=$prices[$zone][$category]??null;if(!is_int($price))throw new RuntimeException('Choose an available ticket type.');if(count($available[$zone]??[])<$count)throw new RuntimeException('Not enough tickets are available. Your existing selections are unchanged.');for($i=0;$i<$count;$i++){$seat=array_shift($available[$zone]);$q=$db->prepare("INSERT INTO commerce_shopping_cart_items(organization_id,cart_id,performance_id,seat_key,category,unit_minor,currency,seat_json,state) VALUES(?,?,?,?,?,?,?,?,'held') ON DUPLICATE KEY UPDATE category=VALUES(category),unit_minor=VALUES(unit_minor),currency=VALUES(currency),seat_json=VALUES(seat_json),state='held'");$q->execute([$org,$cart,$performance,$seat['seat_key'],$category,$price,$p['currency'],$seat['seat_json']]);$q=$db->prepare("UPDATE commerce_inventory SET state='held',order_id=NULL,reason=NULL WHERE organization_id=? AND performance_id=? AND seat_key=? AND state IN ('available','released')");$q->execute([$org,$performance,$seat['seat_key']]);if($q->rowCount()!==1)throw new RuntimeException('Tickets are no longer available.');}}
            $db->prepare('INSERT INTO commerce_shopping_requests(organization_id,cart_id,request_key,request_hash) VALUES(?,?,?,?)')->execute([$org,$cart,$requestKey,$hash]);if($c['expires_at_utc']===null)$db->prepare('UPDATE commerce_shopping_carts SET expires_at_utc=DATE_ADD(UTC_TIMESTAMP(), INTERVAL '.self::HOLD_SECONDS.' SECOND) WHERE id=? AND organization_id=?')->execute([$cart,$org]);
            CommerceStore::audit($db,$org,(int)$user['id'],'commerce.cart_admissions_added','cart',$cart,['performance'=>$performance,'count'=>$total]);
        });
    }

    public static function remove(PDO $db,array $user,int $org,int $cart,int $performance,string $seat):void
    {
        CommerceAccess::demand($db,$user,$org,'test');
        CommerceStore::tx($db,function()use($db,$user,$org,$cart,$performance,$seat){
            CommerceStore::row($db,'performances',$org,$performance,true);CommerceInventoryService::expireLocked($db,$org,$performance,(int)$user['id']);$c=self::owned($db,$user,$org,$cart,true);
            if($c['state']!=='open')throw new RuntimeException('This cart can no longer be changed.');
            $q=$db->prepare('SELECT state FROM commerce_shopping_cart_items WHERE organization_id=? AND cart_id=? AND performance_id=? AND seat_key=? FOR UPDATE');$q->execute([$org,$cart,$performance,$seat]);$state=$q->fetchColumn();if($state===false)throw new RuntimeException('Ticket unavailable.');if($state==='removed')return;
            if($state==='held'){$q=$db->prepare("UPDATE commerce_inventory SET state='released' WHERE organization_id=? AND performance_id=? AND seat_key=? AND state='held' AND order_id IS NULL");$q->execute([$org,$performance,$seat]);if($q->rowCount()!==1)throw new RuntimeException('This reservation changed. Contact the box office.');}
            $db->prepare("UPDATE commerce_shopping_cart_items SET state='removed' WHERE organization_id=? AND cart_id=? AND performance_id=? AND seat_key=?")->execute([$org,$cart,$performance,$seat]);
            if(!(bool)$c['active']){$q=$db->prepare("SELECT COUNT(*) FROM commerce_shopping_cart_items WHERE organization_id=? AND cart_id=? AND state IN ('held','expired')");$q->execute([$org,$cart]);if((int)$q->fetchColumn()===0)$db->prepare("UPDATE commerce_shopping_carts SET state='expired' WHERE id=? AND organization_id=? AND state='open'")->execute([$cart,$org]);}
        });
    }
}
