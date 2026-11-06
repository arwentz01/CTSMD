<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceShoppingCheckoutService.php';
require_once __DIR__.'/MailService.php';
require_once __DIR__.'/CommerceEmailTemplate.php';

/** Consolidated purchase receipt on the Commerce mail queue. */
final class CommerceShoppingDeliveryService
{
    private static function path(array $purchase):string
    {
        $base=(string)(getenv('COMMERCE_PUBLIC_BASE_PATH')?:getenv('APP_BASE_PATH')?:'');if($base!==''&&!preg_match('~^/[a-zA-Z0-9/_-]+$~',$base))throw new RuntimeException('Invalid ticket link configuration.');return$base.'/tickets/confirmation?'.http_build_query(['purchase'=>$purchase['public_reference']]);
    }
    private static function body(PDO $db,int $org,array $purchase,string $url):array
    {
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $q=$db->prepare("SELECT t.state,l.description,l.category,l.unit_minor,c.starts_at,c.schedule_status,pr.title,CONCAT(v.name,' / ',s.name) venue FROM commerce_shopping_purchase_orders x JOIN commerce_orders o ON o.id=x.order_id AND o.organization_id=x.organization_id JOIN commerce_tickets t ON t.order_id=o.id AND t.organization_id=o.organization_id JOIN commerce_order_lines l ON l.organization_id=t.organization_id AND l.order_id=t.order_id AND l.seat_key=t.seat_key JOIN commerce_performances c ON c.id=o.performance_id AND c.organization_id=o.organization_id JOIN productions pr ON pr.id=c.production_id JOIN commerce_layouts lay ON lay.id=c.layout_id AND lay.organization_id=c.organization_id JOIN commerce_spaces s ON s.id=lay.space_id AND s.organization_id=lay.organization_id JOIN commerce_venues v ON v.id=s.venue_id AND v.organization_id=s.organization_id WHERE x.organization_id=? AND x.purchase_id=? ORDER BY c.starts_at,t.id");
        $q->execute([$org,$purchase['id']]);$tickets=$q->fetchAll();
        if(!$tickets)throw new RuntimeException('Purchase has no tickets.');
        $groups=json_decode($purchase['snapshot_json'],true,flags:JSON_THROW_ON_ERROR);
        $expected=0;$subtotal=0;$discount=0;$tax=0;$fee=0;
        foreach($groups as $group){$expected+=count($group['items']);$subtotal+=(int)$group['subtotal'];$discount+=(int)$group['discount'];$tax+=(int)$group['tax'];$fee+=(int)$group['fee'];}
        if(count($tickets)!==$expected||$subtotal-$discount+$tax+$fee!==(int)$purchase['total_minor'])throw new RuntimeException('Purchase receipt requires review.');
        $brand=OrganizationBranding::fromEnvironment();
        $subject=mb_substr(preg_replace('/[\r\n]+/',' ',$brand['name'].' · Your ticket receipt'),0,250);
        $lines=[$brand['name'].' · Ticket receipt'];
        $html=CommerceEmailTemplate::start($brand,'ORDER CONFIRMATION','Your tickets are ready','Your order is confirmed. Find your performance details, tickets, and order summary below.');
        $previous='';$ticketRows='';
        foreach($tickets as $ticket){
            if($ticket['state']!=='valid'||$ticket['schedule_status']!=='scheduled')throw new RuntimeException('Purchase tickets are not available for delivery.');
            $date=(new DateTimeImmutable($ticket['starts_at'],new DateTimeZone('UTC')))->format('l, F j, Y · g:i A');
            $heading=$ticket['title'].' · '.$date.' · '.$ticket['venue'];
            if($heading!==$previous){
                if($previous!=='')$html.=CommerceEmailTemplate::ticketTable($ticketRows);
                $ticketRows='';
                $lines[]=$heading;
                $html.=CommerceEmailTemplate::section((string)$ticket['title'],'<p style="font-size:13px;line-height:1.6;color:#6b605c;margin:0 0 9px">'.$e($date.' · '.$ticket['venue']).'</p>');
            }
            $price=$purchase['currency'].' '.number_format((int)$ticket['unit_minor']/100,2);
            $lines[]=$ticket['description'].' · '.$ticket['category'].' · '.$price;
            $ticketRows.=CommerceEmailTemplate::ticketRow((string)$ticket['description'],(string)$ticket['category'],$price);
            $previous=$heading;
        }
        $html.=CommerceEmailTemplate::ticketTable($ticketRows);
        if(CommercePromoService::available($db)){
            $promo=$db->prepare("SELECT p.code,c.discount_minor FROM commerce_promo_claims c JOIN commerce_promo_codes p ON p.id=c.promo_id AND p.organization_id=c.organization_id WHERE c.organization_id=? AND c.purchase_id=? AND c.state='redeemed'");
            $promo->execute([$org,$purchase['id']]);$used=$promo->fetch();
            if($used){
                $saving=$purchase['currency'].' '.number_format((int)$used['discount_minor']/100,2);
                $lines[]='Promo code: '.$used['code'].' (saved '.$saving.')';
                $html.='<p style="margin:18px 0 0;background:#f8f3e8;padding:11px 13px;border-radius:5px;font-size:13px;color:#59492b">Promo code <strong>'.$e($used['code']).'</strong> · Saved '.$e($saving).'</p>';
            }
        }
        $html.=CommerceEmailTemplate::section('Order summary','');
        $html.='<table role="table" width="100%" cellpadding="0" cellspacing="0">';
        foreach(['Tickets'=>$subtotal,'Discount'=>$discount,'Tax'=>$tax,'Fees'=>$fee,'Total'=>(int)$purchase['total_minor']] as $label=>$amount){
            $value=($label==='Discount'?'−':'').$purchase['currency'].' '.number_format($amount/100,2);
            $lines[]=$label.': '.$value;
            $html.=CommerceEmailTemplate::amountRow($label,$value,$label==='Total');
        }
        $html.='</table>';
        $html.=CommerceEmailTemplate::finish($brand,'View all tickets',$url,'Sign in with your CTSMD Connect account to view and print your tickets.','Keep this email for your records. Admission is verified using each individual ticket.');
        $lines[]='View all tickets: '.$url;
        $lines[]='Sign in to your account to view or print your tickets.';
        return['subject'=>$subject,'text'=>implode("\n",$lines),'html'=>$html];
    }
    public static function enqueueLocked(PDO $db,int $org,int $purchase,int $actor):void
    {
        if(!$db->inTransaction())throw new LogicException('Purchase delivery requires the fulfillment transaction.');$q=$db->prepare('SELECT * FROM commerce_shopping_purchases WHERE organization_id=? AND id=? FOR UPDATE');$q->execute([$org,$purchase]);$p=$q->fetch();if(!$p||$p['state']!=='succeeded')throw new RuntimeException('Purchase unavailable for delivery.');$q=$db->prepare('SELECT 1 FROM commerce_shopping_deliveries WHERE organization_id=? AND purchase_id=?');$q->execute([$org,$purchase]);if($q->fetchColumn())return;
        $path=self::path($p);$content=self::body($db,$org,$p,$path);$queue=MailService::queue($db,null,$p['customer_email'],$p['customer_name'],'commerce',$content['subject'],$content['text'],$content['html'],'commerce-purchase-'.$org.'-'.$purchase);if(!$queue)throw new RuntimeException('Purchase delivery already exists without its record.');$db->prepare("UPDATE email_queue SET status='commerce_queued' WHERE id=? AND category='commerce'")->execute([$queue]);$q=$db->prepare('INSERT INTO commerce_shopping_deliveries(organization_id,purchase_id,email_queue_id,public_reference,actor_user_id,available_at_utc,created_at_utc) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$q->execute([$org,$purchase,$queue,bin2hex(random_bytes(16)),$actor]);CommerceStore::audit($db,$org,$actor,'commerce.purchase_delivery_queued','cart',(int)$p['cart_id'],['purchase'=>$purchase]);
    }
    public static function invalidateLocked(PDO $db,int $org,int $purchase):void
    {
        if(!$db->inTransaction())throw new LogicException('Delivery invalidation requires a transaction.');$q=$db->prepare('SELECT * FROM commerce_shopping_deliveries WHERE organization_id=? AND purchase_id=? FOR UPDATE');$q->execute([$org,$purchase]);if(!$d=$q->fetch())return;$db->prepare("UPDATE email_queue SET status='suppressed',last_error='purchase_unavailable' WHERE id=? AND status IN ('commerce_queued','commerce_sending')")->execute([$d['email_queue_id']]);$db->prepare("UPDATE commerce_shopping_deliveries SET lease_token=NULL,lease_until_utc=NULL,last_error_code='purchase_unavailable' WHERE id=?")->execute([$d['id']]);
    }
    public static function restoreLocked(PDO $db,int $org,int $purchase):void
    {
        if(!$db->inTransaction())throw new LogicException('Delivery restoration requires a transaction.');$q=$db->prepare('SELECT * FROM commerce_shopping_deliveries WHERE organization_id=? AND purchase_id=? FOR UPDATE');$q->execute([$org,$purchase]);if(!$d=$q->fetch())return;$q=$db->prepare("UPDATE email_queue SET status='commerce_queued',last_error=NULL WHERE id=? AND status='suppressed' AND last_error='purchase_unavailable'");$q->execute([$d['email_queue_id']]);if($q->rowCount())$db->prepare('UPDATE commerce_shopping_deliveries SET last_error_code=NULL,available_at_utc=UTC_TIMESTAMP() WHERE id=?')->execute([$d['id']]);
    }
    public static function process(PDO $db,int $org,int $limit=25,?Closure $transport=null):array
    {
        if(getenv('COMMERCE_MAIL_WORKER_ENABLED')!=='1')throw new RuntimeException('Commerce mail worker is disabled.');$result=['processed'=>0,'captured'=>0,'sent'=>0,'failed'=>0,'suppressed'=>0];$limit=max(1,min(100,$limit));
        $q=$db->prepare('SELECT id FROM commerce_shopping_deliveries WHERE organization_id=? AND lease_token IS NOT NULL AND lease_until_utc<UTC_TIMESTAMP() ORDER BY id LIMIT 100');$q->execute([$org]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)CommerceStore::tx($db,function()use($db,$org,$id){$q=$db->prepare('SELECT * FROM commerce_shopping_deliveries WHERE organization_id=? AND id=? AND lease_until_utc<UTC_TIMESTAMP() FOR UPDATE');$q->execute([$org,$id]);$d=$q->fetch();if(!$d)return;$db->prepare("UPDATE email_queue SET status=IF(attempts>=5,'failed','commerce_queued'),last_error='interrupted_delivery' WHERE id=? AND status='commerce_sending'")->execute([$d['email_queue_id']]);$db->prepare("UPDATE commerce_shopping_deliveries SET lease_token=NULL,lease_until_utc=NULL,last_error_code='interrupted_delivery',available_at_utc=UTC_TIMESTAMP() WHERE id=?")->execute([$id]);});
        for($i=0;$i<$limit;$i++){$job=CommerceStore::tx($db,function()use($db,$org){$q=$db->prepare("SELECT d.* FROM commerce_shopping_deliveries d WHERE d.organization_id=? AND d.available_at_utc<=UTC_TIMESTAMP() AND EXISTS(SELECT 1 FROM email_queue q WHERE q.id=d.email_queue_id AND q.category='commerce' AND q.status='commerce_queued') ORDER BY d.id LIMIT 1 FOR UPDATE");$q->execute([$org]);$d=$q->fetch();if(!$d)return null;$q=$db->prepare("SELECT recipient_email,recipient_name,subject,text_body,html_body,attempts FROM email_queue WHERE id=? AND category='commerce' AND status='commerce_queued' FOR UPDATE");$q->execute([$d['email_queue_id']]);$message=$q->fetch();if(!$message)return null;$lease=bin2hex(random_bytes(32));$db->prepare("UPDATE email_queue SET status='commerce_sending',attempts=attempts+1,last_attempt_at=NOW() WHERE id=?")->execute([$d['email_queue_id']]);$db->prepare('UPDATE commerce_shopping_deliveries SET lease_token=?,lease_until_utc=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE id=?')->execute([$lease,$d['id']]);return array_merge($d,$message,['lease_token'=>$lease]);});if(!$job)break;++$result['processed'];$status='captured';$error=null;
            try{$q=$db->prepare('SELECT * FROM commerce_shopping_purchases WHERE organization_id=? AND id=?');$q->execute([$org,$job['purchase_id']]);$p=$q->fetch();if(!$p||$p['state']!=='succeeded'||$p['customer_email']!==$job['recipient_email']){$status='suppressed';$error='purchase_unavailable';}else{$driver=getenv('COMMERCE_MAIL_DRIVER')?:'capture';if($driver==='smtp'){$approved=array_filter(array_map('strtolower',array_map('trim',explode(',',(string)getenv('COMMERCE_MAIL_TEST_RECIPIENTS')))));if(getenv('COMMERCE_MAIL_ENABLED')!=='1'||getenv('COMMERCE_MAIL_APPROVED')!=='1'||!in_array(strtolower($job['recipient_email']),$approved,true)||(getenv('APP_ENV')==='rc1-test'&&!$transport)){$status='suppressed';$error='smtp_or_recipient_not_approved';}else{$content=self::body($db,$org,$p,CommerceStripeService::origin().self::path($p));$message=array_replace($job,['subject'=>$content['subject'],'text_body'=>$content['text'],'html_body'=>$content['html'],'commerce_reference'=>$job['public_reference']]);if($transport)$transport($message);else MailService::deliverCommerce($message);$status='sent';}}elseif($driver!=='capture')throw new RuntimeException('Invalid delivery mode.');else self::body($db,$org,$p,self::path($p));}}
            catch(Throwable){$status=(int)$job['attempts']+1>=5?'failed':'commerce_queued';$error='delivery_unavailable';}
            $finished=CommerceStore::tx($db,function()use($db,$org,$job,$status,$error){$q=$db->prepare('SELECT lease_token FROM commerce_shopping_deliveries WHERE organization_id=? AND id=? FOR UPDATE');$q->execute([$org,$job['id']]);$lease=$q->fetchColumn();if(!is_string($lease)||!hash_equals($lease,$job['lease_token']))return false;$db->prepare("UPDATE email_queue SET status=?,sent_at=IF(?='sent',NOW(),sent_at),last_error=? WHERE id=?")->execute([$status,$status,$error,$job['email_queue_id']]);$db->prepare("UPDATE commerce_shopping_deliveries SET lease_token=NULL,lease_until_utc=NULL,last_error_code=?,captured_at_utc=IF(?='captured',UTC_TIMESTAMP(),captured_at_utc),available_at_utc=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=?")->execute([$error,$status,min(3600,60*(2**min(6,(int)$job['attempts']))),$job['id']]);$outcome=in_array($status,['captured','sent','suppressed'],true)?$status:'failed';$db->prepare('INSERT INTO email_delivery_log(email_queue_id,transport,outcome,detail) VALUES(?,?,?,?)')->execute([$job['email_queue_id'],getenv('COMMERCE_MAIL_DRIVER')?:'capture',$outcome,$error]);return true;});if($finished){if(isset($result[$status]))$result[$status]++;elseif($status==='commerce_queued')$result['failed']++;}}
        return$result;
    }
}
