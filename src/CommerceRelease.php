<?php
declare(strict_types=1);
require_once __DIR__.'/ReleaseCandidate.php';require_once __DIR__.'/CommerceStorefrontService.php';require_once __DIR__.'/CommerceStripeProvider.php';
/** Metadata and aggregate-only inspection. Caller MUST open a read-only transaction. */
final class CommerceRelease
{
    public static function inspect(PDO $db):array
    {
        if(!$db->inTransaction())throw new LogicException('Read-only transaction required.');
        $issues=[];$notes=[];$schema=CommerceAccess::schemaReady($db);if(!$schema)$issues[]='Commerce migrations 101–109 or protective triggers are incomplete.';
        $contracts=[
            'commerce_payment_attempts'=>['provider','remote_session_id','remote_payment_id','expected_minor','captured_minor','refunded_minor','request_json','provider_created_at_utc','last_checked_at_utc','last_error_code'],
            'commerce_orders'=>['state'],'commerce_organizations'=>['public_sales_enabled'],
            'commerce_order_links'=>['expires_at_utc'],
            'commerce_deliveries'=>['email_queue_id','token_hash','lease_token','lease_until_utc','available_at_utc','last_error_code','public_reference','retrieval_path','created_at_utc'],
            'commerce_provider_webhooks'=>['payload_hash','status','available_at_utc','attempts'],
            'commerce_provider_refunds'=>['request_key','amount_minor','currency','original_order_state','remote_refund_id'],
            'commerce_delivery_events'=>['event_id','payload_hash','delivery_id'],
            'email_queue'=>['category','status','recipient_email'],'email_delivery_log'=>['outcome'],
        ];
        foreach(array_keys($contracts) as $table){$q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if($q->fetchColumn()!=='InnoDB')$issues[]='Transactional InnoDB table required: '.$table;}
        $types=[];foreach($contracts as $table=>$columns){$q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$types[$table]=$q->fetchAll(PDO::FETCH_KEY_PAIR);foreach($columns as $column)if(!isset($types[$table][$column]))$issues[]='Missing column '.$table.'.'.$column;}
        foreach([['commerce_payment_attempts','provider',"'stripe_test'"],['commerce_orders','state',"'refund_pending'"],['email_queue','category',"'commerce'"],['email_queue','status',"'captured'"],['email_queue','status',"'commerce_queued'"],['email_queue','status',"'commerce_sending'"],['email_delivery_log','outcome',"'captured'"]] as [$t,$c,$value])if(!str_contains($types[$t][$c]??'',$value)&&!($t==='commerce_payment_attempts'&&str_starts_with($types[$t][$c]??'','varchar(')))$issues[]='Missing type contract '.$t.'.'.$c.':'.$value;
        $unique=[
            'commerce_payment_attempts'=>[['provider','remote_session_id'],['provider','remote_payment_id']],
            'commerce_provider_webhooks'=>[['organization_id','event_id']],
            'commerce_provider_refunds'=>[['order_id'],['remote_refund_id']],
            'commerce_deliveries'=>[['organization_id','order_id','delivery_key'],['email_queue_id'],['public_reference']],
            'commerce_delivery_events'=>[['organization_id','event_id']],
        ];
        foreach($unique as $table=>$keys){$q=$db->prepare("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND NON_UNIQUE=0 GROUP BY INDEX_NAME");$q->execute([$table]);$actual=$q->fetchAll(PDO::FETCH_KEY_PAIR);foreach($keys as $cols)if(!in_array(implode(',',$cols),$actual,true))$issues[]='Missing unique boundary '.$table.'('.implode(',',$cols).')';}
        $checks=$db->query("SELECT tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME='commerce_payment_attempts' AND tc.CONSTRAINT_TYPE='CHECK'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $providerCheck=$checks['commerce_payment_provider_allowed']??'';$normalized=strtolower(preg_replace('/[_]utf8mb4|[`\s()]/','',str_replace(chr(92),'',$providerCheck)));if($normalized!=="providerin'simulated','stripe_test'")$issues[]='Test-only provider CHECK requires review; inspect SHOW CREATE TABLE before migration 108.';
        $schemaReady=!$issues;$counts=[];if(!$issues){
            $queries=[
                'unresolved_timezone_slots'=>"SELECT COUNT(*) FROM commerce_performances p WHERE p.schedule_status='scheduled' AND EXISTS(SELECT 1 FROM productions pr WHERE pr.id=p.production_id AND pr.lifecycle_stage IN ('upcoming','planning','in_production','performing')) AND (p.starts_at_utc IS NULL OR NOT EXISTS(SELECT 1 FROM commerce_showtime_slots s WHERE s.organization_id=p.organization_id AND s.performance_id=p.id AND s.starts_at_utc=p.starts_at_utc))",
                'duplicate_valid_entitlements'=>"SELECT COUNT(*) FROM (SELECT organization_id,performance_id,seat_key FROM commerce_tickets WHERE state='valid' GROUP BY organization_id,performance_id,seat_key HAVING COUNT(*)>1) x",
                'unprotected_sold_seats'=>"SELECT COUNT(*) FROM commerce_inventory i LEFT JOIN commerce_orders o ON o.id=i.order_id AND o.organization_id=i.organization_id WHERE i.state='sold' AND (o.id IS NULL OR o.state NOT IN ('paid','exception','refund_pending'))",
                'commerce_jobs_in_general_queue'=>"SELECT COUNT(*) FROM email_queue WHERE category='commerce' AND status IN ('queued','sending')",
                'payment_exceptions'=>"SELECT COUNT(*) FROM commerce_orders WHERE state='exception'",
                'refunds_pending'=>"SELECT COUNT(*) FROM commerce_orders WHERE state='refund_pending'",
                'delivery_failures'=>"SELECT COUNT(*) FROM commerce_deliveries d JOIN email_queue q ON q.id=d.email_queue_id WHERE q.status='failed'",
                'legacy_local_time_links'=>"SELECT COUNT(*) FROM commerce_order_links WHERE expires_at_utc IS NULL AND revoked_at IS NULL AND expires_at>NOW()",
            ];foreach($queries as $key=>$sql)$counts[$key]=(int)$db->query($sql)->fetchColumn();
            foreach(['unresolved_timezone_slots','duplicate_valid_entitlements','unprotected_sold_seats','commerce_jobs_in_general_queue'] as $key)if($counts[$key])$issues[]='Aggregate invariant requires review: '.$key;
            foreach(['payment_exceptions','refunds_pending','delivery_failures','legacy_local_time_links'] as $key)if($counts[$key])$notes[]='Operator review: '.$key.' (history is retained; inspect privately).';
        }
        $gates=[
            'preview_requested'=>getenv('COMMERCE_STOREFRONT_PREVIEW_ENABLED')!=='0',
            'stripe_test_configured'=>CommerceStripeProvider::configured(),
            'new_stripe_checkout_requested'=>getenv('COMMERCE_STRIPE_CHECKOUT_ENABLED')==='1',
            'payment_worker_requested'=>getenv('COMMERCE_PAYMENT_WORKER_ENABLED')==='1',
            'mail_worker_requested'=>getenv('COMMERCE_MAIL_WORKER_ENABLED')==='1',
            'mail_driver_capture'=>(getenv('COMMERCE_MAIL_DRIVER')?:'capture')==='capture',
            'mail_delivery_requested'=>getenv('COMMERCE_MAIL_ENABLED')==='1',
            'mail_delivery_approved'=>getenv('COMMERCE_MAIL_APPROVED')==='1',
            'pilot_recipient_list_present'=>trim((string)getenv('COMMERCE_MAIL_TEST_RECIPIENTS'))!=='',
            'mail_bridge_secret_present'=>strlen((string)getenv('COMMERCE_MAIL_WEBHOOK_SECRET'))>=32,
            'public_sales_requested'=>getenv('COMMERCE_PUBLIC_SALES_ENABLED')==='1',
            'public_sales_active'=>CommerceStorefrontService::publicSalesActive(),
        ];
        $organization=[];if(isset($types['commerce_organizations']['public_sales_enabled'])){$q=$db->prepare('SELECT ticketing_enabled,public_sales_enabled FROM commerce_organizations WHERE organization_key=?');$q->execute([CommerceAccess::primaryKey()]);$organization=$q->fetch()?:[];}$launch=CommerceStorefrontService::readiness($organization);if(!$organization)$notes[]='Configured Commerce owner must be initialized before pilot acceptance.';
        if($gates['public_sales_active'])$issues[]='Public activation must remain closed for this test-only release.';
        return ['schema_ready'=>$schemaReady,'acceptance_ready'=>!$issues,'issues'=>$issues,'review_notes'=>$notes,'aggregate_counts'=>$counts,'configuration_flags'=>$gates,'independent_launch_gates'=>$launch,'migration_108_existing_check_names'=>array_keys($checks),'provider_network_verified'=>false,'smtp_network_verified'=>false,'deployment_authorized'=>false,'data_modified'=>false];
    }
}
