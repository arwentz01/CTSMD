<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
/** Explicit, reviewed inheritance. Performance prices remain the reservation authority. */
final class CommercePricingService
{
    private static function rows(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute($args);return$q->fetchAll();}
    public static function ready(PDO $db):bool
    {return(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('commerce_production_pricing','commerce_performance_pricing','commerce_pricing_batches')")->fetchColumn()===3;}
    public static function organizationReady(PDO $db):bool
    {return(bool)$db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='commerce_organization_pricing'")->fetchColumn();}
    public static function organizationDefaults(PDO $db,int $org):?array
    {
        if(!self::organizationReady($db))return null;
        $rows=self::rows($db,'SELECT * FROM commerce_organization_pricing WHERE organization_id=?'.($db->inTransaction()?' FOR UPDATE':''),[$org]);
        return$rows?['prices'=>json_decode($rows[0]['prices_json'],true),'revision'=>(int)$rows[0]['revision'],'source'=>'organization']:null;
    }
    public static function defaults(PDO $db,int $org,int $production):?array
    {
        $rows=self::rows($db,'SELECT * FROM commerce_production_pricing WHERE organization_id=? AND production_id=?',[$org,$production]);
        return$rows?['prices'=>json_decode($rows[0]['prices_json'],true),'revision'=>(int)$rows[0]['revision'],'source'=>'production']:self::organizationDefaults($db,$org);
    }
    public static function organizationZones(PDO $db,int $org):array
    {
        $zones=[];foreach(self::rows($db,"SELECT document_json FROM commerce_layouts WHERE organization_id=? AND status='published'",[$org]) as $layout)foreach(json_decode($layout['document_json'],true)['items'] as $item)if($item['kind']==='seat')$zones[(string)$item['zone']]=true;
        return array_map('strval',array_keys($zones));
    }
    public static function saveOrganizationDefaults(PDO $db,array $u,int $org,array $prices,string $reason,int $expectedRevision):void
    {
        CommerceAccess::demand($db,$u,$org,'configure');if(!self::organizationReady($db))throw new RuntimeException('Organization pricing requires migration 111.');CommerceInventoryService::prices($prices,[]);
        if(trim($reason)===''||mb_strlen($reason)>500)throw new RuntimeException('Record a reason of up to 500 characters.');
        CommerceStore::tx($db,function()use($db,$u,$org,$prices,$reason,$expectedRevision){
            self::rows($db,'SELECT id FROM commerce_organizations WHERE id=? FOR UPDATE',[$org]);$before=self::organizationDefaults($db,$org);
            if(($before['revision']??0)!==$expectedRevision)throw new RuntimeException('Organization defaults changed. Reload before saving.');
            $zones=self::organizationZones($db,$org);foreach(array_keys($prices) as $zone)if(!in_array((string)$zone,$zones,true))throw new RuntimeException('Choose zones from a published organization layout.');
            $db->prepare('INSERT INTO commerce_organization_pricing(organization_id,prices_json) VALUES(?,?) ON DUPLICATE KEY UPDATE prices_json=VALUES(prices_json),revision=revision+1')->execute([$org,json_encode($prices)]);
            CommerceStore::audit($db,$org,(int)$u['id'],'commerce.organization_pricing_changed','organization',$org,['reason'=>$reason,'before'=>$before,'prices'=>$prices,'existing_prices_unchanged'=>true]);
        });
    }
    private static function access(PDO $db,array $u,int $org,int $production):void
    {
        CommerceAccess::demand($db,$u,$org,'inventory');$owner=CommerceStore::organization($db,$org);
        if($owner['organization_key']!==CommerceAccess::primaryKey())throw new RuntimeException('Production pricing is limited to the configured installation owner.');
        ProductionLifecycleGuard::assertWritable($db,$production);
    }
    public static function parse(array $rows):array
    {
        $prices=[];if(count($rows)>1200)throw new RuntimeException('Too many ticket prices.');
        foreach($rows as $row){if(!is_array($row))throw new RuntimeException('Invalid price row.');$zone=trim((string)($row['zone']??''));$category=trim((string)($row['category']??''));$amount=trim((string)($row['amount']??''));if(!preg_match('/^(0|[1-9][0-9]{0,4})(?:\.([0-9]{1,2}))?$/',$amount,$m))throw new RuntimeException('Enter currency amounts with up to two decimal places.');if(isset($prices[$zone][$category]))throw new RuntimeException('Each category must occur only once in a seating zone.');$prices[$zone][$category]=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');}
        foreach($prices as &$categories)ksort($categories);unset($categories);ksort($prices);return CommerceInventoryService::prices($prices,[]);
    }
    public static function preview(PDO $db,array $u,int $org,int $production,string $mode,array $ids,array $prices,string $reason,bool $acknowledge):array
    {
        self::access($db,$u,$org,$production);if(!in_array($mode,['defaults','inherit','override'],true)||trim($reason)===''||mb_strlen($reason)>500)throw new RuntimeException('Choose a pricing action and record a reason (up to 500 characters).');
        $defaults=self::defaults($db,$org,$production);if($mode==='inherit')$prices=$defaults['prices']??throw new RuntimeException('Set production defaults before enabling inheritance.');CommerceInventoryService::prices($prices,[]);
        if($mode==='defaults')$ids=array_column(self::rows($db,"SELECT p.id FROM commerce_performances p JOIN commerce_performance_pricing b ON b.performance_id=p.id AND b.organization_id=p.organization_id WHERE p.organization_id=? AND p.production_id=? AND b.pricing_mode='inherit' AND p.schedule_status IN ('scheduled','draft') AND p.starts_at_utc>UTC_TIMESTAMP() ORDER BY p.id",[$org,$production]),'id');
        $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);if(count($ids)>100||($mode!=='defaults'&&!$ids))throw new RuntimeException('Select 1–100 performances; divide larger productions into batches.');
        $rows=[];
        foreach($ids as $id){$p=CommerceStore::row($db,'performances',$org,$id);if((int)$p['production_id']!==$production)throw new RuntimeException('A selected performance does not belong to this production.');if(!in_array($p['schedule_status'],['scheduled','draft'],true)||!$p['starts_at_utc']||$p['starts_at_utc']<=gmdate('Y-m-d H:i:s'))throw new RuntimeException('Prices can change only on reviewed future scheduled or draft performances.');
            CommerceInventoryService::prices($prices,array_map(fn($i)=>json_decode($i['seat_json'],true),CommerceInventoryService::inventory($db,$org,$id)));
            $history=self::rows($db,"SELECT COUNT(*) orders_count,COALESCE(SUM(state IN ('paid','refund_pending','exception','payment_pending')),0) protected_orders FROM commerce_orders WHERE organization_id=? AND performance_id=?",[$org,$id])[0];
            if((int)$history['orders_count']&&!$acknowledge)throw new RuntimeException('Acknowledge existing orders: their saved prices remain unchanged.');
            $binding=self::rows($db,'SELECT pricing_mode FROM commerce_performance_pricing WHERE organization_id=? AND performance_id=?',[$org,$id]);
            $rows[]=['id'=>$id,'showtime'=>CommercePerformancePolicy::label($p),'prices'=>json_decode($p['prices_json'],true),'pricing_mode'=>$binding[0]['pricing_mode']??'override','orders'=>(int)$history['orders_count'],'protected_orders'=>(int)$history['protected_orders'],'schedule_status'=>$p['schedule_status'],'sales_status'=>$p['sales_status']];
        }
        $payload=compact('production','mode','ids','prices','reason','acknowledge','rows','defaults');return$payload+['hash'=>hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR))];
    }
    public static function apply(PDO $db,array $u,int $org,array $request,string $hash,string $key):array
    {
        self::access($db,$u,$org,(int)$request['production']);if(!preg_match('/^[a-f0-9]{32}$/',$key))throw new RuntimeException('Reload pricing review.');$requestHash=hash('sha256',json_encode([$request,$hash],JSON_THROW_ON_ERROR));
        return CommerceStore::tx($db,function()use($db,$u,$org,$request,$hash,$key,$requestHash){
            // Shares the production lock used by atomic scheduling; affected seats are never rewritten.
            ProductionLifecycleGuard::assertWritable($db,(int)$request['production'],true);
            $prior=self::rows($db,'SELECT * FROM commerce_pricing_batches WHERE organization_id=? AND actor_user_id=? AND request_key=? FOR UPDATE',[$org,$u['id'],$key]);if($prior){if(!hash_equals($prior[0]['request_hash'],$requestHash))throw new RuntimeException('Pricing retry does not match its review.');return json_decode($prior[0]['result_json'],true);}
            foreach($request['ids'] as $id)CommerceStore::row($db,'performances',$org,(int)$id,true);
            $fresh=self::preview($db,$u,$org,(int)$request['production'],$request['mode'],$request['ids'],$request['prices'],$request['reason'],$request['acknowledge']);if(!hash_equals($fresh['hash'],$hash))throw new RuntimeException('Prices, inheritance, order history or performance scope changed. Review again; nothing was applied.');
            if($request['mode']==='defaults')$db->prepare('INSERT INTO commerce_production_pricing(organization_id,production_id,prices_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE prices_json=VALUES(prices_json),revision=revision+1')->execute([$org,$request['production'],json_encode($fresh['prices'])]);
            foreach($fresh['rows'] as $row){$p=CommerceStore::row($db,'performances',$org,$row['id']);CommerceInventoryService::configureLocked($db,$u,$org,$row['id'],$fresh['prices'],$p['sales_status'],(bool)$p['checkin_enabled'],(int)$p['fee_minor'],(int)$p['tax_basis_points'],(int)$p['discount_minor']);self::bindLocked($db,$org,$row['id'],$request['mode']==='override'?'override':'inherit');CommerceStore::audit($db,$org,(int)$u['id'],'commerce.performance_prices_changed','performance',$row['id'],['reason'=>$request['reason'],'before'=>$row['prices'],'after'=>$fresh['prices'],'mode'=>$request['mode'],'existing_order_count'=>$row['orders'],'order_snapshots_unchanged'=>true]);}
            $result=['performances'=>$fresh['ids'],'mode'=>$request['mode']];CommerceStore::audit($db,$org,(int)$u['id'],'commerce.production_pricing_applied','production',(int)$request['production'],['reason'=>$request['reason'],'before_defaults'=>$fresh['defaults'],'prices'=>$fresh['prices'],'result'=>$result,'review_hash'=>$hash]);$db->prepare('INSERT INTO commerce_pricing_batches VALUES(?,?,?,?,?)')->execute([$org,$u['id'],$key,$requestHash,json_encode($result)]);return$result;
        });
    }
    public static function bindLocked(PDO $db,int $org,int $performance,string $mode):void
    {
        if(!$db->inTransaction()||!in_array($mode,['inherit','override'],true))throw new LogicException('Pricing binding requires a transaction and valid mode.');
        $db->prepare('INSERT INTO commerce_performance_pricing VALUES(?,?,?) ON DUPLICATE KEY UPDATE pricing_mode=VALUES(pricing_mode)')->execute([$org,$performance,$mode]);
    }
}
