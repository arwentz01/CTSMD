<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
/** Shared internal fulfillment; callers authorize settlement and hold the performance lock. */
final class CommerceFulfillmentService
{
    public static function issueLocked(PDO $db,int $org,array $order):void
    {
        if(!$db->inTransaction())throw new LogicException('Ticket fulfillment requires a transaction.');
        $id=(int)$order['id'];$performance=(int)$order['performance_id'];CommercePerformancePolicy::demand($db,$org,CommerceStore::row($db,'performances',$org,$performance));
        $q=$db->prepare('SELECT * FROM commerce_order_lines WHERE organization_id=? AND order_id=? ORDER BY id');$q->execute([$org,$id]);$lines=$q->fetchAll();$inventory=array_column(CommerceInventoryService::inventory($db,$org,$performance),null,'seat_key');
        if(!$lines)throw new RuntimeException('Order has no fulfillable seats; manual reconciliation required.');
        foreach($lines as $line){$seat=$inventory[$line['seat_key']]??null;if(!$seat||$seat['state']!=='held'||(int)$seat['order_id']!==$id)throw new RuntimeException('Inventory does not match settlement; manual reconciliation required.');}
        foreach($lines as $line){$db->prepare('INSERT INTO commerce_tickets(organization_id,order_id,performance_id,seat_key,token) VALUES(?,?,?,?,?)')->execute([$org,$id,$performance,$line['seat_key'],bin2hex(random_bytes(32))]);$db->prepare("UPDATE commerce_inventory SET state='sold' WHERE organization_id=? AND performance_id=? AND seat_key=? AND order_id=?")->execute([$org,$performance,$line['seat_key'],$id]);}
    }
}
