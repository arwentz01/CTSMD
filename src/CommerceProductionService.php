<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
/** Read-only operational projection. Physical inventory and financial settlement stay separate. */
final class CommerceProductionService
{
    private static function rows(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute($args);return$q->fetchAll();}
    public static function productions(PDO $db,array $u,int $org):array
    {
        $caps=[];foreach(['inventory','report','sell','checkin'] as $cap)$caps[$cap]=CommerceAccess::can($db,$u,$org,$cap);if(!array_filter($caps))throw new RuntimeException('Commerce staff access is required.');
        $owner=CommerceStore::organization($db,$org);$primary=$owner['organization_key']===CommerceAccess::primaryKey();
        $productions=self::rows($db,"SELECT p.id,p.title,p.lifecycle_stage,p.announcement_published_at,EXISTS(SELECT 1 FROM production_artwork a WHERE a.production_id=p.id AND a.role='poster') has_poster FROM productions p WHERE EXISTS(SELECT 1 FROM commerce_performances c WHERE c.production_id=p.id AND c.organization_id=?) OR (?=1 AND p.lifecycle_stage IN ('planning','upcoming','in_production','performing')) ORDER BY p.title",[$org,$primary?1:0]);
        $shows=self::rows($db,"SELECT c.*,v.name venue,s.name space_name FROM commerce_performances c JOIN commerce_layouts l ON l.id=c.layout_id AND l.organization_id=c.organization_id JOIN commerce_spaces s ON s.id=l.space_id AND s.organization_id=l.organization_id JOIN commerce_venues v ON v.id=s.venue_id AND v.organization_id=s.organization_id WHERE c.organization_id=? ORDER BY COALESCE(c.starts_at_utc,c.starts_at),c.id",[$org]);
        // Expired unpaid holds are effectively free. Uncertain/payment-pending holds remain protected.
        $counts=self::rows($db,"SELECT i.performance_id,COUNT(*) capacity,SUM(i.state='sold') sold,SUM(i.state IN ('available','released') OR (i.state='held' AND o.state='held' AND o.expires_at<=NOW())) free,SUM(i.state='held' AND NOT(COALESCE(o.state='held' AND o.expires_at<=NOW(),0))) held,SUM(i.state IN ('blocked','house','complimentary')) blocked FROM commerce_inventory i LEFT JOIN commerce_orders o ON o.id=i.order_id AND o.organization_id=i.organization_id WHERE i.organization_id=? GROUP BY i.performance_id",[$org]);$counts=array_column($counts,null,'performance_id');
        $revenue=[];if($caps['report']){$revenue=self::rows($db,"SELECT o.performance_id,o.currency,SUM(CASE WHEN a.provider='stripe_test' THEN GREATEST(0,a.captured_minor-a.refunded_minor) WHEN o.state='paid' THEN o.total_minor ELSE 0 END) net_minor,SUM(CASE WHEN a.provider='stripe_test' THEN a.refunded_minor WHEN o.state='refunded' THEN o.total_minor ELSE 0 END) refunded_minor FROM commerce_orders o LEFT JOIN commerce_payment_attempts a ON a.order_id=o.id AND a.organization_id=o.organization_id WHERE o.organization_id=? GROUP BY o.performance_id,o.currency",[$org]);$revenue=array_column($revenue,null,'performance_id');}
        $grouped=[];foreach($productions as $p)$grouped[$p['id']]=$p+['shows'=>[],'totals'=>['capacity'=>0,'sold'=>0,'free'=>0,'sellable'=>0,'held'=>0,'blocked'=>0],'revenue'=>[],'venues'=>[],'past'=>true];
        foreach($shows as $show){$n=$counts[$show['id']]??[];foreach(['capacity','sold','free','held','blocked'] as $key)$show[$key]=(int)($n[$key]??0);$show['problem']=!$owner['ticketing_enabled']?'Ticketing is disabled.':CommercePerformancePolicy::problem($db,$org,$show);$show['sellable']=$show['problem']===null?$show['free']:0;
            $show['sold_out']=$show['capacity']>0&&$show['sold']===$show['capacity'];$show['readiness']=$show['problem']??($show['sold_out']?'Sold out':($show['free']>0?'On sale':($show['held']>0?'Temporarily unavailable · held seats':'Unavailable · allocated seats')));
            if($caps['report']){$show['net_minor']=(int)($revenue[$show['id']]['net_minor']??0);$show['refunded_minor']=(int)($revenue[$show['id']]['refunded_minor']??0);}
            $p=&$grouped[$show['production_id']];$p['shows'][]=$show;$p['venues'][$show['venue'].' / '.$show['space_name']]=true;foreach(array_keys($p['totals']) as $key)$p['totals'][$key]+=$show[$key];if($caps['report'])$p['revenue'][$show['currency']]=($p['revenue'][$show['currency']]??0)+$show['net_minor'];if($show['starts_at_utc']&&$show['starts_at_utc']>gmdate('Y-m-d H:i:s')&&!in_array($show['schedule_status'],['cancelled','completed'],true))$p['past']=false;unset($p);
        }
        foreach($grouped as &$p){if(!$p['shows']&&in_array($p['lifecycle_stage'],['planning','upcoming','in_production','performing'],true))$p['past']=false;$p['venues']=array_keys($p['venues']);}unset($p);return array_values($grouped);
    }
}
