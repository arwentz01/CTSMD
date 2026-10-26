<?php

declare(strict_types=1);

require_once __DIR__.'/ProductionContext.php';

/**
 * Query-time visibility for production-linked notification records.
 * Historical records are retained, but removed members/guardians must not read
 * newer casting announcements from their notifications feed.
 */
final class ProductionNotificationAccess
{
    public static function visible(PDO $db,int $recipientId,array $notification):bool
    {
        $source=(string)($notification['source_type']??'');
        if($source==='casting_offer'||$source==='casting_offer_response'){
            $offerId=(int)($notification['source_id']??0);
            if($offerId<1)return false;
            $s=$db->prepare("SELECT production_id,student_user_id FROM production_cast_offers WHERE id=:id LIMIT 1");
            $s->execute(['id'=>$offerId]);$offer=$s->fetch();
            if(!$offer)return false;
            if($source==='casting_offer'){
                if($recipientId===(int)$offer['student_user_id'])return true;
                $s=$db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:guardian AND student_user_id=:student AND status='active' LIMIT 1");
                $s->execute(['guardian'=>$recipientId,'student'=>(int)$offer['student_user_id']]);
                return (bool)$s->fetchColumn();
            }
            return ProductionContext::isActiveMember($db,$recipientId,(int)$offer['production_id']);
        }
        if($source!=='casting_release')return true;
        $productionId=(int)($notification['source_id']??0);
        return $productionId>0 && ProductionContext::isActiveMember($db,$recipientId,$productionId);
    }

    public static function filter(PDO $db,int $recipientId,array $rows):array
    {
        return array_values(array_filter($rows,static fn(array $row):bool=>self::visible($db,$recipientId,$row)));
    }
}
