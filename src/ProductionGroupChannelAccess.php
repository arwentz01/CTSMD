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
            JOIN production_group_members pgm ON pgm.group_id=pg.id AND pgm.status='active'
            JOIN production_memberships pm ON pm.id=pgm.production_membership_id AND pm.production_id=c.production_id AND pm.user_id=:user AND pm.status='active'
            JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled'
            WHERE c.id=:channel
            LIMIT 1");
        $group->execute(['channel'=>$channelId,'user'=>$userId]);
        return (bool)$group->fetchColumn();
    }
}
