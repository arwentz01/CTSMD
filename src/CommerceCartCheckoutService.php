<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceCartService.php';

/**
 * One-payment-per-cart ledger preparation. No provider API calls, captures or
 * fulfillment occur in this class. Integration remains explicitly disabled.
 */
final class CommerceCartCheckoutService
{
    public static function prepare(PDO $db,array $user,int $org,int $cart,string $token):array
    {
        CommerceAccess::demand($db,$user,$org,'test');
        if(getenv('COMMERCE_CART_CHECKOUT_PREPARE_ENABLED')!=='1'){
            throw new RuntimeException('Cart checkout preparation is disabled.');
        }
        // A retry may arrive after the cart has already been frozen. Return the
        // existing immutable snapshot only to its authenticated owner.
        $existing=CommerceStore::tx($db,function()use($db,$user,$org,$cart,$token):?array{
            $q=$db->prepare('SELECT state,tester_user_id,ownership_hash FROM commerce_carts WHERE organization_id=? AND id=? FOR UPDATE');
            $q->execute([$org,$cart]);$owner=$q->fetch();
            if(!$owner||!preg_match('/^[a-f0-9]{64}$/',$token)||
               (int)$owner['tester_user_id']!==(int)$user['id']||
               !hash_equals((string)$owner['ownership_hash'],hash('sha256',$token))){
                throw new RuntimeException('Cart unavailable.');
            }
            if($owner['state']!=='checkout_pending')return null;
            $q=$db->prepare('SELECT * FROM commerce_cart_payments WHERE organization_id=? AND cart_id=?');
            $q->execute([$org,$cart]);$payment=$q->fetch();
            if(!$payment)throw new RuntimeException('Cart checkout requires reconciliation.');
            return $payment;
        });
        if($existing!==null)return $existing;
        // Readiness acquires a cart lock and verifies holds, performance eligibility,
        // ticket inventory, purchaser identity, and absence of legacy payment attempts.
        $quote=CommerceCartService::readiness($db,$user,$org,$cart,$token);
        if($quote['total_minor']<1)throw new RuntimeException('This cart has no payable balance.');
        return CommerceStore::tx($db,function()use($db,$user,$org,$cart,$token,$quote):array{
            $q=$db->prepare('SELECT id,state,tester_user_id,ownership_hash FROM commerce_carts WHERE organization_id=? AND id=? FOR UPDATE');
            $q->execute([$org,$cart]);$owner=$q->fetch();
            if(!$owner||(int)$owner['tester_user_id']!==(int)$user['id']||!preg_match('/^[a-f0-9]{64}$/',$token)||!hash_equals((string)$owner['ownership_hash'],hash('sha256',$token))||!in_array($owner['state'],['open','checkout_pending'],true)){
                throw new RuntimeException('This cart is unavailable for checkout.');
            }
            $q=$db->prepare('SELECT * FROM commerce_cart_payments WHERE organization_id=? AND cart_id=? FOR UPDATE');
            $q->execute([$org,$cart]);
            if($existing=$q->fetch()){
                if($owner['state']!=='checkout_pending')throw new RuntimeException('Cart payment state requires reconciliation.');
                return $existing;
            }
            if($owner['state']!=='open')throw new RuntimeException('This cart cannot start a new checkout.');
            $q=$db->prepare("SELECT o.id,o.performance_id,o.state,o.expires_at,o.currency,o.customer_name,o.customer_email,o.total_minor,
                (o.expires_at>NOW()) AS hold_valid
                FROM commerce_cart_orders co JOIN commerce_orders o
                ON o.organization_id=co.organization_id AND o.id=co.order_id
                WHERE co.organization_id=? AND co.cart_id=? ORDER BY o.id FOR UPDATE");
            $q->execute([$org,$cart]);$orders=$q->fetchAll();
            if(count($orders)!==(int)$quote['order_count'])throw new RuntimeException('Cart changed. Review your selections again.');
            $total=0;$first=$orders[0]??null;
            if(!$first)throw new RuntimeException('This cart is empty.');
            foreach($orders as $o){
                if($o['state']!=='held'||!(bool)$o['hold_valid']||$o['currency']!==$first['currency']||
                    $o['customer_name']!==$first['customer_name']||$o['customer_email']!==$first['customer_email']){
                    throw new RuntimeException('Cart selections changed. Review your tickets again.');
                }
                $q=$db->prepare('SELECT 1 FROM commerce_payment_attempts WHERE organization_id=? AND order_id=? LIMIT 1');
                $q->execute([$org,$o['id']]);
                if($q->fetchColumn())throw new RuntimeException('A ticket selection requires payment reconciliation.');
                $q=$db->prepare("SELECT COUNT(*) FROM commerce_order_lines WHERE organization_id=? AND order_id=?");
                $q->execute([$org,$o['id']]);$expected=(int)$q->fetchColumn();
                $q=$db->prepare("SELECT COUNT(*) FROM commerce_inventory WHERE organization_id=? AND order_id=? AND state='held'");
                $q->execute([$org,$o['id']]);
                if($expected<1||(int)$q->fetchColumn()!==$expected)throw new RuntimeException('Cart inventory changed.');
                $q=$db->prepare("SELECT COUNT(*) FROM commerce_order_lines l LEFT JOIN commerce_inventory i
                    ON i.organization_id=l.organization_id AND i.performance_id=?
                    AND i.order_id=l.order_id AND i.seat_key=l.seat_key AND i.state='held'
                    WHERE l.organization_id=? AND l.order_id=? AND i.seat_key IS NULL");
                $q->execute([$o['performance_id'],$org,$o['id']]);
                if((int)$q->fetchColumn()!==0)throw new RuntimeException('Reserved ticket inventory changed.');
                CommercePerformancePolicy::demand($db,$org,CommerceStore::row($db,'performances',$org,(int)$o['performance_id']));
                if((int)$o['total_minor']<0||$total>PHP_INT_MAX-(int)$o['total_minor'])throw new RuntimeException('Invalid cart payment amount.');
                $total+=(int)$o['total_minor'];
            }
            if($total!==(int)$quote['total_minor']||$first['currency']!==$quote['currency'])throw new RuntimeException('Cart total changed. Review your selections again.');
            $reference=bin2hex(random_bytes(16));
            $q=$db->prepare("INSERT INTO commerce_cart_payments(organization_id,cart_id,reference,currency,expected_minor,state) VALUES(?,?,?,?,?,'prepared')");
            $q->execute([$org,$cart,$reference,$first['currency'],$total]);$payment=(int)$db->lastInsertId();
            $insert=$db->prepare('INSERT INTO commerce_cart_payment_orders(organization_id,payment_id,order_id,expected_minor) VALUES(?,?,?,?)');
            foreach($orders as $o)$insert->execute([$org,$payment,$o['id'],$o['total_minor']]);
            $db->prepare("UPDATE commerce_carts SET state='checkout_pending' WHERE organization_id=? AND id=? AND state='open'")->execute([$org,$cart]);
            CommerceStore::audit($db,$org,(int)$user['id'],'commerce.cart_payment_prepared','cart',$cart,['payment_id'=>$payment,'order_count'=>count($orders),'amount_minor'=>$total]);
            $q=$db->prepare('SELECT * FROM commerce_cart_payments WHERE id=? AND organization_id=?');
            $q->execute([$payment,$org]);return $q->fetch();
        });
    }
}
