<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';

/**
 * Sandbox-only cart membership foundation. Payment and fulfillment remain
 * single-order until an explicitly reviewed atomic multi-order checkout exists.
 */
final class CommerceCartService
{
    public static function create(PDO $db,array $user,int $organization,string $token):int
    {
        CommerceAccess::demand($db,$user,$organization,'test');
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('Invalid cart ownership token.');
        return CommerceStore::tx($db,function()use($db,$user,$organization,$token):int{
            $q=$db->prepare('INSERT INTO commerce_carts(organization_id,tester_user_id,ownership_hash) VALUES(?,?,?)');
            $q->execute([$organization,(int)$user['id'],hash('sha256',$token)]);
            return (int)$db->lastInsertId();
        });
    }

    private static function ownedLocked(PDO $db,array $user,int $organization,int $cart,string $token):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('Cart is unavailable.');
        $q=$db->prepare('SELECT * FROM commerce_carts WHERE id=? AND organization_id=? FOR UPDATE');
        $q->execute([$cart,$organization]);$row=$q->fetch();
        if(!$row||(int)$row['tester_user_id']!==(int)$user['id']||!hash_equals($row['ownership_hash'],hash('sha256',$token)))throw new RuntimeException('Cart is unavailable.');
        return $row;
    }

    public static function addHeldOrder(PDO $db,array $user,int $organization,int $cart,string $cartToken,int $order,string $orderToken):void
    {
        CommerceAccess::demand($db,$user,$organization,'test');
        CommerceStore::tx($db,function()use($db,$user,$organization,$cart,$cartToken,$order,$orderToken):void{
            $c=self::ownedLocked($db,$user,$organization,$cart,$cartToken);
            if($c['state']!=='open')throw new RuntimeException('This cart can no longer be changed.');
            $o=CommerceInventoryService::own($db,$user,$organization,$order,$orderToken);
            $q=$db->prepare('SELECT state,(expires_at>NOW()) AS hold_valid,currency,customer_name,customer_email FROM commerce_orders WHERE id=? AND organization_id=? FOR UPDATE');
            $q->execute([$order,$organization]);$locked=$q->fetch();
            if(!$locked||$locked['state']!=='held'||!(bool)$locked['hold_valid'])throw new RuntimeException('This seat hold has expired or is no longer available.');
            $q=$db->prepare('SELECT o.currency,o.customer_name,o.customer_email FROM commerce_cart_orders co JOIN commerce_orders o ON o.id=co.order_id AND o.organization_id=co.organization_id WHERE co.organization_id=? AND co.cart_id=? LIMIT 1');
            $q->execute([$organization,$cart]);$existing=$q->fetch();
            if($existing&&($existing['currency']!==$locked['currency']||$existing['customer_name']!==$locked['customer_name']||$existing['customer_email']!==$locked['customer_email']))throw new RuntimeException('All cart selections must use the same purchaser and currency.');
            $q=$db->prepare('INSERT INTO commerce_cart_orders(organization_id,cart_id,order_id) VALUES(?,?,?)');
            $q->execute([$organization,$cart,$order]);
        });
    }

    public static function summary(PDO $db,array $user,int $organization,int $cart,string $token):array
    {
        CommerceAccess::demand($db,$user,$organization,'test');
        return CommerceStore::tx($db,function()use($db,$user,$organization,$cart,$token):array{
            $c=self::ownedLocked($db,$user,$organization,$cart,$token);
            $q=$db->prepare('SELECT o.id,o.performance_id,o.state,o.expires_at,o.currency,o.subtotal_minor,o.discount_minor,o.tax_minor,o.fee_minor,o.total_minor FROM commerce_cart_orders co JOIN commerce_orders o ON o.id=co.order_id AND o.organization_id=co.organization_id WHERE co.organization_id=? AND co.cart_id=? ORDER BY co.added_at,o.id');
            $q->execute([$organization,$cart]);$orders=$q->fetchAll();
            $totals=['subtotal_minor'=>0,'discount_minor'=>0,'tax_minor'=>0,'fee_minor'=>0,'total_minor'=>0];
            foreach($orders as $o){foreach($totals as $key=>$_)$totals[$key]+=(int)$o[$key];}
            return ['state'=>$c['state'],'orders'=>$orders,'totals'=>$totals];
        });
    }
}
