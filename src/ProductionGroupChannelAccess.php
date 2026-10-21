<?php

declare(strict_types=1);

final class ProductionGroupChannelAccess
{
    public static function canAccess(PDO $db,int $channelId,int $userId,string $mode='read'):bool
    {
        if($channelId<1||$userId<1)return false;
        $column=$mode==='post'?'can_post':'can_read';

        $selected=$db->prepare("SELECT cm.$column FROM channel_members cm JOIN users u ON u.id=cm.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE cm.channel_id=:channel AND cm.user_id=:user AND cm.status='active' LIMIT 1");
        $selected->execute(['channel'=>$channelId,'user'=>$userId]);
        if((bool)$selected->fetchColumn())return true;

        $group=$db->prepare("SELECT 1
            FROM channels c
            JOIN productions p ON p.id=c.production_id AND p.is_active=1
            JOIN production_groups pg ON pg.id=c.production_group_id AND pg.production_id=c.production_id AND pg.active=1
            WHERE c.id=:channel
              AND (
                  EXISTS (
                      SELECT 1
                      FROM production_group_members pgm
                      JOIN production_memberships pm ON pm.id=pgm.production_membership_id AND pm.user_id=:user_direct AND pm.status='active'
                      JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled'
                      WHERE pgm.group_id=pg.id AND pgm.status='active' AND pm.production_id=c.production_id
                  )
                  OR EXISTS (
                      SELECT 1
                      FROM family_relationships fr
                      JOIN users guardian ON guardian.id=fr.guardian_user_id AND guardian.active=1 AND guardian.account_status<>'disabled'
                      JOIN production_memberships gpm ON gpm.user_id=fr.guardian_user_id AND gpm.audience_type='guardian' AND gpm.status='active'
                      JOIN production_memberships spm ON spm.production_id=gpm.production_id AND spm.user_id=fr.student_user_id AND spm.audience_type='student' AND spm.status='active'
                      JOIN users student ON student.id=spm.user_id AND student.active=1 AND student.account_status<>'disabled'
                      JOIN production_group_members pgm ON pgm.group_id=pg.id AND pgm.production_membership_id=spm.id AND pgm.status='active'
                      WHERE fr.guardian_user_id=:user_guardian AND fr.status='active' AND gpm.production_id=c.production_id
                  )
              )
            LIMIT 1");
        $group->execute(['channel'=>$channelId,'user_direct'=>$userId,'user_guardian'=>$userId]);
        return (bool)$group->fetchColumn();
    }
}
