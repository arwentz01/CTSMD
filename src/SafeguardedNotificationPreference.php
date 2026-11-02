<?php

declare(strict_types=1);

/**
 * Guardian-specific alert preference. Muting never changes membership,
 * read access, unread counts, the conversation record or required participants.
 */
final class SafeguardedNotificationPreference
{
    public static function isGuardian(PDO $db,int $userId):bool
    {
        if($userId<1)return false;
        $s=$db->prepare("SELECT 1 FROM family_relationships fr JOIN users student ON student.id=fr.student_user_id AND student.active=1 WHERE fr.guardian_user_id=:user AND fr.status='active' AND fr.relationship_type IN ('parent','guardian') LIMIT 1");
        $s->execute(['user'=>$userId]);
        return (bool)$s->fetchColumn();
    }

    public static function alertsEnabled(PDO $db,int $userId,int $conversationId):bool
    {
        if($conversationId<1||$userId<1)return false;
        $s=$db->prepare('SELECT conversation_type FROM conversations WHERE id=:id LIMIT 1');
        $s->execute(['id'=>$conversationId]);
        if($s->fetchColumn()!=='safeguarded')return true;
        // Only optional guardian alerts may be muted, never staff safety-participant alerts.
        $participant=$db->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id=:conversation AND user_id=:user AND participant_role='guardian' AND access_revoked_at IS NULL LIMIT 1");
        $participant->execute(['conversation'=>$conversationId,'user'=>$userId]);
        if(!$participant->fetchColumn())return true;
        if(!self::isGuardian($db,$userId))return true;
        $s=$db->prepare('SELECT push_safeguarded_messages FROM notification_preferences WHERE user_id=:user LIMIT 1');
        $s->execute(['user'=>$userId]);
        $value=$s->fetchColumn();
        return $value===false||(bool)$value;
    }
}
