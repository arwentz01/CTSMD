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
    public static function add(PDO $db,array $user,int $org,int $cart,int $performance,array $selection):void
    {
        CommerceAccess::demand($db,$user,$org,'test');CommerceStorefrontService::demandPerformance($db,$user,$org,$performance);
        if(!$selection||count($selection)>12)throw new RuntimeException('Choose between 1 and 12 tickets.');
        foreach($selection as $seat=>$category)if(!is_string($seat)||!is_string($category)||$seat===''||$category==='')throw new RuntimeException('Choose a ticket type for each seat.');
        ksort($selection,SORT_STRING);
        CommerceStore::tx($db,function()use($db,$user,$org,$cart,$performance,$selection){
            // Performance before cart is the common lock order for all inventory writes.
            $p=CommerceStore::row($db,'performances',$org,$performance,true);CommerceInventoryService::expireLocked($db,$org,$performance,(int)$user['id']);
            $c=self::owned($db,$user,$org,$cart,true);if($c['state']!=='open'||!(bool)$c['active'])throw new RuntimeException('Your cart has expired. Review your tickets.');
            CommercePerformancePolicy::demand($db,$org,$p);if($p['seating_mode']!=='reserved')throw new RuntimeException('Choose general admission quantities for this performance.');
            $prices=json_decode($p['prices_json'],true,flags:JSON_THROW_ON_ERROR);$inventory=array_column(CommerceInventoryService::inventory($db,$org,$performance),null,'seat_key');
            foreach($selection as $key=>$category){
                $seat=$inventory[$key]??null;if(!$seat)throw new RuntimeException('A selected seat is unavailable.');$info=json_decode($seat['seat_json'],true,flags:JSON_THROW_ON_ERROR);$price=$prices[$info['zone']][$category]??null;if(!is_int($price))throw new RuntimeException('Choose an available ticket type.');
                $q=$db->prepare("SELECT * FROM commerce_shopping_cart_items WHERE organization_id=? AND cart_id=? AND performance_id=? AND seat_key=? FOR UPDATE");$q->execute([$org,$cart,$performance,$key]);$prior=$q->fetch();
                if($prior&&$prior['state']==='held'){if($seat['state']!=='held'||$seat['order_id']!==null||$prior['category']!==$category)throw new RuntimeException('Your selection changed. Review the cart.');continue;}
                if(!in_array($seat['state'],['available','released'],true))throw new RuntimeException('A selected seat is no longer available. Your other tickets remain reserved.');
                $q=$db->prepare("INSERT INTO commerce_shopping_cart_items(organization_id,cart_id,performance_id,seat_key,category,unit_minor,currency,seat_json,state) VALUES(?,?,?,?,?,?,?,?,'held') ON DUPLICATE KEY UPDATE category=VALUES(category),unit_minor=VALUES(unit_minor),currency=VALUES(currency),seat_json=VALUES(seat_json),state='held'");$q->execute([$org,$cart,$performance,$key,$category,$price,$p['currency'],$seat['seat_json']]);
                $q=$db->prepare("UPDATE commerce_inventory SET state='held',order_id=NULL,reason=NULL WHERE organization_id=? AND performance_id=? AND seat_key=? AND state IN ('available','released')");$q->execute([$org,$performance,$key]);if($q->rowCount()!==1)throw new RuntimeException('A selected seat is no longer available.');
            }
            if($c['expires_at_utc']===null)$db->prepare('UPDATE commerce_shopping_carts SET expires_at_utc=DATE_ADD(UTC_TIMESTAMP(), INTERVAL '.self::HOLD_SECONDS.' SECOND) WHERE id=? AND organization_id=?')->execute([$cart,$org]);
            CommerceStore::audit($db,$org,(int)$user['id'],'commerce.cart_seats_added','cart',$cart,['performance'=>$performance,'count'=>count($selection)]);
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
