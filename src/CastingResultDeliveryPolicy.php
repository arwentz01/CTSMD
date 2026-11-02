<?php
declare(strict_types=1);
require_once __DIR__.'/IdentityRolePolicy.php';

/** Read-only dispatch gate; testing this never starts an email worker. */
final class CastingResultDeliveryPolicy
{
    public static function allows(PDO $db,int $recordId,int $recipientId,string $decision):bool
    {
        $q=$db->prepare("SELECT cr.user_id FROM production_casting_records cr JOIN productions p ON p.id=cr.production_id AND p.lifecycle_stage IN ('upcoming','planning','in_production','performing') AND p.status<>'archived' JOIN users performer ON performer.id=cr.user_id AND performer.active=1 AND performer.account_status<>'disabled' JOIN users recipient ON recipient.id=? AND recipient.active=1 AND recipient.account_status='active' WHERE cr.id=? AND cr.casting_status=?");$q->execute([$recipientId,$recordId,$decision]);$subject=(int)$q->fetchColumn();
        if(!$subject)return false;
        Auth::clearRequestCache($subject);
        if(!IdentityRolePolicy::isStudent($db,$subject))return IdentityRolePolicy::isAdult($db,$subject)&&$subject===$recipientId;
        if($subject===$recipientId){$q=$db->prepare("SELECT 1 FROM family_relationships fr JOIN users guardian ON guardian.id=fr.guardian_user_id AND guardian.active=1 AND guardian.account_status='active' WHERE fr.student_user_id=? AND fr.status='active' AND fr.relationship_type IN ('parent','guardian') LIMIT 1");$q->execute([$subject]);return (bool)$q->fetchColumn();}
        $q=$db->prepare("SELECT 1 FROM family_relationships WHERE student_user_id=? AND guardian_user_id=? AND status='active' AND relationship_type IN ('parent','guardian') LIMIT 1");$q->execute([$subject,$recipientId]);return (bool)$q->fetchColumn();
    }
}
