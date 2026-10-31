<?php
declare(strict_types=1);
require_once __DIR__.'/CommercePaymentService.php';
final class CommerceTicketService
{
    private static function token(string $scan):string{$scan=trim($scan);if(str_starts_with($scan,'CC1:'))$scan=substr($scan,4);if(!preg_match('/^[a-f0-9]{64}$/',$scan))throw new RuntimeException('Ticket code not recognized. Scan the admission QR or paste its manual code.');return$scan;}
    public static function checkin(PDO $db,array $u,int $org,int $performance,string $scan):array
    {
        CommerceAccess::demand($db,$u,$org,'checkin');CommerceStore::organization($db,$org,true);$token=self::token($scan);
        return CommerceStore::tx($db,function()use($db,$u,$org,$performance,$token){$p=CommerceStore::row($db,'performances',$org,$performance,true);ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);if(!$p['checkin_enabled'])throw new RuntimeException('Check-in is closed for this performance.');$q=$db->prepare('SELECT * FROM commerce_tickets WHERE organization_id=? AND token=? FOR UPDATE');$q->execute([$org,$token]);$t=$q->fetch();if(!$t)throw new RuntimeException('Ticket unavailable for this organization.');if((int)$t['performance_id']!==$performance)throw new RuntimeException('Wrong performance. This ticket was not admitted.');$o=CommerceStore::row($db,'orders',$org,(int)$t['order_id']);if($t['state']!=='valid'||$o['state']!=='paid')throw new RuntimeException('Ticket is revoked, refunded or unpaid. Do not admit.');if($t['checked_in_at'])throw new RuntimeException('Already checked in at '.$t['checked_in_at'].'. No second admission recorded.');$q=$db->prepare('UPDATE commerce_tickets SET checked_in_at=NOW(),checked_in_by=? WHERE organization_id=? AND id=? AND checked_in_at IS NULL AND state=\'valid\'');$q->execute([$u['id'],$org,$t['id']]);if($q->rowCount()!==1)throw new RuntimeException('Already admitted. No second admission recorded.');CommerceStore::audit($db,$org,(int)$u['id'],'commerce.ticket_checked_in','ticket',(int)$t['id'],['performance'=>$performance]);$line=$db->prepare('SELECT description,category FROM commerce_order_lines WHERE organization_id=? AND order_id=? AND seat_key=?');$line->execute([$org,$t['order_id'],$t['seat_key']]);return$line->fetch()+['ticket_id'=>(int)$t['id'],'status'=>'Admitted'];});
    }
    public static function reverse(PDO $db,array $u,int $org,int $ticket,string $reason):void
    {
        CommerceAccess::demand($db,$u,$org,'reverse');self::reason($reason);$t=CommerceStore::row($db,'tickets',$org,$ticket);
        CommerceStore::tx($db,function()use($db,$u,$org,$ticket,$reason,$t){CommerceStore::row($db,'performances',$org,(int)$t['performance_id'],true);$t=CommerceStore::row($db,'tickets',$org,$ticket,true);if(!$t['checked_in_at'])throw new RuntimeException('This ticket has no admission to reverse.');$db->prepare('UPDATE commerce_tickets SET checked_in_at=NULL,checked_in_by=NULL WHERE organization_id=? AND id=?')->execute([$org,$ticket]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.checkin_reversed','ticket',$ticket,['reason'=>$reason,'previous_checkin'=>$t['checked_in_at'],'previous_actor'=>$t['checked_in_by']]);});
    }
    public static function revoke(PDO $db,array $u,int $org,int $ticket,string $reason):void
    {
        CommerceAccess::demand($db,$u,$org,'refund');self::reason($reason);$t=CommerceStore::row($db,'tickets',$org,$ticket);
        CommerceStore::tx($db,function()use($db,$u,$org,$ticket,$reason,$t){CommerceStore::row($db,'performances',$org,(int)$t['performance_id'],true);$t=CommerceStore::row($db,'tickets',$org,$ticket,true);if($t['checked_in_at'])throw new RuntimeException('Reverse admission before revoking this ticket.');if($t['state']!=='valid')throw new RuntimeException('Only valid tickets can be revoked.');$db->prepare("UPDATE commerce_tickets SET state='revoked' WHERE organization_id=? AND id=?")->execute([$org,$ticket]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.ticket_revoked','ticket',$ticket,['reason'=>$reason]);});
    }
    private static function reason(string $reason):void{if(trim($reason)===''||mb_strlen($reason)>500)throw new RuntimeException('Record a reason of up to 500 characters.');}
    /** Door staff see admission information, not purchaser contacts or financial amounts. */
    public static function lookup(PDO $db,array $u,int $org,int $performance,string $reference):array
    {
        CommerceAccess::demand($db,$u,$org,'checkin');CommerceStore::row($db,'performances',$org,$performance);if(!preg_match('/^[a-f0-9]{32}$/',$reference))throw new RuntimeException('Enter the full sandbox order reference.');$q=$db->prepare('SELECT t.id,t.token,t.state,t.checked_in_at,l.description,l.category FROM commerce_orders o JOIN commerce_tickets t ON t.order_id=o.id AND t.organization_id=o.organization_id JOIN commerce_order_lines l ON l.order_id=t.order_id AND l.seat_key=t.seat_key AND l.organization_id=t.organization_id WHERE o.organization_id=? AND o.performance_id=? AND o.public_reference=?');$q->execute([$org,$performance,$reference]);$rows=$q->fetchAll();CommerceStore::audit($db,$org,(int)$u['id'],'commerce.door_lookup','performance',$performance,['found'=>(bool)$rows]);return$rows;
    }
    public static function attendance(PDO $db,array $u,int $org,int $performance):array
    {
        CommerceAccess::demand($db,$u,$org,'checkin');CommerceStore::row($db,'performances',$org,$performance);$q=$db->prepare("SELECT COUNT(*) issued,SUM(t.state='valid' AND o.state='paid') valid_count,SUM(t.state='valid' AND o.state='paid' AND t.checked_in_at IS NOT NULL) admitted FROM commerce_tickets t JOIN commerce_orders o ON o.id=t.order_id AND o.organization_id=t.organization_id WHERE t.organization_id=? AND t.performance_id=?");$q->execute([$org,$performance]);return$q->fetch();
    }
    public static function reportOrder(PDO $db,array $u,int $org,string $reference):?array
    {CommerceAccess::demand($db,$u,$org,'report');if(!preg_match('/^[a-f0-9]{32}$/',$reference))throw new RuntimeException('Enter the full sandbox order reference.');$q=$db->prepare('SELECT * FROM commerce_orders WHERE organization_id=? AND public_reference=?');$q->execute([$org,$reference]);$o=$q->fetch();CommerceStore::audit($db,$org,(int)$u['id'],'commerce.order_lookup','order',(int)($o['id']??0));return$o?:null;}
}
