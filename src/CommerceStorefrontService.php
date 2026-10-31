<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
/** Preview publication is separate from both module enablement and future public activation. */
final class CommerceStorefrontService
{
    public static function publicSalesActive():bool
    {
        // Activation request is separate from module/preview enablement. The only current
        // adapter is simulated, so a requested live activation still fails closed.
        $requested=getenv('COMMERCE_PUBLIC_SALES_ENABLED')==='1';
        $verifiedRealProviderAvailable=false;
        return $requested&&$verifiedRealProviderAvailable;
    }
    public static function approve(PDO $db,array $u,int $org,int $performance,bool $enabled):void
    {
        CommerceAccess::demand($db,$u,$org,'configure');
        CommerceStore::tx($db,function()use($db,$u,$org,$performance,$enabled){$p=CommerceStore::row($db,'performances',$org,$performance,true);ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);$db->prepare('INSERT INTO commerce_storefront_performances(organization_id,performance_id,preview_approved,approved_by_user_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE preview_approved=VALUES(preview_approved),approved_by_user_id=VALUES(approved_by_user_id)')->execute([$org,$performance,$enabled?1:0,$u['id']]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.storefront_preview_approval','performance',$performance,['approved'=>$enabled]);});
    }
    public static function performances(PDO $db,array $u,int $org,int $production=0):array
    {
        CommerceAccess::demand($db,$u,$org,'test');CommerceStore::organization($db,$org,true);
        $q=$db->prepare("SELECT c.*,p.title,v.name venue,v.location,EXISTS(SELECT 1 FROM production_artwork a WHERE a.production_id=p.id AND a.role='poster') has_poster FROM commerce_performances c JOIN commerce_storefront_performances a ON a.performance_id=c.id AND a.organization_id=c.organization_id AND a.preview_approved=1 JOIN productions p ON p.id=c.production_id JOIN commerce_layouts l ON l.id=c.layout_id AND l.organization_id=c.organization_id JOIN commerce_spaces s ON s.id=l.space_id AND s.organization_id=c.organization_id JOIN commerce_venues v ON v.id=s.venue_id AND v.organization_id=c.organization_id WHERE c.organization_id=? AND (?=0 OR p.id=?) AND p.announcement_published_at IS NOT NULL AND p.lifecycle_stage IN ('upcoming','planning','in_production','performing') ORDER BY c.starts_at,c.id");$q->execute([$org,$production,$production]);return $q->fetchAll();
    }
    public static function demandPerformance(PDO $db,array $u,int $org,int $performance):void
    {
        foreach(self::performances($db,$u,$org) as $p)if((int)$p['id']===$performance)return;
        throw new RuntimeException('This performance is not available in the ticket storefront.');
    }
    public static function link(PDO $db,array $u,int $org,int $order,string $ownership):string
    {
        $o=CommerceInventoryService::own($db,$u,$org,$order,$ownership);if($o['state']!=='paid')throw new RuntimeException('Only confirmed orders have retrieval links.');$token=bin2hex(random_bytes(32));$db->prepare('INSERT INTO commerce_order_links(organization_id,order_id,token_hash,expires_at,created_by_user_id) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR),?)')->execute([$org,$order,hash('sha256',$token),$u['id']]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.order_link_created','order',$order,['expires_in_hours'=>24]);return $token;
    }
    public static function retrieve(PDO $db,array $u,int $org,string $token):array
    {
        CommerceAccess::demand($db,$u,$org,'test');if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('This order link is unavailable or expired.');
        $q=$db->prepare('SELECT o.* FROM commerce_order_links l JOIN commerce_orders o ON o.id=l.order_id AND o.organization_id=l.organization_id WHERE l.organization_id=? AND l.token_hash=? AND l.expires_at>NOW() AND l.revoked_at IS NULL');$q->execute([$org,hash('sha256',$token)]);return $q->fetch()?:throw new RuntimeException('This order link is unavailable or expired.');
    }
}
