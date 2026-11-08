<?php
declare(strict_types=1);
require_once __DIR__.'/AccessPolicy.php';

/** Live membership review queue, shared by web and mobile Action Centers. */
final class MembershipReviewQueue
{
    public static function pending(PDO $db,array $reviewer,int $limit=50):array
    {
        if(!AccessPolicy::canManageAccounts($reviewer))return [];
        $limit=max(1,min(100,$limit));
        $query=$db->query("SELECT id,first_name,last_name,CONCAT(first_name,' ',last_name) name, email,self_registered_at
            FROM users WHERE active=1 AND account_status='active'
            AND organization_membership_status='pending'
            AND self_registered_at IS NOT NULL AND email_verified_at IS NOT NULL
            ORDER BY self_registered_at ASC,id ASC LIMIT ".$limit);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function count(PDO $db,array $reviewer):int
    {
        if(!AccessPolicy::canManageAccounts($reviewer))return 0;
        return (int)$db->query("SELECT COUNT(*) FROM users
            WHERE active=1 AND account_status='active'
            AND organization_membership_status='pending'
            AND self_registered_at IS NOT NULL AND email_verified_at IS NOT NULL")->fetchColumn();
    }
}
