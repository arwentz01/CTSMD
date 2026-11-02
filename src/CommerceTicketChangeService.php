<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceTicketService.php';
/** Same-performance, same-zone relocations only; no repricing or financial-history rewrite. */
final class CommerceTicketChangeService
{
    public static function change(PDO $db,array $u,int $org,int $ticket,string $target,string $reason,string $expectedTokenHash):array
    {
        CommerceAccess::demand($db,$u,$org,'refund');$reason=trim($reason);if($reason===''||mb_strlen($reason)>500||!preg_match('/^[a-f0-9]{64}$/',$expectedTokenHash))throw new RuntimeException('Record a reason and reload the current ticket before changing it.');$before=CommerceStore::row($db,'tickets',$org,$ticket);
        return CommerceStore::tx($db,function()use($db,$u,$org,$ticket,$target,$reason,$expectedTokenHash,$before){$p=CommerceStore::row($db,'performances',$org,(int)$before['performance_id'],true);CommercePerformancePolicy::demand($db,$org,$p,false);$t=CommerceStore::row($db,'tickets',$org,$ticket,true);$order=CommerceStore::row($db,'orders',$org,(int)$t['order_id'],true);
            if($order['state']!=='paid'||$t['state']!=='valid'||$t['checked_in_at'])throw new RuntimeException('Only valid, unadmitted tickets on paid orders can be changed. Reverse admission first.');
            if(!hash_equals(hash('sha256',$t['token']),$expectedTokenHash))throw new RuntimeException('This ticket already changed. Reload its current status before retrying.');
            $priorHash=hash('sha256',$t['token']);$token=bin2hex(random_bytes(32));
            if($target===''||$target===$t['seat_key']){
                $db->prepare('UPDATE commerce_tickets SET token=? WHERE organization_id=? AND id=?')->execute([$token,$org,$ticket]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.ticket_reissued','ticket',$ticket,['reason'=>$reason,'old_token_hash'=>$priorHash,'new_token_hash'=>hash('sha256',$token)]);return CommerceStore::row($db,'tickets',$org,$ticket);
            }
            if($p['seating_mode']!=='reserved')throw new RuntimeException('General admission has no assigned seats to relocate.');CommerceInventoryService::expireLocked($db,$org,(int)$p['id'],(int)$u['id']);$items=array_column(CommerceInventoryService::inventory($db,$org,(int)$p['id']),null,'seat_key');$old=$items[$t['seat_key']]??null;$next=$items[$target]??null;
            if(!$old||$old['state']!=='sold'||(int)$old['order_id']!==(int)$order['id']||!$next||!in_array($next['state'],['available','released'],true))throw new RuntimeException('The original or replacement inventory is unavailable. Nothing was changed.');
            $oldSeat=json_decode($old['seat_json'],true);$seat=json_decode($next['seat_json'],true);if($oldSeat['zone']!==$seat['zone'])throw new RuntimeException('Only same-price-zone relocations are supported. Financial exchanges require a separate reviewed workflow.');
            $q=$db->prepare('SELECT * FROM commerce_order_lines WHERE organization_id=? AND order_id=? AND seat_key=?');$q->execute([$org,$order['id'],$t['seat_key']]);$line=$q->fetch();
            $q=$db->prepare('SELECT 1 FROM commerce_order_lines WHERE organization_id=? AND order_id=? AND seat_key=?');$q->execute([$org,$order['id'],$target]);if($q->fetchColumn())throw new RuntimeException('That seat already belongs to this order history. Choose another seat.');
            $db->prepare("INSERT INTO commerce_order_lines(organization_id,order_id,seat_key,product_type,category,description,unit_minor,seat_json) VALUES(?,?,?,'relocation_ticket',?,?,0,?)")->execute([$org,$order['id'],$target,$line['category'],$seat['section_name'].' / '.$seat['row_name'].' / '.$seat['label'].' · relocated, original price retained',$next['seat_json']]);
            $db->prepare("UPDATE commerce_tickets SET state='revoked' WHERE organization_id=? AND id=?")->execute([$org,$ticket]);
            $db->prepare("UPDATE commerce_inventory SET state='released',order_id=NULL,reason=? WHERE organization_id=? AND performance_id=? AND seat_key=?")->execute(['Relocation: '.$reason,$org,$p['id'],$t['seat_key']]);
            $db->prepare("UPDATE commerce_inventory SET state='sold',order_id=?,reason=? WHERE organization_id=? AND performance_id=? AND seat_key=?")->execute([$order['id'],'Relocation: '.$reason,$org,$p['id'],$target]);
            $db->prepare('INSERT INTO commerce_tickets(organization_id,order_id,performance_id,seat_key,token) VALUES(?,?,?,?,?)')->execute([$org,$order['id'],$p['id'],$target,$token]);$id=(int)$db->lastInsertId();CommerceStore::audit($db,$org,(int)$u['id'],'commerce.ticket_relocated','ticket',$ticket,['reason'=>$reason,'replacement_ticket'=>$id,'from_seat'=>$t['seat_key'],'to_seat'=>$target,'original_line'=>$line['id'],'financial_change_minor'=>0]);return CommerceStore::row($db,'tickets',$org,$id);
        });
    }
}
