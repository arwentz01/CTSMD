<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
/** Preview publication is separate from both module enablement and future public activation. */
final class CommerceStorefrontService
{
    /** Independent launch gates. A test provider can never satisfy live-provider readiness. */
    public static function readiness(array $organization=[]):array
    {
        $gates=['commerce_enabled'=>!empty($organization['ticketing_enabled']),
            'preview_enabled'=>getenv('COMMERCE_STOREFRONT_PREVIEW_ENABLED')!=='0',
            'public_sales_requested'=>getenv('COMMERCE_PUBLIC_SALES_ENABLED')==='1',
            'public_sales_approved'=>getenv('COMMERCE_PUBLIC_SALES_APPROVED')==='1',
            'organization_activated'=>!empty($organization['public_sales_enabled']),
            'verified_live_provider'=>false]; // This release implements simulation and Stripe TEST only.
        $gates['active']=$gates['commerce_enabled']&&$gates['public_sales_requested']&&$gates['public_sales_approved']&&$gates['organization_activated']&&$gates['verified_live_provider'];return $gates;
    }
    public static function publicSalesActive():bool{return self::readiness()['active'];}
    public static function approve(PDO $db,array $u,int $org,int $performance,bool $enabled):void
    {
        CommerceAccess::demand($db,$u,$org,'configure');
        CommerceStore::tx($db,function()use($db,$u,$org,$performance,$enabled){$p=CommerceStore::row($db,'performances',$org,$performance,true);ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);$db->prepare('INSERT INTO commerce_storefront_performances(organization_id,performance_id,preview_approved,approved_by_user_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE preview_approved=VALUES(preview_approved),approved_by_user_id=VALUES(approved_by_user_id)')->execute([$org,$performance,$enabled?1:0,$u['id']]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.storefront_preview_approval','performance',$performance,['approved'=>$enabled]);});
    }
    public static function performances(PDO $db,array $u,int $org,int $production=0):array
    {
        CommerceAccess::demand($db,$u,$org,'test');CommerceStore::organization($db,$org,true);
        $q=$db->prepare("SELECT c.*,p.title,CONCAT(v.name,' / ',s.name) venue,v.location,EXISTS(SELECT 1 FROM production_artwork a WHERE a.production_id=p.id AND a.role='poster') has_poster FROM commerce_performances c JOIN commerce_storefront_performances a ON a.performance_id=c.id AND a.organization_id=c.organization_id AND a.preview_approved=1 JOIN productions p ON p.id=c.production_id JOIN commerce_layouts l ON l.id=c.layout_id AND l.organization_id=c.organization_id JOIN commerce_spaces s ON s.id=l.space_id AND s.organization_id=c.organization_id JOIN commerce_venues v ON v.id=s.venue_id AND v.organization_id=c.organization_id WHERE c.organization_id=? AND (?=0 OR p.id=?) AND c.schedule_status='scheduled' AND c.starts_at_utc>UTC_TIMESTAMP() AND EXISTS(SELECT 1 FROM commerce_showtime_slots t WHERE t.organization_id=c.organization_id AND t.performance_id=c.id AND t.starts_at_utc=c.starts_at_utc) AND p.announcement_published_at IS NOT NULL AND p.lifecycle_stage IN ('upcoming','planning','in_production','performing') ORDER BY c.starts_at,c.starts_at_utc,c.id");$q->execute([$org,$production,$production]);return $q->fetchAll();
    }
    public static function demandPerformance(PDO $db,array $u,int $org,int $performance):void
    {
        foreach(self::performances($db,$u,$org) as $p)if((int)$p['id']===$performance)return;
        throw new RuntimeException('This performance is not available in the ticket storefront.');
    }
    public static function link(PDO $db,array $u,int $org,int $order,string $ownership):string
    {
        CommerceInventoryService::own($db,$u,$org,$order,$ownership);return CommerceStore::tx($db,fn()=>self::issueLinkLocked($db,$org,$order,(int)$u['id']));
    }
    public static function issueLinkLocked(PDO $db,int $org,int $order,int $actor):string
    {
        if(!$db->inTransaction())throw new LogicException('Retrieval link requires a transaction.');$o=CommerceStore::row($db,'orders',$org,$order,true);if($o['state']!=='paid')throw new RuntimeException('Only confirmed orders have retrieval links.');$token=bin2hex(random_bytes(32));$db->prepare('INSERT INTO commerce_order_links(organization_id,order_id,token_hash,expires_at,expires_at_utc,created_by_user_id) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR),?)')->execute([$org,$order,hash('sha256',$token),$actor]);CommerceStore::audit($db,$org,$actor,'commerce.order_link_created','order',$order,['expires_in_hours'=>24]);return$token;
    }
    public static function retrieve(PDO $db,array $u,int $org,string $token):array
    {
        CommerceAccess::demand($db,$u,$org,'test');if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('This order link is unavailable or expired.');
        $q=$db->prepare('SELECT o.* FROM commerce_order_links l JOIN commerce_orders o ON o.id=l.order_id AND o.organization_id=l.organization_id WHERE l.organization_id=? AND l.token_hash=? AND ((l.expires_at_utc IS NOT NULL AND l.expires_at_utc>UTC_TIMESTAMP()) OR (l.expires_at_utc IS NULL AND l.expires_at>NOW())) AND l.revoked_at IS NULL');$q->execute([$org,hash('sha256',$token)]);return $q->fetch()?:throw new RuntimeException('This order link is unavailable or expired.');
    }
}
