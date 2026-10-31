<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceVenueService.php';
require_once __DIR__.'/ProductionLifecycleGuard.php';
require_once __DIR__.'/CommercePerformancePolicy.php';
final class CommerceInventoryService
{
    /** Test-only customer policy; exact approved pilot inboxes supplement synthetic addresses. */
    public static function testEmailAllowed(string $email):bool
    {
        $email=strtolower(trim($email));if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
        if(str_ends_with($email,'.invalid'))return true;
        $approved=array_map('strtolower',array_map('trim',explode(',',(string)getenv('COMMERCE_MAIL_TEST_RECIPIENTS'))));
        return getenv('COMMERCE_MAIL_APPROVED')==='1'&&in_array($email,$approved,true);
    }
    public static function prices(array $prices,array $seats):array
    {
        if(!$prices||count($prices)>100)throw new RuntimeException('Provide prices for each seat zone.');
        foreach($prices as $zone=>$categories){if(!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',(string)$zone)||!is_array($categories)||!$categories||count($categories)>12)throw new RuntimeException('Invalid pricing zone.');foreach($categories as $category=>$minor)if(!preg_match('/^[a-zA-Z0-9 _-]{1,60}$/',(string)$category)||!is_int($minor)||$minor<0||$minor>1000000)throw new RuntimeException('Prices use integer minor units, between 0 and 1000000.');}
        foreach($seats as $seat)if(!isset($prices[$seat['zone']]))throw new RuntimeException('Every seat zone needs a price.');return$prices;
    }
    public static function create(PDO $db,array $u,int $org,int $production,int $layout,string $starts,array $prices,string $offset=''):int
    {
        CommerceAccess::demand($db,$u,$org,'inventory');$time=CommercePerformancePolicy::resolve($starts,CommercePerformancePolicy::venueTimezone($db,$org,$layout),$offset);
        return CommerceStore::tx($db,fn()=>self::createLocked($db,$u,$org,$production,$layout,$time,$prices));
    }
    /** Batch scheduling reuses this exact inventory creator within its outer transaction. */
    public static function createLocked(PDO $db,array $u,int $org,int $production,int $layout,array $time,array $prices):int
    {
        if(!$db->inTransaction())throw new LogicException('Performance creation requires a transaction.');CommerceAccess::demand($db,$u,$org,'inventory');$owner=CommerceStore::organization($db,$org,true);
        if($owner['organization_key']!==CommerceAccess::primaryKey())throw new RuntimeException('Production linking is limited to this installation owner until backend tenant isolation exists.');
        $resolved=CommercePerformancePolicy::resolve(str_replace(' ','T',substr($time['local'],0,16)),CommercePerformancePolicy::venueTimezone($db,$org,$layout),$time['offset']);if($resolved!==$time)throw new RuntimeException('Venue timezone changed. Review the schedule again.');if($time['utc']<=gmdate('Y-m-d H:i:s'))throw new RuntimeException('Create a future performance time.');
        ProductionLifecycleGuard::assertWritable($db,$production,true);$l=CommerceStore::row($db,'layouts',$org,$layout,true);if($l['status']!=='published')throw new RuntimeException('Choose a published seating layout.');$doc=json_decode($l['document_json'],true,flags:JSON_THROW_ON_ERROR);$seats=array_values(array_filter($doc['items'],fn($i)=>$i['kind']==='seat'));self::prices($prices,$seats);
        $q=$db->prepare('SELECT id FROM commerce_spaces WHERE organization_id=? AND id=? FOR UPDATE');$q->execute([$org,$l['space_id']]);
        // Legacy records must be reviewed before accepting new bookings in the same space.
        $q=$db->prepare('SELECT 1 FROM commerce_performances p JOIN commerce_layouts l ON l.id=p.layout_id AND l.organization_id=p.organization_id WHERE p.organization_id=? AND l.space_id=? AND p.starts_at_utc IS NULL LIMIT 1');$q->execute([$org,$l['space_id']]);if($q->fetchColumn())throw new RuntimeException('Review historical showtimes in this auditorium before creating additional performances.');
        $q=$db->prepare('SELECT 1 FROM commerce_showtime_slots WHERE organization_id=? AND space_id=? AND starts_at_utc=?');$q->execute([$org,$l['space_id'],$time['utc']]);if($q->fetchColumn())throw new RuntimeException('This auditorium already has a performance at that exact showtime.');
        $db->prepare('INSERT INTO commerce_performances(organization_id,production_id,layout_id,starts_at,starts_at_utc,timezone_name,utc_offset_minutes,prices_json,currency) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$org,$production,$layout,$time['local'],$time['utc'],$time['timezone'],$time['offset_minutes'],json_encode($prices),$owner['currency']]);$id=(int)$db->lastInsertId();
        $db->prepare('INSERT INTO commerce_showtime_slots(organization_id,space_id,starts_at_utc,performance_id) VALUES(?,?,?,?)')->execute([$org,$l['space_id'],$time['utc'],$id]);
        foreach($seats as $seat){$seat['section_name']=self::name($doc['sections'],$seat['section']);$seat['row_name']=self::name($doc['rows'],$seat['row']);$db->prepare('INSERT INTO commerce_inventory(organization_id,performance_id,seat_key,seat_json) VALUES(?,?,?,?)')->execute([$org,$id,$seat['key'],json_encode($seat)]);}
        CommerceStore::audit($db,$org,(int)$u['id'],'commerce.performance_created','performance',$id,['layout'=>$layout,'seats'=>count($seats),'showtime'=>$time]);return$id;
    }
    private static function name(array $items,string $key):string{foreach($items as $i)if($i['key']===$key)return$i['name'];throw new RuntimeException('Missing layout label.');}
    public static function inventory(PDO $db,int $org,int $performance):array
    {$q=$db->prepare('SELECT * FROM commerce_inventory WHERE organization_id=? AND performance_id=? ORDER BY seat_key');$q->execute([$org,$performance]);return$q->fetchAll();}
    public static function configure(PDO $db,array $u,int $org,int $id,array $prices,string $sales,bool $checkin,int $fee,int $tax,int $discount):void
    {
        CommerceAccess::demand($db,$u,$org,'inventory');if(!in_array($sales,['open','closed'],true)||$fee<0||$fee>100000||$tax<0||$tax>10000||$discount<0||$discount>1000000)throw new RuntimeException('Check sales status, fees, tax and discount amounts.');
        CommerceStore::tx($db,function()use($db,$u,$org,$id,$prices,$sales,$checkin,$fee,$tax,$discount){$p=CommerceStore::row($db,'performances',$org,$id,true);ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);if($sales==='open')CommercePerformancePolicy::demand($db,$org,$p,false);self::prices($prices,array_map(fn($i)=>json_decode($i['seat_json'],true),self::inventory($db,$org,$id)));$db->prepare('UPDATE commerce_performances SET prices_json=?,sales_status=?,checkin_enabled=?,fee_minor=?,tax_basis_points=?,discount_minor=? WHERE id=? AND organization_id=?')->execute([json_encode($prices),$sales,$checkin?1:0,$fee,$tax,$discount,$id,$org]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.performance_configured','performance',$id,['sales'=>$sales,'checkin'=>$checkin]);});
    }
    public static function allocate(PDO $db,array $u,int $org,int $id,array $keys,string $state,string $reason):void
    {
        self::allocateMany($db,$u,$org,[$id],$keys,$state,$reason);
    }
    public static function allocationPreview(PDO $db,array $u,int $org,array $performances,array $keys,string $state,string $reason):array
    {
        CommerceAccess::demand($db,$u,$org,'inventory');$performances=array_values(array_unique(array_map('intval',$performances)));sort($performances);$keys=array_values(array_unique($keys));foreach($keys as $seat)if(!is_string($seat)||!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$seat))throw new RuntimeException('Choose valid seat identifiers.');sort($keys);if(!$performances||count($performances)>100||!$keys||count($keys)>1500||count($performances)*count($keys)>50000||!in_array($state,['blocked','house','complimentary','released','available'],true)||trim($reason)===''||mb_strlen($reason)>500)throw new RuntimeException('Choose 1–100 performances, valid seats, an allocation and a required reason (maximum 50,000 affected positions).');
        $conflicts=[];$rows=[];foreach($performances as $id){$p=CommerceStore::row($db,'performances',$org,$id);$problem=null;try{ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id']);if($p['schedule_status']!=='scheduled'||!$p['starts_at_utc']||$p['starts_at_utc']<=gmdate('Y-m-d H:i:s'))$problem='Performance is not a reviewed future scheduled performance.';}catch(RuntimeException $e){$problem=$e->getMessage();}$v=$db->prepare("SELECT CONCAT(v.name,' / ',s.name) FROM commerce_layouts l JOIN commerce_spaces s ON s.id=l.space_id AND s.organization_id=l.organization_id JOIN commerce_venues v ON v.id=s.venue_id AND v.organization_id=s.organization_id WHERE l.organization_id=? AND l.id=?");$v->execute([$org,$p['layout_id']]);$venue=(string)$v->fetchColumn();$inventory=array_column(self::inventory($db,$org,$id),null,'seat_key');
            foreach($keys as $seat){$i=$inventory[$seat]??null;$allowed=$state==='released'||$state==='available'?['blocked','house','complimentary']:['available','released',$state];$conflict=$problem??(!$i?'Seat does not exist in this performance.':(!in_array($i['state'],$allowed,true)?'Current state '.$i['state'].' is incompatible.':null));$info=$i?json_decode($i['seat_json'],true):null;$row=['venue'=>$venue,'description'=>$info?$info['section_name'].' / '.$info['row_name'].' / '.$info['label']:'Missing seat','performance'=>$id,'showtime'=>$p['starts_at'],'utc'=>$p['starts_at_utc'],'timezone'=>$p['timezone_name'],'seat'=>$seat,'before'=>$i['state']??'missing','order'=>$i['order_id']??null,'previous_reason'=>$i['reason']??null,'schedule_status'=>$p['schedule_status'],'conflict'=>$conflict];$rows[]=$row;if($conflict)$conflicts[]='Performance #'.$id.', seat '.$seat.': '.$conflict;}}
        $payload=['performances'=>$performances,'seats'=>$keys,'state'=>$state,'reason'=>$reason,'rows'=>$rows];return$payload+['hash'=>hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)),'conflicts'=>$conflicts];
    }
    public static function allocateMany(PDO $db,array $u,int $org,array $performances,array $keys,string $state,string $reason,?string $reviewHash=null,?string $requestKey=null,?array $scope=null):array
    {
        CommerceAccess::demand($db,$u,$org,'inventory');$performances=array_values(array_unique(array_map('intval',$performances)));sort($performances);$keys=array_values(array_unique($keys));sort($keys);$key=$requestKey??bin2hex(random_bytes(16));if(!preg_match('/^[a-f0-9]{32,100}$/',$key))throw new RuntimeException('Reload the allocation preview.');if(!$performances||count($performances)>100)throw new RuntimeException('Choose up to 100 performances.');$requestHash=hash('sha256',json_encode([$performances,$keys,$state,$reason,$reviewHash,$scope],JSON_THROW_ON_ERROR));
        return CommerceStore::tx($db,function()use($db,$u,$org,$performances,$keys,$state,$reason,$reviewHash,$key,$requestHash,$scope){
            foreach($performances as $id)CommerceStore::row($db,'performances',$org,$id,true);
            $q=$db->prepare('SELECT * FROM commerce_allocation_batches WHERE organization_id=? AND actor_user_id=? AND request_key=? FOR UPDATE');$q->execute([$org,$u['id'],$key]);if($prior=$q->fetch()){if(!hash_equals($prior['request_hash'],$requestHash))throw new RuntimeException('Allocation retry does not match its original review.');return json_decode($prior['result_json'],true);}
            foreach($performances as $id){$p=CommerceStore::row($db,'performances',$org,$id);ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);}
            if($scope!==null&&self::allocationScope($db,$u,$org,$scope)!==$performances)throw new RuntimeException('The scope now contains a different set of performances. Review again; nothing was applied.');
            $preview=self::allocationPreview($db,$u,$org,$performances,$keys,$state,$reason);if($preview['conflicts'])throw new RuntimeException(implode(' ',array_slice($preview['conflicts'],0,20)).(count($preview['conflicts'])>20?' Additional conflicts are shown in the preview.':''));if($reviewHash!==null&&!hash_equals($preview['hash'],$reviewHash))throw new RuntimeException('Inventory changed after preview. Review again; nothing was applied.');
            foreach($performances as $id){foreach($keys as $seat)$db->prepare('UPDATE commerce_inventory SET state=?,reason=?,order_id=NULL WHERE organization_id=? AND performance_id=? AND seat_key=?')->execute([$state,$reason,$org,$id,$seat]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.inventory_allocated','performance',$id,['seats'=>$keys,'state'=>$state,'reason'=>$reason,'batch'=>$key,'review_hash'=>$preview['hash']]);}
            $result=['performances'=>$performances,'seats'=>$keys,'state'=>$state,'affected'=>count($preview['rows'])];$db->prepare('INSERT INTO commerce_allocation_batches(organization_id,actor_user_id,request_key,request_hash,result_json) VALUES(?,?,?,?,?)')->execute([$org,$u['id'],$key,$requestHash,json_encode($result)]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.bulk_allocation_applied','allocation',0,['request_key'=>$key,'reason'=>$reason,'before'=>$preview['rows'],'result'=>$result]);return$result;
        });
    }
    public static function allocationScope(PDO $db,array $u,int $org,array $scope):array
    {
        CommerceAccess::demand($db,$u,$org,'inventory');$production=(int)($scope['production']??0);ProductionLifecycleGuard::assertWritable($db,$production);$mode=(string)($scope['mode']??'selected');$args=[$org,$production];$sql='SELECT id FROM commerce_performances WHERE organization_id=? AND production_id=?';
        if($mode==='range'){$from=(string)($scope['from']??'');$to=(string)($scope['to']??'');foreach([$from,$to] as $date){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new RuntimeException('Choose valid date-range boundaries.');}if($from>$to)throw new RuntimeException('The range end must follow its start.');$sql.=" AND schedule_status='scheduled' AND starts_at>=? AND starts_at<DATE_ADD(?,INTERVAL 1 DAY)";$args[]=$from.' 00:00:00';$args[]=$to.' 00:00:00';}
        elseif($mode==='selected'){$ids=array_values(array_unique(array_map('intval',(array)($scope['ids']??[]))));sort($ids);if(!$ids||count($ids)>100)throw new RuntimeException('Select 1–100 performances.');$sql.=' AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')';$args=array_merge($args,$ids);}
        elseif($mode!=='all')throw new RuntimeException('Choose selected performances, a date range or all performances in the production.');
        $q=$db->prepare($sql.' ORDER BY id'.($db->inTransaction()?' FOR UPDATE':''));$q->execute($args);$result=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if($mode==='selected'&&$result!==$ids)throw new RuntimeException('A selected performance does not belong to this production and organization.');if(!$result||count($result)>100)throw new RuntimeException('This scope must contain 1–100 performances. Refine the date range or selection.');return$result;
    }
    /** Caller holds the performance row lock. Pending/exception payments are deliberately excluded. */
    public static function expireLocked(PDO $db,int $org,int $performance,int $actor):void
    {
        $q=$db->prepare("SELECT id FROM commerce_orders WHERE organization_id=? AND performance_id=? AND state='held' AND expires_at<=NOW() FOR UPDATE");$q->execute([$org,$performance]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){$db->prepare("UPDATE commerce_inventory SET state='released',order_id=NULL WHERE organization_id=? AND performance_id=? AND order_id=? AND state='held'")->execute([$org,$performance,$id]);$db->prepare("UPDATE commerce_orders SET state='canceled' WHERE id=? AND organization_id=?")->execute([$id,$org]);CommerceStore::audit($db,$org,$actor,'commerce.hold_expired','order',(int)$id);}
    }
    public static function availability(PDO $db,array $u,int $org,int $performance):array
    {
        CommerceAccess::demand($db,$u,$org,'test');CommerceStore::organization($db,$org,true);
        return CommerceStore::tx($db,function()use($db,$u,$org,$performance){CommerceStore::row($db,'performances',$org,$performance,true);self::expireLocked($db,$org,$performance,(int)$u['id']);return self::inventory($db,$org,$performance);});
    }
    /** Quantity checkout keeps seat/category assignment server-owned and deterministic. */
    public static function admissionMode(PDO $db,array $u,int $org,int $performance,bool $general):void
    {
        CommerceAccess::demand($db,$u,$org,'inventory');CommerceStore::tx($db,fn()=>self::admissionModeLocked($db,$u,$org,$performance,$general));
    }
    public static function admissionModeLocked(PDO $db,array $u,int $org,int $performance,bool $general):void
    {
        if(!$db->inTransaction())throw new LogicException('Seating mode update requires a transaction.');CommerceAccess::demand($db,$u,$org,'inventory');$p=CommerceStore::row($db,'performances',$org,$performance,true);ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);$q=$db->prepare('SELECT 1 FROM commerce_orders WHERE organization_id=? AND performance_id=? LIMIT 1');$q->execute([$org,$performance]);if($q->fetchColumn())throw new RuntimeException('Seating mode cannot change after any order history exists. Create a new performance.');$db->prepare('UPDATE commerce_performances SET seating_mode=? WHERE organization_id=? AND id=?')->execute([$general?'general_admission':'reserved',$org,$performance]);CommerceStore::audit($db,$org,(int)$u['id'],'commerce.seating_mode_configured','performance',$performance,['general_admission'=>$general]);
    }
    public static function holdAdmissions(PDO $db,array $u,int $org,int $performance,array $quantities,string $name,string $email,string $key,string $token):array
    {
        $counts=[];$total=0;foreach($quantities as $zone=>$categories){if(!is_array($categories))throw new RuntimeException('Invalid admission quantities.');foreach($categories as $category=>$count){if((!is_string($count)&&!is_int($count))||!preg_match('/^[0-9]{1,2}$/',(string)$count))throw new RuntimeException('Enter whole admission quantities.');$counts[$zone][$category]=(int)$count;$total+=(int)$count;}ksort($counts[$zone]);}ksort($counts);if($total<1||$total>12)throw new RuntimeException('Choose between 1 and 12 admissions.');
        return self::hold($db,$u,$org,$performance,[],$name,$email,'',$key,$token,$counts,true);
    }
    public static function holdQuantities(PDO $db,array $u,int $org,int $performance,array $seats,array $quantities,string $name,string $email,string $phone,string $key,string $token):array
    {
        $selection=[];foreach($seats as $seat){if(!is_string($seat)||isset($selection[$seat]))throw new RuntimeException('Choose unique seats.');$selection[$seat]='';}
        $counts=[];foreach($quantities as $zone=>$categories){if((!is_string($zone)&&!is_int($zone))||!is_array($categories))throw new RuntimeException('Invalid ticket quantities.');foreach($categories as $category=>$count){if((!is_string($category)&&!is_int($category))||(!is_int($count)&&!is_string($count))||!preg_match('/^[0-9]{1,2}$/',(string)$count)||(int)$count>12)throw new RuntimeException('Ticket quantities must be whole numbers between 0 and 12.');$counts[$zone][$category]=(int)$count;}if(isset($counts[$zone]))ksort($counts[$zone]);}ksort($counts);
        return self::hold($db,$u,$org,$performance,$selection,$name,$email,$phone,$key,$token,$counts);
    }
    private static function assignQuantities(array $inventory,array $prices,array $selection,array $quantities):array
    {
        $zones=[];foreach(array_keys($selection) as $seat){if(!isset($inventory[$seat]))throw new RuntimeException('A selected seat is unavailable. Review the updated map.');$s=json_decode($inventory[$seat]['seat_json'],true);$zones[$s['zone']][]=$seat;}
        foreach($quantities as $zone=>$categories){if(!isset($prices[$zone]))throw new RuntimeException('Choose quantities for an available pricing zone.');foreach($categories as $category=>$count)if(!array_key_exists($category,$prices[$zone]))throw new RuntimeException('Choose an available ticket type.');if(array_sum($categories)!==count($zones[$zone]??[]))throw new RuntimeException('Ticket-type quantities must match the selected seats in each pricing zone.');}
        $assigned=[];foreach($zones as $zone=>$seats){$counts=$quantities[$zone]??[];if(array_sum($counts)!==count($seats))throw new RuntimeException('Ticket-type quantities must match the selected seats in each pricing zone.');sort($seats,SORT_STRING);ksort($counts);$index=0;foreach($counts as $category=>$count)for($i=0;$i<$count;$i++)$assigned[$seats[$index++]]=$category;}
        ksort($assigned);return$assigned;
    }
    public static function hold(PDO $db,array $u,int $org,int $performance,array $selection,string $name,string $email,string $phone,string $key,string $token,?array $quantities=null,bool $admissions=false):array
    {
        CommerceAccess::demand($db,$u,$org,'test');$owner=CommerceStore::organization($db,$org,true);
        $name=trim($name);$email=strtolower(trim($email));if($name===''||mb_strlen($name)>190||!self::testEmailAllowed($email)||mb_strlen($phone)>40)throw new RuntimeException('Enter a purchaser name and a synthetic .invalid email, or an explicitly approved pilot inbox. Phone is optional.');
        if(!preg_match('/^[a-zA-Z0-9_-]{16,100}$/',$key)||!preg_match('/^[a-f0-9]{64}$/',$token)||(!$admissions&&count($selection)<1)||count($selection)>12)throw new RuntimeException('Choose between 1 and 12 seats and retry with a valid checkout token.');
        $normalized=[];foreach($selection as $seat=>$category){if(!is_string($seat)||!is_string($category))throw new RuntimeException('Invalid seat selection.');$normalized[$seat]=$category;}ksort($normalized);
        $requestHash=hash('sha256',json_encode($quantities===null?[$performance,$normalized,$name,$email,$phone]:[$admissions?'admission_checkout':'quantity_checkout',$performance,array_keys($normalized),$quantities,$name,$email,$phone],JSON_THROW_ON_ERROR));
        return CommerceStore::tx($db,function()use($db,$u,$org,$performance,$normalized,$name,$email,$phone,$key,$token,$owner,$requestHash,$quantities,$admissions){
            $p=CommerceStore::row($db,'performances',$org,$performance,true);self::expireLocked($db,$org,$performance,(int)$u['id']);
            $q=$db->prepare('SELECT * FROM commerce_orders WHERE organization_id=? AND tester_user_id=? AND idempotency_key=? FOR UPDATE');$q->execute([$org,$u['id'],$key]);if($prior=$q->fetch()){if(!hash_equals($prior['request_hash'],$requestHash)||!hash_equals($prior['ownership_hash'],hash('sha256',$token)))throw new RuntimeException('Checkout retry does not match its original selection.');return$prior;}
            CommercePerformancePolicy::demand($db,$org,$p);$inventory=array_column(self::inventory($db,$org,$performance),null,'seat_key');$prices=json_decode($p['prices_json'],true);if(($p['seating_mode']==='general_admission')!==$admissions)throw new RuntimeException('Use the configured seating or admission selection for this performance.');if($admissions){foreach($quantities as $zone=>$categories){$remaining=array_sum($categories);foreach($inventory as $seat=>$item){$info=json_decode($item['seat_json'],true);if($remaining>0&&$info['zone']===(string)$zone&&in_array($item['state'],['available','released'],true)){$normalized[$seat]='';--$remaining;}}if($remaining>0)throw new RuntimeException('Not enough admissions remain in that price zone. No admissions were held.');}}if($quantities!==null)$normalized=self::assignQuantities($inventory,$prices,$normalized,$quantities);$lines=[];$subtotal=0;
            foreach($normalized as $seat=>$category){$i=$inventory[$seat]??null;if(!$i||!in_array($i['state'],['available','released'],true))throw new RuntimeException('A selected seat is no longer available. No seats were reserved; review the updated map.');$s=json_decode($i['seat_json'],true);$price=$prices[$s['zone']][$category]??null;if(!is_int($price))throw new RuntimeException('Choose an available ticket category.');$subtotal+=$price;$lines[]=[$seat,$category,$price,$i['seat_json'],$admissions?'General admission · '.$s['zone']:$s['section_name'].' / '.$s['row_name'].' / '.$s['label']];}
            $discount=min($subtotal,(int)$p['discount_minor']);$tax=intdiv(($subtotal-$discount)*(int)$p['tax_basis_points']+5000,10000);$total=$subtotal-$discount+$tax+(int)$p['fee_minor'];
            $db->prepare("INSERT INTO commerce_orders(organization_id,performance_id,tester_user_id,public_reference,ownership_hash,idempotency_key,request_hash,state,expires_at,customer_name,customer_email,customer_phone,currency,subtotal_minor,discount_minor,tax_minor,fee_minor,total_minor,tax_basis_points_snapshot) VALUES(?,?,?,?,?,?,?,'held',DATE_ADD(NOW(),INTERVAL ? SECOND),?,?,?,?,?,?,?,?,?,?)")->execute([$org,$performance,$u['id'],bin2hex(random_bytes(16)),hash('sha256',$token),$key,$requestHash,(int)$owner['hold_seconds'],$name,$email,$phone?:null,$p['currency'],$subtotal,$discount,$tax,$p['fee_minor'],$total,$p['tax_basis_points']]);$id=(int)$db->lastInsertId();
            foreach($lines as [$seat,$category,$price,$json,$desc]){$db->prepare('INSERT INTO commerce_order_lines(organization_id,order_id,seat_key,category,description,unit_minor,seat_json) VALUES(?,?,?,?,?,?,?)')->execute([$org,$id,$seat,$category,$desc,$price,$json]);$db->prepare("UPDATE commerce_inventory SET state='held',order_id=?,reason=NULL WHERE organization_id=? AND performance_id=? AND seat_key=?")->execute([$id,$org,$performance,$seat]);}
            CommerceStore::audit($db,$org,(int)$u['id'],'commerce.hold_created','order',$id,['seats'=>array_keys($normalized),'total_minor'=>$total]);return CommerceStore::row($db,'orders',$org,$id);
        });
    }
    public static function own(PDO $db,array $u,int $org,int $id,string $token):array
    {
        CommerceAccess::demand($db,$u,$org,'test');$o=CommerceStore::row($db,'orders',$org,$id);if((int)$o['tester_user_id']!==(int)$u['id']||!hash_equals($o['ownership_hash'],hash('sha256',$token)))throw new RuntimeException('Order is unavailable for this checkout.');return$o;
    }
}
