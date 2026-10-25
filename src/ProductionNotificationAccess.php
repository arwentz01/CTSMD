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
        if(($notification['source_type']??'')!=='casting_release')return true;
        $productionId=(int)($notification['source_id']??0);
        return $productionId>0 && ProductionContext::isActiveMember($db,$recipientId,$productionId);
    }

    public static function filter(PDO $db,int $recipientId,array $rows):array
    {
        return array_values(array_filter($rows,static fn(array $row):bool=>self::visible($db,$recipientId,$row)));
    }
}
