<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
require_once __DIR__.'/StorageService.php';
/** Read-only operational projection. Physical inventory and financial settlement stay separate. */
final class CommerceProductionService
{
    private static function rows(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute($args);return$q->fetchAll();}
    /** Same visibility boundary as production management; never a public-artwork bypass. */
    public static function poster(PDO $db,array $u,int $org,int $production):?array
    {
        CommerceAccess::demand($db,$u,$org,'inventory');$owner=CommerceStore::organization($db,$org);
        $rows=self::rows($db,"SELECT sv.* FROM productions p JOIN production_artwork a ON a.production_id=p.id AND a.role='poster' JOIN stored_files f ON f.id=a.stored_file_id AND f.status='active' JOIN stored_file_versions sv ON sv.stored_file_id=f.id WHERE p.id=? AND (EXISTS(SELECT 1 FROM commerce_performances c WHERE c.organization_id=? AND c.production_id=p.id) OR (?=1 AND p.lifecycle_stage IN ('planning','upcoming','in_production','performing'))) ORDER BY sv.version_number DESC LIMIT 1",[$production,$org,$owner['organization_key']===CommerceAccess::primaryKey()?1:0]);
        $file=$rows[0]??null;return$file&&in_array($file['mime_type'],['image/png','image/jpeg','image/webp'],true)?$file:null;
    }
    public static function productions(PDO $db,array $u,int $org,bool $includePastEmpty=false):array
    {
        $caps=[];foreach(['inventory','report','sell','checkin'] as $cap)$caps[$cap]=CommerceAccess::can($db,$u,$org,$cap);if(!array_filter($caps))throw new RuntimeException('Commerce staff access is required.');
        $owner=CommerceStore::organization($db,$org);$primary=$owner['organization_key']===CommerceAccess::primaryKey();
        $productions=self::rows($db,"SELECT p.id,p.title,p.lifecycle_stage,p.announcement_published_at,EXISTS(SELECT 1 FROM production_artwork a WHERE a.production_id=p.id AND a.role='poster') has_poster FROM productions p WHERE EXISTS(SELECT 1 FROM commerce_performances c WHERE c.production_id=p.id AND c.organization_id=?) OR (?=1 AND (p.lifecycle_stage IN ('planning','upcoming','in_production','performing') OR (?=1 AND p.lifecycle_stage IN ('completed','archived')))) ORDER BY p.title",[$org,$primary?1:0,$includePastEmpty?1:0]);
        $shows=self::rows($db,"SELECT c.*,v.name venue,s.name space_name FROM commerce_performances c JOIN commerce_layouts l ON l.id=c.layout_id AND l.organization_id=c.organization_id JOIN commerce_spaces s ON s.id=l.space_id AND s.organization_id=l.organization_id JOIN commerce_venues v ON v.id=s.venue_id AND v.organization_id=s.organization_id WHERE c.organization_id=? ORDER BY c.starts_at,COALESCE(c.starts_at_utc,c.starts_at),c.id",[$org]);
        // Expired unpaid holds are effectively free. Uncertain/payment-pending holds remain protected.
        $counts=self::rows($db,"SELECT i.performance_id,COUNT(*) capacity,SUM(i.state='sold') sold,SUM(i.state IN ('available','released') OR (i.state='held' AND o.state='held' AND o.expires_at<=NOW())) free,SUM(i.state='held' AND NOT(COALESCE(o.state='held' AND o.expires_at<=NOW(),0))) held,SUM(i.state IN ('blocked','house','complimentary')) blocked FROM commerce_inventory i LEFT JOIN commerce_orders o ON o.id=i.order_id AND o.organization_id=i.organization_id WHERE i.organization_id=? GROUP BY i.performance_id",[$org]);$counts=array_column($counts,null,'performance_id');
        $revenue=[];if($caps['report']){
            $schema=self::rows($db,"SELECT COUNT(*) n FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='commerce_shopping_purchases' AND column_name='refunded_minor'");$combined=(int)$schema[0]['n']===1;
            $exclude=$combined?" AND NOT EXISTS(SELECT 1 FROM commerce_shopping_purchase_orders x WHERE x.organization_id=o.organization_id AND x.order_id=o.id)":'';
            $revenue=self::rows($db,"SELECT o.performance_id,o.currency,SUM(CASE WHEN a.provider='stripe_test' THEN GREATEST(0,a.captured_minor-a.refunded_minor) WHEN o.state='paid' THEN o.total_minor ELSE 0 END) net_minor,SUM(CASE WHEN a.provider='stripe_test' THEN a.refunded_minor WHEN o.state='refunded' THEN o.total_minor ELSE 0 END) refunded_minor FROM commerce_orders o LEFT JOIN commerce_payment_attempts a ON a.order_id=o.id AND a.organization_id=o.organization_id WHERE o.organization_id=?".$exclude." GROUP BY o.performance_id,o.currency",[$org]);$revenue=array_column($revenue,null,'performance_id');
            if($combined){
                // A purchase-wide refund is apportioned by order total, with exact integer remainders.
                $rows=self::rows($db,"SELECT x.purchase_id,o.id,o.performance_id,o.currency,o.total_minor,p.total_minor purchase_total,p.refunded_minor FROM commerce_shopping_purchase_orders x JOIN commerce_orders o ON o.id=x.order_id AND o.organization_id=x.organization_id JOIN commerce_shopping_purchases p ON p.id=x.purchase_id AND p.organization_id=x.organization_id WHERE x.organization_id=? AND p.captured_minor=p.total_minor ORDER BY x.purchase_id,o.id",[$org]);$cumulative=[];
                foreach($rows as $row){$pid=(int)$row['purchase_id'];$before=$cumulative[$pid]??0;$after=$before+(int)$row['total_minor'];$cumulative[$pid]=$after;$whole=(int)$row['purchase_total'];$returned=min($whole,(int)$row['refunded_minor']);$part=$whole?intdiv($returned*$after,$whole)-intdiv($returned*$before,$whole):0;$performance=(int)$row['performance_id'];if(!isset($revenue[$performance]))$revenue[$performance]=['performance_id'=>$performance,'currency'=>$row['currency'],'net_minor'=>0,'refunded_minor'=>0];$revenue[$performance]['net_minor']+=(int)$row['total_minor']-$part;$revenue[$performance]['refunded_minor']+=$part;if($returned>0&&$returned<$whole)$revenue[$performance]['refunds_allocated']=true;}
            }
        }
        $grouped=[];foreach($productions as $p)$grouped[$p['id']]=$p+['shows'=>[],'totals'=>['capacity'=>0,'sold'=>0,'free'=>0,'sellable'=>0,'held'=>0,'blocked'=>0],'revenue'=>[],'venues'=>[],'past'=>true];
        foreach($shows as $show){$n=$counts[$show['id']]??[];foreach(['capacity','sold','free','held','blocked'] as $key)$show[$key]=(int)($n[$key]??0);$show['problem']=!$owner['ticketing_enabled']?'Ticketing is disabled.':CommercePerformancePolicy::problem($db,$org,$show);$show['sellable']=$show['problem']===null?$show['free']:0;
            $show['sold_out']=$show['capacity']>0&&$show['sold']===$show['capacity'];
            $show['readiness']=$show['problem']??($show['free']>0?'On sale':($show['held']>0?'Temporarily unavailable · held seats':'Unavailable · allocated seats'));
            if($show['sold_out'])$show['readiness']='Sold out'.($show['problem']?' · '.$show['problem']:'');
            if($caps['report']){$show['net_minor']=(int)($revenue[$show['id']]['net_minor']??0);$show['refunded_minor']=(int)($revenue[$show['id']]['refunded_minor']??0);$show['refunds_allocated']=!empty($revenue[$show['id']]['refunds_allocated']);}
            $p=&$grouped[$show['production_id']];$p['shows'][]=$show;$p['venues'][$show['venue'].' / '.$show['space_name']]=true;foreach(array_keys($p['totals']) as $key)$p['totals'][$key]+=$show[$key];if($caps['report'])$p['revenue'][$show['currency']]=($p['revenue'][$show['currency']]??0)+$show['net_minor'];if($show['starts_at_utc']&&$show['starts_at_utc']>gmdate('Y-m-d H:i:s')&&!in_array($show['schedule_status'],['cancelled','completed'],true))$p['past']=false;unset($p);
        }
        foreach($grouped as &$p){if(!$p['shows']&&in_array($p['lifecycle_stage'],['planning','upcoming','in_production','performing'],true))$p['past']=false;$p['venues']=array_keys($p['venues']);}unset($p);return array_values($grouped);
    }
}
